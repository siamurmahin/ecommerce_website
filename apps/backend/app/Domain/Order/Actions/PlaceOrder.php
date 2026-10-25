<?php

namespace App\Domain\Order\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\LandingPage;
use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentMethod;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Events\OrderPlaced;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Support\CartPricer;
use App\Domain\Order\Support\Discount;
use App\Domain\Order\Support\Inventory;
use App\Domain\Order\Support\QuoteLine;
use App\Domain\Reward\Models\PointTransaction;
use App\Domain\Reward\PointLedger;
use App\Domain\Shipping\Models\Thana;
use App\Domain\Tracking\ServerEvent;
use App\Domain\Tracking\ServerTracker;
use App\Settings\PaymentSettings;
use App\Settings\RewardSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlaceOrder
{
    public function __construct(
        private CartPricer $pricer,
        private Inventory $inventory,
        private ServerTracker $tracker,
        private RewardSettings $rewards,
        private PointLedger $ledger,
    ) {}

    /**
     * @param  array{
     *     name:string, phone:string, email?:string|null, district_id:int, thana_id?:int|null, address:string, note?:string|null,
     *     payment_method:string, payment_wallet?:string|null, payment_sender?:string|null, payment_trx_id?:string|null, items:list<array{product_id:int, variant_id?:int|null, quantity:int}>,
     *     event_id?:string|null, utm?:array<string,string>|null, fbp?:string|null, fbc?:string|null, ttp?:string|null, ttclid?:string|null,
     *     ga_client_id?:string|null, ga_session_id?:string|null, landing_page_id?:int|null, redeem_points?:int|null
     * }  $data
     * @param  array{
     *     ip?:string|null, user_agent?:string|null, locale?:string,
     *     source?:OrderSource, status?:OrderStatus, user_id?:int|null, internal_note?:string|null,
     *     delivery_charge?:int|null, discount?:int, notify?:bool, customer?:Customer|null
     * }  $context  admin-only options (source, starting status, charge override, discount) come from here, never from the storefront payload
     */
    public function handle(array $data, array $context = []): Order
    {
        // A retried submit (double tap, flaky network) returns the order it already created.
        if (filled($data['event_id'] ?? null) && $existing = Order::firstWhere('event_id', $data['event_id'])) {
            return $existing;
        }

        // Staff can still take orders from a blocked number by hand; the storefront can't.
        if (($context['source'] ?? OrderSource::Web) === OrderSource::Web && Customer::isBlocked($data['phone'])) {
            throw ValidationException::withMessages(['phone' => 'এই নম্বর থেকে অনলাইনে অর্ডার নেওয়া যাচ্ছে না। অর্ডারের জন্য হটলাইনে কল করুন।']);
        }

        $quote = $this->pricer->quote($data['items'], $data['district_id'], $data['coupon_code'] ?? null, $data['phone']);

        if ($quote->coupon && ! $quote->coupon->applied()) {
            throw ValidationException::withMessages(['coupon_code' => $quote->coupon->message()]);
        }

        if ($quote->hasProblems()) {
            $names = collect($quote->lines)
                ->filter(fn (QuoteLine $l) => $l->problem)
                ->map(fn (QuoteLine $l) => $l->product?->localized('name') ?? '#'.$l->productId)
                ->implode(', ');

            throw ValidationException::withMessages(['items' => "স্টক বদলেছে, কার্ট আপডেট করুন: {$names}"]);
        }

        $deliveryCharge = $context['delivery_charge'] ?? $quote->deliveryCharge;
        $thana = filled($data['thana_id'] ?? null)
            ? Thana::active()->where('district_id', $quote->district?->id)->find($data['thana_id'])
            : null;

        if (filled($data['thana_id'] ?? null) && ! $thana) {
            throw ValidationException::withMessages(['thana_id' => 'থানা/উপজেলা এই জেলার নয়।']);
        }

        if (! $quote->zone && $deliveryCharge === null) {
            throw ValidationException::withMessages(['district_id' => 'এই এলাকায় এখন ডেলিভারি চালু নেই।']);
        }

        // Bundle/coupon savings plus any discount staff gave on a manual order.
        $discount = min($quote->discountTotal() + ($context['discount'] ?? 0), $quote->subtotal);
        $discounts = $quote->discounts;
        $points = $this->redeemPoints($data, $context['customer'] ?? null, $quote->subtotal - $discount);
        if ($points > 0) {
            $value = $points * $this->rewards->point_value;
            $discount += $value;
            $discounts[] = new Discount(Discount::POINTS, $points.' পয়েন্ট', $value);
        }
        $total = $quote->subtotal + ($deliveryCharge ?? 0) - $discount;
        $payment = $this->payment($data, $total, $deliveryCharge ?? 0, $context);

        return DB::transaction(function () use ($data, $context, $quote, $deliveryCharge, $thana, $payment, $discount, $discounts, $points, $total) {
            $order = Order::create([
                'access_token' => Str::random(40),
                'status' => $status = $context['status'] ?? OrderStatus::Pending,
                ...$payment,
                'customer_name' => $data['name'],
                'customer_phone' => $data['phone'],
                'customer_email' => $data['email'] ?? null,
                'district_id' => $quote->district->id,
                'district_name' => $quote->district->name,
                'thana_id' => $thana?->id,
                'thana_name' => $thana?->name,
                'address' => $data['address'],
                'delivery_zone_id' => $quote->zone?->id,
                'delivery_zone_name' => $quote->zone?->name,
                'customer_note' => $data['note'] ?? null,
                'subtotal' => $quote->subtotal,
                'delivery_charge' => $deliveryCharge ?? 0,
                'discount' => $discount,
                'coupon_id' => $quote->coupon?->coupon?->id,
                'coupon_code' => $quote->coupon?->coupon?->code,
                'discounts' => array_map(fn ($d) => $d->toArray(), $discounts) ?: null,
                'points_redeemed' => $points,
                'referrer_id' => $quote->coupon?->applied() ? $quote->coupon->referrer?->id : null,
                'total' => $total,
                'source' => $context['source'] ?? OrderSource::Web,
                // Attribution only: a page deleted or switched off mid-checkout must not block the order.
                'landing_page_id' => filled($data['landing_page_id'] ?? null) ? LandingPage::whereKey($data['landing_page_id'])->value('id') : null,
                'internal_note' => $context['internal_note'] ?? null,
                'locale' => $context['locale'] ?? 'bn',
                'event_id' => $data['event_id'] ?? null,
                'utm' => $data['utm'] ?? null,
                'fbp' => $data['fbp'] ?? null,
                'fbc' => $data['fbc'] ?? null,
                'ttp' => $data['ttp'] ?? null,
                'ttclid' => $data['ttclid'] ?? null,
                'ga_client_id' => $data['ga_client_id'] ?? null,
                'ga_session_id' => $data['ga_session_id'] ?? null,
                'ip' => $context['ip'] ?? null,
                'user_agent' => Str::limit($context['user_agent'] ?? '', 250, ''),
            ]);

            $order->update(['number' => 'LJ'.(10000 + $order->id)]);

            foreach ($quote->lines as $line) {
                $order->items()->create([
                    'product_id' => $line->productId,
                    'product_variant_id' => $line->variantId,
                    'name' => $line->product->name,
                    'variant_label' => $line->variant?->label('bn'),
                    'sku' => $line->variant?->sku ?? $line->product->sku,
                    'image' => $line->imagePath(),
                    'unit_price' => $line->unitPrice,
                    'quantity' => $line->quantity,
                    'line_total' => $line->lineTotal(),
                    'order_bump_id' => $line->bumpId,
                ]);
            }

            if ($coupon = $quote->coupon?->coupon) {
                // Atomic so two last-minute orders can't both take the final use.
                $claimed = Coupon::whereKey($coupon->id)
                    ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
                    ->increment('used_count');
                if (! $claimed) {
                    throw ValidationException::withMessages(['coupon_code' => 'এই কুপনের ব্যবহার সীমা শেষ।']);
                }
                $order->couponRedemption()->create(['coupon_id' => $coupon->id, 'phone' => $order->customer_phone, 'amount' => $quote->coupon->discount]);
            }

            if ($points > 0) {
                // Locked so two orders at once can't spend the same points.
                $customer = Customer::whereKey($context['customer']->id)->lockForUpdate()->first();
                if ($customer->points_balance < $points) {
                    throw ValidationException::withMessages(['redeem_points' => 'আপনার যথেষ্ট পয়েন্ট নেই।']);
                }
                $this->ledger->post($customer, -$points, PointTransaction::REDEEM, $order, $order->number);
            }
            if ($order->referrer_id) {
                Customer::firstOrCreate(['phone' => $order->customer_phone], ['name' => $order->customer_name])
                    ->whereNull('referred_by_id')->where('phone', $order->customer_phone)->update(['referred_by_id' => $order->referrer_id]);
            }

            $this->inventory->deduct($order->load('items'));
            $order->statusHistories()->create(['to' => $status, 'user_id' => $context['user_id'] ?? null]);

            // Queued after commit; shares event_id with the browser's Purchase for de-duplication.
            // Staff-entered orders have no ad click behind them, so they aren't reported.
            if ($order->source === OrderSource::Web) {
                $this->tracker->dispatch(ServerEvent::purchase($order));
            }
            DB::afterCommit(fn () => OrderPlaced::dispatch($order, $context['notify'] ?? true));

            return $order;
        });
    }

    /** Points a signed-in customer spends on this order; only on an order to their own number. */
    private function redeemPoints(array $data, ?Customer $customer, int $productsTotal): int
    {
        $wanted = (int) ($data['redeem_points'] ?? 0);
        if ($wanted <= 0) {
            return 0;
        }
        if (! $customer || $customer->phone !== $data['phone']) {
            throw ValidationException::withMessages(['redeem_points' => 'পয়েন্ট শুধু নিজের অ্যাকাউন্টের নম্বরে অর্ডারে ব্যবহার করা যায়।']);
        }
        if ($wanted > $this->rewards->redeemable($customer->points_balance, $productsTotal)) {
            throw ValidationException::withMessages(['redeem_points' => 'এই অর্ডারে এত পয়েন্ট ব্যবহার করা যাবে না।']);
        }

        return $wanted;
    }

    /**
     * Method, wallet and transaction fields for a new order. Storefront orders must use a method the
     * shop has switched on; wallet payments (and the COD delivery advance, when required) need the
     * sender number and a transaction ID that no other live order already used.
     *
     * @return array<string, mixed>
     */
    private function payment(array $data, int $total, int $deliveryCharge, array $context): array
    {
        $method = PaymentMethod::from($data['payment_method']);
        $base = ['payment_method' => $method, 'payment_status' => PaymentStatus::Unpaid];

        // Staff-entered orders record payment by hand from the order page.
        if (($context['source'] ?? OrderSource::Web) !== OrderSource::Web) {
            return $base;
        }

        $settings = app(PaymentSettings::class);
        if (! $settings->isAvailable($method)) {
            throw ValidationException::withMessages(['payment_method' => 'এই পেমেন্ট মাধ্যম এখন চালু নেই।']);
        }

        $advance = match (true) {
            $method->isWallet() => $total,
            $settings->requiresDeliveryAdvance() && $deliveryCharge > 0 => $deliveryCharge,
            default => 0,
        };
        if ($advance === 0) {
            return $base;
        }

        $wallet = $method->isWallet() ? $method : PaymentMethod::tryFrom((string) ($data['payment_wallet'] ?? ''));
        if (! $wallet || ! in_array($wallet, $settings->activeWallets(), true)) {
            throw ValidationException::withMessages(['payment_wallet' => 'কোন মাধ্যমে টাকা পাঠিয়েছেন বেছে নিন।']);
        }

        $errors = [];
        $sender = $data['payment_sender'] ?? null;
        $trx = strtoupper(trim((string) ($data['payment_trx_id'] ?? '')));
        if (blank($sender)) {
            $errors['payment_sender'] = 'যে নম্বর থেকে টাকা পাঠিয়েছেন সেটা দিন।';
        }
        if ($trx === '') {
            $errors['payment_trx_id'] = 'ট্রানজেকশন আইডি (TrxID) দিন।';
        } elseif (Order::where('payment_trx_id', $trx)->where('status', '!=', OrderStatus::Cancelled)->exists()) {
            $errors['payment_trx_id'] = 'এই ট্রানজেকশন আইডি দিয়ে আগেই একটা অর্ডার হয়েছে।';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            ...$base,
            'payment_status' => PaymentStatus::Pending,
            'payment_wallet' => $wallet,
            'payment_sender' => $sender,
            'payment_trx_id' => $trx,
            'advance_amount' => $advance,
        ];
    }
}
