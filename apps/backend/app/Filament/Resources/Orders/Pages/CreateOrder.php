<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Catalog\Models\Product;
use App\Domain\Fraud\Models\FraudCheck;
use App\Domain\Order\Actions\PlaceOrder;
use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\IncompleteOrder;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Support\CartPricer;
use App\Domain\Shipping\Models\District;
use App\Domain\Shipping\Models\Thana;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Fields;
use App\Support\Money;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Phone / Facebook / WhatsApp orders entered by staff. Goes through the same PlaceOrder action
 * as checkout, so prices come from the database and stock is deducted the same way.
 */
class CreateOrder extends Page
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'নতুন অর্ডার';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('create', Order::class) ?? false;
    }

    public function mount(): void
    {
        $draft = request()->integer('draft') ? IncompleteOrder::find(request()->integer('draft')) : null;

        $this->form->fill([
            'source' => OrderSource::Phone->value,
            'status' => OrderStatus::Confirmed->value,
            'notify' => true,
            'items' => [['quantity' => 1]],
            // Prefilled from an incomplete checkout; placing the order closes that lead (CloseIncompleteOrders).
            ...($draft ? array_filter([
                'phone' => $draft->customer_phone,
                'name' => $draft->customer_name,
                'district_id' => $draft->district_id,
                'thana_id' => $draft->thana_id,
                'address' => $draft->address,
                'note' => $draft->customer_note,
                'items' => collect($draft->items)->map(fn ($item) => [
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                ])->all() ?: null,
            ]) : []),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])->schema([
                    Section::make('কাস্টমার')->columnSpan(['lg' => 2])->columns(2)->schema([
                        TextInput::make('phone')->label('মোবাইল')->tel()->required()->live(onBlur: true)
                            ->rule(fn () => fn ($attribute, $value, $fail) => preg_match(Phone::PATTERN, (string) Phone::normalize($value)) ? null : $fail('সঠিক মোবাইল নম্বর দিন'))
                            ->helperText(fn (?string $state) => $this->phoneHistory($state)),
                        TextInput::make('name')->label('নাম')->required()->minLength(2)->maxLength(100),
                        Select::make('district_id')->label('জেলা')->required()->searchable()->live()
                            ->afterStateUpdated(fn ($set) => $set('thana_id', null))
                            ->options(fn () => District::orderBy('division')->orderBy('name')->get()->groupBy('division')
                                ->map(fn ($districts) => $districts->pluck('name', 'id')->all())->all()),
                        Select::make('thana_id')->label('থানা/উপজেলা')->searchable()
                            ->options(fn (Get $get) => Thana::active()->where('district_id', $get('district_id'))->orderBy('name')->pluck('name', 'id')),
                        Select::make('source')->label('অর্ডার এসেছে')->options(collect(OrderSource::cases())->reject(fn ($s) => $s === OrderSource::Web)
                            ->mapWithKeys(fn (OrderSource $s) => [$s->value => $s->getLabel()]))->required()->native(false),
                        Textarea::make('address')->label('পূর্ণ ঠিকানা')->required()->minLength(8)->maxLength(500)->rows(2)->columnSpanFull(),
                        TextInput::make('note')->label('কাস্টমারের নোট')->maxLength(500),
                        TextInput::make('internal_note')->label('ভেতরের নোট (কাস্টমার দেখবে না)')->maxLength(500),
                    ]),
                    Section::make('সারাংশ')->columnSpan(1)->schema([
                        Text::make(fn (Get $get) => $this->summary($get)),
                        Fields::money('delivery_charge', 'ডেলিভারি চার্জ (বদলাতে চাইলে)')->live(onBlur: true)
                            ->helperText('খালি রাখলে জেলার জোন অনুযায়ী'),
                        Fields::money('discount', 'ডিসকাউন্ট')->live(onBlur: true),
                        Select::make('status')->label('শুরুর স্ট্যাটাস')->options([
                            OrderStatus::Pending->value => OrderStatus::Pending->getLabel(),
                            OrderStatus::Confirmed->value => OrderStatus::Confirmed->getLabel(),
                        ])->required()->native(false),
                        Toggle::make('notify')->label('কাস্টমারকে SMS পাঠাও'),
                    ]),
                ]),
                Section::make('পণ্য')->schema([
                    Repeater::make('items')->hiddenLabel()->minItems(1)->maxItems(50)->addActionLabel('আরেকটা পণ্য')
                        ->columns(['default' => 1, 'md' => 6])->live()
                        ->schema([
                            Select::make('product_id')->label('পণ্য')->required()->searchable()->live()->columnSpan(['md' => 3])
                                ->getSearchResultsUsing(fn (string $search) => Product::visible()
                                    ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                                    ->limit(30)->get()->mapWithKeys(fn (Product $p) => [$p->id => $this->productLabel($p)]))
                                ->getOptionLabelUsing(fn ($value) => ($p = Product::find($value)) ? $this->productLabel($p) : null)
                                ->afterStateUpdated(fn ($set) => $set('variant_id', null)),
                            Select::make('variant_id')->label('ভ্যারিয়েন্ট')->columnSpan(['md' => 2])->live()
                                ->options(fn (Get $get) => ($p = Product::with('variants.attributeValues.attribute')->find($get('product_id')))
                                    ? $p->variants->where('is_active', true)->mapWithKeys(fn ($v) => [$v->id => $v->label('bn').' · '.Money::format($v->price).' · স্টক '.$v->stock])
                                    : [])
                                ->visible(fn (Get $get) => Product::find($get('product_id'))?->isVariable() ?? false)
                                ->required(fn (Get $get) => Product::find($get('product_id'))?->isVariable() ?? false),
                            TextInput::make('quantity')->label('পরিমাণ')->numeric()->integer()->minValue(1)->maxValue(100)->default(1)->required()->live(onBlur: true),
                        ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('create')
                ->footer([
                    Actions::make([
                        Action::make('create')->label('অর্ডার তৈরি করুন')->submit('create')->keyBindings(['mod+s']),
                        Action::make('cancel')->label('বাতিল')->color('gray')->url(OrderResource::getUrl()),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function create(PlaceOrder $placeOrder): void
    {
        $data = $this->form->getState();

        try {
            $order = $placeOrder->handle([
                'name' => trim($data['name']),
                'phone' => Phone::normalize($data['phone']),
                'district_id' => (int) $data['district_id'],
                'thana_id' => $data['thana_id'] ?? null,
                'address' => trim($data['address']),
                'note' => $data['note'] ?? null,
                'payment_method' => 'cod',
                'items' => $this->items($data['items'] ?? []),
            ], [
                'locale' => 'bn',
                'source' => OrderSource::from($data['source']),
                'status' => OrderStatus::from($data['status']),
                'user_id' => auth()->id(),
                'internal_note' => $data['internal_note'] ?? null,
                'delivery_charge' => $data['delivery_charge'] ?? null,
                'discount' => $data['discount'] ?? 0,
                'notify' => (bool) ($data['notify'] ?? false),
            ]);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('অর্ডার হয়নি')->body(collect($e->errors())->flatten()->first())->send();

            return;
        }

        Notification::make()->success()->title("অর্ডার {$order->number} তৈরি হয়েছে")->send();
        $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
    }

    /** @return list<array{product_id:int, variant_id:int|null, quantity:int}> */
    private function items(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => filled($row['product_id'] ?? null))
            ->map(fn ($row) => ['product_id' => (int) $row['product_id'], 'variant_id' => filled($row['variant_id'] ?? null) ? (int) $row['variant_id'] : null, 'quantity' => max(1, (int) ($row['quantity'] ?? 1))])
            ->values()->all();
    }

    private function productLabel(Product $product): string
    {
        $stock = $product->isVariable() ? '' : ' · স্টক '.$product->stock;

        return $product->name.($product->sku ? " ({$product->sku})" : '').' · '.Money::format($product->price).$stock;
    }

    /** Live totals from the same pricer checkout uses. */
    private function summary(Get $get): string
    {
        $items = $this->items($get('items') ?? []);
        if ($items === []) {
            return 'পণ্য যোগ করুন।';
        }

        $quote = app(CartPricer::class)->quote($items, $get('district_id') ? (int) $get('district_id') : null);
        $override = Money::toPoisha($get('delivery_charge'));
        $delivery = $override ?? $quote->deliveryCharge;
        $discount = min(Money::toPoisha($get('discount')) ?? 0, $quote->subtotal);
        $problems = collect($quote->lines)->filter(fn ($l) => $l->problem)->map(fn ($l) => ($l->product?->name ?? '#'.$l->productId).': '.($l->problem === 'unavailable' ? 'পাওয়া যাচ্ছে না' : "স্টকে আছে {$l->available}"));

        $lines = [
            'সাবটোটাল: '.Money::format($quote->subtotal),
            'ডেলিভারি: '.($delivery === null ? 'জেলা বাছুন' : Money::format($delivery)),
            $discount ? 'ডিসকাউন্ট: −'.Money::format($discount) : null,
            'মোট: '.Money::format($quote->subtotal + ($delivery ?? 0) - $discount),
            ...$problems->map(fn ($p) => "⚠ {$p}"),
        ];

        return implode(' · ', array_filter($lines));
    }

    /** Past orders on this number: a quick fake-order check while on the phone. */
    private function phoneHistory(?string $phone): ?string
    {
        $phone = Phone::normalize($phone);
        if (! $phone || ! preg_match(Phone::PATTERN, $phone)) {
            return null;
        }

        $orders = Order::where('customer_phone', $phone)->get(['status']);
        if ($orders->isEmpty()) {
            $external = FraudCheck::where('phone', $phone)->where('source', '!=', 'internal')->first();

            return 'নতুন কাস্টমার'.($external ? " · সব কুরিয়ার: {$external->summary()}" : '');
        }

        $delivered = $orders->where('status', OrderStatus::Delivered)->count();
        $failed = $orders->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Returned])->count();

        $external = FraudCheck::where('phone', $phone)->where('source', '!=', 'internal')->first();

        return "আগের অর্ডার {$orders->count()}টা — ডেলিভারড {$delivered}, বাতিল/রিটার্ন {$failed}"
            .($external ? " · সব কুরিয়ার: {$external->summary()}" : '');
    }
}
