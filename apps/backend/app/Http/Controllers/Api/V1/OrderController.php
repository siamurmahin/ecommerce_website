<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Actions\PlaceOrder;
use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlaceOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Support\ClientIp;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /** Guest checkout. Returns the order plus an access token for the thank-you page. */
    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): JsonResponse
    {
        $customer = $request->user() instanceof Customer ? $request->user() : null;
        $data = $request->validated();
        if (! $customer) {
            unset($data['redeem_points']);
        }

        $order = $placeOrder->handle($data, [
            'ip' => ClientIp::of($request), // signed-in orders arrive through the storefront's proxy
            'user_agent' => $request->userAgent(),
            'locale' => app()->getLocale(),
            'customer' => $customer,
        ]);

        return (new OrderResource($order->load(['items.product', 'items.variant.attributeValues.attribute', 'district', 'deliveryZone'])))
            ->additional(['access_token' => $order->access_token])
            ->response()
            ->setStatusCode(201);
    }

    /** Thank-you page lookup; the token from `store` is required so order numbers can't be enumerated. */
    public function show(Request $request, string $number): OrderResource
    {
        $request->validate(['token' => ['required', 'string']]);

        $order = Order::where('number', $number)->firstOrFail();
        abort_unless(hash_equals($order->access_token, (string) $request->query('token')), 404);

        return new OrderResource($order->load(['items.product', 'items.variant.attributeValues.attribute', 'district', 'deliveryZone', 'statusHistories']));
    }

    /**
     * Guest order tracking: order number + the phone it was placed with.
     * Returns the access token so the storefront can open the order page.
     *
     * @response array{number: string, access_token: string}
     */
    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $order = Order::where('number', strtoupper(preg_replace('/\s+/', '', Phone::latinDigits($data['number']))))
            ->where('customer_phone', Phone::normalize($data['phone']))
            ->first();

        abort_unless($order, 404);

        return response()->json(['number' => $order->number, 'access_token' => $order->access_token]);
    }
}
