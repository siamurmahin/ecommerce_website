<?php

namespace App\Domain\Report;

use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentMethod;
use App\Domain\Order\Models\IncompleteOrder;
use App\Domain\Order\Models\Order;
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

    /** @return list<array{product_id: ?int, name: string, quantity: int, revenue: int, orders: int}> best sellers by units */
    public function products(?int $limit = null): array
    {
        return $this->sales()->toBase()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('order_items.product_id, max(order_items.name) as name, sum(order_items.quantity) as quantity, sum(order_items.line_total) as revenue, count(distinct orders.id) as orders')
            ->groupBy('order_items.product_id')
            ->orderByDesc('quantity')->orderByDesc('revenue')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()
            ->map(fn ($r) => ['product_id' => $r->product_id, 'name' => $r->name, 'quantity' => (int) $r->quantity, 'revenue' => (int) $r->revenue, 'orders' => (int) $r->orders])
            ->all();
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
