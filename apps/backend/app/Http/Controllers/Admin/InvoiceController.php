<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use App\Settings\BrandSettings;
use App\Settings\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Printable A5 invoices, or packing slips (`?type=slip`: no prices, a pick list for the whole batch first);
 * `orders` is one or more comma-separated order numbers.
 */
class InvoiceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $numbers = array_filter(explode(',', (string) $request->query('orders')));
        abort_if($numbers === [] || count($numbers) > 200, 404);

        $orders = Order::with(['items', 'latestShipment'])->whereIn('number', $numbers)->orderBy('id')->get();
        abort_if($orders->isEmpty(), 404);
        $orders->each(fn (Order $order) => Gate::authorize('view', $order));

        $slip = $request->query('type') === 'slip';

        return view($slip ? 'admin.packing-slip' : 'admin.invoice', [
            'orders' => $orders,
            // Pick list: every piece in the batch, grouped by product + variant.
            'picks' => $slip ? $orders->flatMap->items->groupBy(fn ($i) => $i->product_id.'|'.$i->product_variant_id.'|'.$i->name)
                ->map(fn ($items) => ['name' => $items->first()->name, 'variant' => $items->first()->variant_label, 'sku' => $items->first()->sku, 'quantity' => $items->sum('quantity')])
                ->sortBy('name')->values() : collect(),
            'shop' => [
                'name' => app(BrandSettings::class)->name,
                'phone' => app(SiteSettings::class)->hotline,
                'website' => config('shop.storefront_url'),
            ],
        ]);
    }
}
