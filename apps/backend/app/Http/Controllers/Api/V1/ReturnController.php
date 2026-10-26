<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Order\Models\Order;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\OrderReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReturnRequest;
use App\Support\Returns;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    /**
     * A customer asks to return or exchange items of a delivered order, from the order page (order number + access token).
     *
     * @response 201 array{id: int, status: string}
     */
    public function store(StoreReturnRequest $request, string $number): JsonResponse
    {
        $order = Order::where('number', $number)->with('items')->firstOrFail();
        abort_unless(hash_equals($order->access_token, (string) $request->input('token')), 404);

        if (! Returns::accepts($order)) {
            throw ValidationException::withMessages(['items' => __('এই অর্ডারে এখন আর রিটার্ন বা এক্সচেঞ্জের অনুরোধ করা যাবে না।')]);
        }

        $items = collect($request->validated('items'))->groupBy('order_item_id')
            ->map(fn ($rows, $id) => ['order_item_id' => (int) $id, 'quantity' => (int) $rows->sum('quantity')]);
        foreach ($items as $id => $row) {
            $item = $order->items->firstWhere('id', $id);
            if (! $item || $row['quantity'] > $item->quantity) {
                throw ValidationException::withMessages(['items' => __('অর্ডারের পণ্য আর পরিমাণ মিলছে না।')]);
            }
        }

        $return = OrderReturn::create([
            'order_id' => $order->id,
            'type' => $request->validated('type'),
            'reason' => $request->validated('reason'),
            'note' => $request->validated('note'),
            'items' => $items->values()->all(),
            'status' => ReturnStatus::Requested,
            'source' => 'customer',
        ]);

        return response()->json(['id' => $return->id, 'status' => $return->status->value], 201);
    }
}
