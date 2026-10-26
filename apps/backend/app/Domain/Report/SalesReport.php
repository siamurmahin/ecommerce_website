<?php

namespace App\Domain\Report;

use App\Domain\Finance\Models\Expense;
use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentMethod;
use App\Domain\Order\Models\IncompleteOrder;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Enums\ReturnType;
use App\Domain\Returns\Models\OrderReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sales numbers for a date range (orders placed in it). "Sales" leave out cancelled and returned
 * orders; counts of placed orders include everything. Money is integer poisha.
 */
final class SalesReport
{
    /** Not counted as a sale. */
    public const LOST = [OrderStatus::Cancelled, OrderStatus::Returned];

    public function __construct(public readonly DateRange $range) {}

    public static function for(DateRange $range): self
    {
        return new self($range);
    }

    /** @return Builder<Order> */
    public function orders(): Builder
    {
        return Order::query()->whereBetween('orders.created_at', $this->range->utc());
    }

    /** @return Builder<Order> */
    public function sales(): Builder
    {
        return $this->orders()->whereNotIn('orders.status', array_map(fn ($s) => $s->value, self::LOST));
    }

    /**
     * @return array{orders: int, sales: int, revenue: int, average: int, discount: int, delivery: int,
     *     cancelled: int, delivered: int, returned: int, delivery_rate: ?float, new_customers: int, incomplete: int}
     */
    public function summary(): array
    {
        $lost = "'".implode("','", array_map(fn ($s) => $s->value, self::LOST))."'";
        $row = $this->orders()->toBase()->selectRaw("
            count(*) as orders,
            coalesce(sum(case when status not in ({$lost}) then 1 else 0 end), 0) as sales,
            coalesce(sum(case when status not in ({$lost}) then total else 0 end), 0) as revenue,
            coalesce(sum(case when status not in ({$lost}) then discount else 0 end), 0) as discount,
            coalesce(sum(case when status not in ({$lost}) then delivery_charge else 0 end), 0) as delivery,
            coalesce(sum(case when status = 'cancelled' then 1 else 0 end), 0) as cancelled,
            coalesce(sum(case when status = 'delivered' then 1 else 0 end), 0) as delivered,
            coalesce(sum(case when status = 'returned' then 1 else 0 end), 0) as returned
        ")->first();

        $sales = (int) $row->sales;
        $finished = (int) $row->delivered + (int) $row->returned;

        return [
            'orders' => (int) $row->orders,
            'sales' => $sales,
            'revenue' => (int) $row->revenue,
            'average' => $sales ? intdiv((int) $row->revenue, $sales) : 0,
            'discount' => (int) $row->discount,
            'delivery' => (int) $row->delivery,
            'cancelled' => (int) $row->cancelled,
            'delivered' => (int) $row->delivered,
            'returned' => (int) $row->returned,
            'delivery_rate' => $finished ? round((int) $row->delivered / $finished * 100, 1) : null,
            'new_customers' => $this->firstTimeBuyers(),
            'incomplete' => IncompleteOrder::whereBetween('created_at', $this->range->utc())->count(),
        ];
    }

    /** Phones whose very first order falls in the range. */
    public function firstTimeBuyers(): int
    {
        $firsts = Order::query()->toBase()->selectRaw('customer_phone, min(created_at) as first_at')->groupBy('customer_phone');

        return DB::query()->fromSub($firsts, 'f')->whereBetween('first_at', $this->range->utc())->count();
    }

    /** @return list<array{date: string, orders: int, sales: int, revenue: int}> one row per day, empty days included */
    public function daily(): array
    {
        $day = DateRange::localDateSql('created_at', Order::query()->getConnection()->getDriverName());
        $lost = "'".implode("','", array_map(fn ($s) => $s->value, self::LOST))."'";
        $rows = $this->orders()->toBase()
            ->selectRaw("{$day} as day, count(*) as orders,
                coalesce(sum(case when status not in ({$lost}) then 1 else 0 end), 0) as sales,
                coalesce(sum(case when status not in ({$lost}) then total else 0 end), 0) as revenue")
            ->groupBy('day')->get()->keyBy('day');

        return array_map(fn (string $date) => [
            'date' => $date,
            'orders' => (int) ($rows[$date]->orders ?? 0),
            'sales' => (int) ($rows[$date]->sales ?? 0),
            'revenue' => (int) ($rows[$date]->revenue ?? 0),
        ], $this->range->days());
    }

    /** @return array<string, int> order status value => count */
    public function statuses(): array
    {
        return $this->orders()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Best sellers by units with everything they cost, as kept on each order item: purchase price, other
     * per-unit costs (wrapping, carrying, shared costs) and their share of delivery the shop paid. Profit is
     * before order discounts and expenses. Cost figures are null when an item has no purchase price.
     *
     * @return list<array{product_id: ?int, name: string, quantity: int, revenue: int, orders: int, purchase: ?int, extra: ?int, delivery: ?int, cost: ?int, profit: ?int}>
     */
    public function products(?int $limit = null): array
    {
        return $this->sales()->toBase()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('order_items.product_id, max(order_items.name) as name, sum(order_items.quantity) as quantity, sum(order_items.line_total) as revenue,
                count(distinct orders.id) as orders, sum(order_items.quantity * order_items.unit_cost) as purchase,
                sum(order_items.quantity * order_items.unit_extra_cost) as extra, sum(order_items.delivery_cost) as delivery,
                sum(case when order_items.unit_cost is null then 1 else 0 end) as uncosted')
            ->groupBy('order_items.product_id')
            ->orderByDesc('quantity')->orderByDesc('revenue')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()
            ->map(function ($r) {
                $known = ! (int) $r->uncosted;
                $cost = (int) $r->purchase + (int) $r->extra + (int) $r->delivery;

                return [
                    'product_id' => $r->product_id, 'name' => $r->name, 'quantity' => (int) $r->quantity, 'revenue' => (int) $r->revenue,
                    'orders' => (int) $r->orders,
                    'purchase' => $known ? (int) $r->purchase : null,
                    'extra' => $known ? (int) $r->extra : null,
                    'delivery' => $known ? (int) $r->delivery : null,
                    'cost' => $known ? $cost : null,
                    'profit' => $known ? (int) $r->revenue - $cost : null,
                ];
            })
            ->all();
    }

    /**
     * Profit and loss for the range. A delivery charge the customer pays goes to the courier, so goods sales are
     * the order totals without it (discounts already taken off); delivery the shop pays itself (free delivery) is
     * a cost. Expenses count by the day they were spent.
     *
     * @return array{goods: int, purchase: int, extra: int, delivery: int, refunds: int, restocked: int, gross: int, expenses: array<string, int>, expenses_total: int, net: int, uncosted: int}
     */
    public function profit(): array
    {
        $sales = $this->sales()->toBase()
            ->selectRaw('coalesce(sum(total), 0) as total, coalesce(sum(delivery_charge), 0) as charged, coalesce(sum(delivery_cost), 0) as delivery, coalesce(sum(gift_wrap_cost), 0) as wrapping')->first();
        $items = $this->sales()->toBase()->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('coalesce(sum(order_items.quantity * order_items.unit_cost), 0) as purchase,
                coalesce(sum(order_items.quantity * order_items.unit_extra_cost), 0) as extra,
                coalesce(sum(case when order_items.unit_cost is null then order_items.quantity else 0 end), 0) as uncosted')
            ->first();

        // Partial returns of orders still counted as sales: the money given back, minus the cost of pieces back on the shelf.
        $returns = OrderReturn::query()->toBase()->where('status', ReturnStatus::Completed->value)
            ->whereIn('order_id', $this->sales()->select('orders.id'))
            ->selectRaw('coalesce(sum(refund_amount), 0) as refunds, coalesce(sum(restocked_cost), 0) as restocked')->first();

        $expenses = Expense::query()->toBase()
            ->whereDate('spent_on', '>=', $this->range->start->toDateString())->whereDate('spent_on', '<=', $this->range->end->toDateString())
            ->selectRaw('category, sum(amount) as amount')->groupBy('category')->orderByDesc('amount')
            ->pluck('amount', 'category')->map(fn ($a) => (int) $a)->all();

        $goods = (int) $sales->total - (int) $sales->charged;
        $extra = (int) $items->extra + (int) $sales->wrapping;
        $gross = $goods - (int) $returns->refunds - (int) $items->purchase - $extra + (int) $returns->restocked - (int) $sales->delivery;
        $total = array_sum($expenses);

        return [
            'goods' => $goods,
            'purchase' => (int) $items->purchase,
            'extra' => $extra,
            'delivery' => (int) $sales->delivery,
            'refunds' => (int) $returns->refunds,
            'restocked' => (int) $returns->restocked,
            'gross' => $gross,
            'expenses' => $expenses,
            'expenses_total' => $total,
            'net' => $gross - $total,
            'uncosted' => (int) $items->uncosted,
        ];
    }

    /** @return list<array{district: string, orders: int, revenue: int, delivered: int, returned: int}> */
    public function districts(): array
    {
        $lost = "'".implode("','", array_map(fn ($s) => $s->value, self::LOST))."'";

        return $this->orders()->toBase()
            ->selectRaw("coalesce(district_name, '—') as district, count(*) as orders,
                coalesce(sum(case when status not in ({$lost}) then total else 0 end), 0) as revenue,
                coalesce(sum(case when status = 'delivered' then 1 else 0 end), 0) as delivered,
                coalesce(sum(case when status = 'returned' then 1 else 0 end), 0) as returned")
            ->groupBy('district')->orderByDesc('orders')
            ->get()
            ->map(fn ($r) => ['district' => $r->district, 'orders' => (int) $r->orders, 'revenue' => (int) $r->revenue, 'delivered' => (int) $r->delivered, 'returned' => (int) $r->returned])
            ->all();
    }

    /**
     * Where orders came from: UTM source/campaign for web orders, otherwise the channel staff picked
     * (phone, Facebook…). Grouped in PHP so the JSON column works the same on every database.
     *
     * @return list<array{source: string, campaign: string, orders: int, sales: int, revenue: int}>
     */
    public function sources(): array
    {
        $groups = [];
        $this->orders()->select(['id', 'source', 'utm', 'status', 'total'])->lazyById(500)->each(function (Order $o) use (&$groups) {
            $source = $o->source === OrderSource::Web ? ($o->utm['utm_source'] ?? 'সরাসরি / অর্গানিক') : $o->source->getLabel();
            $campaign = $o->source === OrderSource::Web ? ($o->utm['utm_campaign'] ?? '—') : '—';
            $g = &$groups["{$source}|{$campaign}"];
            $g ??= ['source' => $source, 'campaign' => $campaign, 'orders' => 0, 'sales' => 0, 'revenue' => 0];
            $g['orders']++;
            if (! in_array($o->status, self::LOST, true)) {
                $g['sales']++;
                $g['revenue'] += $o->total;
            }
        });

        return collect($groups)->sortByDesc('orders')->values()->all();
    }

    /**
     * Per utm_campaign: what the orders brought in, the ad spend tagged with that campaign (খরচের তালিকা) and
     * ROAS = sales ÷ spend. Profit is goods sold minus product and delivery costs minus the ad spend.
     *
     * @return list<array{campaign: string, orders: int, sales: int, revenue: int, spend: int, roas: ?string, profit: int}>
     */
    public function campaigns(): array
    {
        $groups = [];
        $costs = OrderItem::query()->toBase()->whereColumn('order_items.order_id', 'orders.id')
            ->selectRaw('coalesce(sum(quantity * (coalesce(unit_cost, 0) + unit_extra_cost)), 0)');
        $row = fn (string $campaign) => ['campaign' => $campaign, 'orders' => 0, 'sales' => 0, 'revenue' => 0, 'spend' => 0, 'roas' => null, 'profit' => 0];

        $this->orders()->select(['id', 'utm', 'status', 'total', 'delivery_charge', 'delivery_cost', 'gift_wrap_cost'])->selectSub($costs, 'item_cost')
            ->lazyById(500)->each(function (Order $o) use (&$groups, $row) {
                $campaign = $o->utm['utm_campaign'] ?? null;
                if (! $campaign) {
                    return;
                }
                $g = &$groups[$campaign];
                $g ??= $row($campaign);
                $g['orders']++;
                if (! in_array($o->status, self::LOST, true)) {
                    $g['sales']++;
                    $g['revenue'] += $o->total;
                    $g['profit'] += $o->total - $o->delivery_charge - (int) $o->item_cost - $o->delivery_cost - $o->gift_wrap_cost;
                }
            });

        Expense::query()->toBase()->where('category', 'ads')
            ->whereDate('spent_on', '>=', $this->range->start->toDateString())->whereDate('spent_on', '<=', $this->range->end->toDateString())
            ->selectRaw("coalesce(campaign, '') as campaign, sum(amount) as amount")->groupBy('campaign')
            ->get()->each(function ($e) use (&$groups, $row) {
                $key = $e->campaign === '' ? '—' : $e->campaign;
                $groups[$key] ??= $row($key === '—' ? 'ক্যাম্পেইন ছাড়া বিজ্ঞাপন খরচ' : $key);
                $groups[$key]['spend'] += (int) $e->amount;
            });

        return collect($groups)->map(function (array $g) {
            $g['roas'] = $g['spend'] > 0 ? number_format($g['revenue'] / $g['spend'], 2).'x' : null;
            $g['profit'] -= $g['spend'];

            return $g;
        })->sortByDesc('revenue')->values()->all();
    }

    /**
     * Returns and exchanges opened in the period, by reason.
     *
     * @return list<array{reason: string, returns: int, exchanges: int, pieces: int, refunded: int}>
     */
    public function returns(): array
    {
        $groups = [];
        OrderReturn::query()->where('status', '!=', ReturnStatus::Rejected->value)
            ->whereBetween('created_at', $this->range->utc())
            ->get()->each(function (OrderReturn $r) use (&$groups) {
                $g = &$groups[$r->reason->value];
                $g ??= ['reason' => $r->reason->getLabel(), 'returns' => 0, 'exchanges' => 0, 'pieces' => 0, 'refunded' => 0];
                $g[$r->type === ReturnType::Exchange ? 'exchanges' : 'returns']++;
                $g['pieces'] += $r->quantities()->sum();
                $g['refunded'] += $r->refund_amount;
            });

        return collect($groups)->sortByDesc(fn ($g) => $g['returns'] + $g['exchanges'])->values()->all();
    }

    /**
     * Staff work: status changes each person made in the period (confirmed, cancelled, shipped…), and how the
     * orders they confirmed turned out so far (delivered vs returned/cancelled).
     *
     * @return list<array{staff: string, confirmed: int, cancelled: int, shipped: int, changes: int, delivered: int, lost: int, success: ?string}>
     */
    public function staff(): array
    {
        $lost = array_map(fn ($s) => $s->value, self::LOST);
        $to = DB::getQueryGrammar()->wrap('h.to'); // "to" is a reserved word
        $rows = DB::table('order_status_histories as h')
            ->leftJoin('users', 'users.id', '=', 'h.user_id')
            ->join('orders', 'orders.id', '=', 'h.order_id')
            ->whereBetween('h.created_at', $this->range->utc())
            ->whereColumn('h.from', '!=', 'h.to')
            ->selectRaw("h.user_id, max(users.name) as name, count(*) as changes,
                count(distinct case when {$to} = 'confirmed' then h.order_id end) as confirmed,
                count(distinct case when {$to} = 'cancelled' then h.order_id end) as cancelled,
                count(distinct case when {$to} = 'shipped' then h.order_id end) as shipped,
                count(distinct case when {$to} = 'confirmed' and orders.status = 'delivered' then h.order_id end) as delivered,
                count(distinct case when {$to} = 'confirmed' and orders.status in ('".implode("','", $lost)."') then h.order_id end) as lost")
            ->groupBy('h.user_id')->orderByDesc('confirmed')->get();

        return $rows->map(fn ($r) => [
            'staff' => $r->user_id ? ($r->name ?? 'মুছে ফেলা স্টাফ') : 'সিস্টেম / কুরিয়ার (স্বয়ংক্রিয়)',
            'confirmed' => (int) $r->confirmed,
            'cancelled' => (int) $r->cancelled,
            'shipped' => (int) $r->shipped,
            'changes' => (int) $r->changes,
            'delivered' => (int) $r->delivered,
            'lost' => (int) $r->lost,
            'success' => $r->delivered + $r->lost > 0 ? round($r->delivered * 100 / ($r->delivered + $r->lost)).'%' : null,
        ])->all();
    }

    /** @return list<array{code: string, orders: int, discount: int, revenue: int}> */
    public function coupons(): array
    {
        return $this->sales()->toBase()->whereNotNull('coupon_code')
            ->selectRaw('coupon_code as code, count(*) as orders, sum(discount) as discount, sum(total) as revenue')
            ->groupBy('coupon_code')->orderByDesc('orders')
            ->get()
            ->map(fn ($r) => ['code' => $r->code, 'orders' => (int) $r->orders, 'discount' => (int) $r->discount, 'revenue' => (int) $r->revenue])
            ->all();
    }

    /** @return list<array{title: string, orders: int, sales: int, revenue: int}> */
    public function landingPages(): array
    {
        $lost = "'".implode("','", array_map(fn ($s) => $s->value, self::LOST))."'";

        return $this->orders()->toBase()->whereNotNull('landing_page_id')
            ->leftJoin('landing_pages', 'landing_pages.id', '=', 'orders.landing_page_id')
            ->selectRaw("coalesce(max(landing_pages.title), '—') as title, count(*) as orders,
                coalesce(sum(case when orders.status not in ({$lost}) then 1 else 0 end), 0) as sales,
                coalesce(sum(case when orders.status not in ({$lost}) then orders.total else 0 end), 0) as revenue")
            ->groupBy('orders.landing_page_id')->orderByDesc('orders')
            ->get()
            ->map(fn ($r) => ['title' => $r->title, 'orders' => (int) $r->orders, 'sales' => (int) $r->sales, 'revenue' => (int) $r->revenue])
            ->all();
    }

    /** @return list<array{method: string, orders: int, revenue: int}> */
    public function paymentMethods(): array
    {
        return $this->sales()->toBase()
            ->selectRaw('payment_method, count(*) as orders, sum(total) as revenue')
            ->groupBy('payment_method')->orderByDesc('orders')
            ->get()
            ->map(fn ($r) => ['method' => PaymentMethod::tryFrom($r->payment_method)?->getLabel() ?? $r->payment_method, 'orders' => (int) $r->orders, 'revenue' => (int) $r->revenue])
            ->all();
    }
}
