<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use App\Settings\BrandSettings;
use App\Settings\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Printable A5 invoices; `orders` is one or more comma-separated order numbers. */
class InvoiceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $numbers = array_filter(explode(',', (string) $request->query('orders')));
        abort_if($numbers === [] || count($numbers) > 200, 404);

        $orders = Order::with('items')->whereIn('number', $numbers)->orderBy('id')->get();
        abort_if($orders->isEmpty(), 404);
        $orders->each(fn (Order $order) => Gate::authorize('view', $order));

        return view('admin.invoice', [
            'orders' => $orders,
            'shop' => [
                'name' => app(BrandSettings::class)->name,
                'phone' => app(SiteSettings::class)->hotline,
                'website' => config('shop.storefront_url'),
            ],
        ]);
    }
}
