<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Reward\Models\PointTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CustomerAddressRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Http\Resources\V1\CustomerAddressResource;
use App\Http\Resources\V1\CustomerOrderResource;
use App\Http\Resources\V1\CustomerResource;
use App\Http\Resources\V1\PointTransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AccountController extends Controller
{
    public function show(Request $request): CustomerResource
    {
        return new CustomerResource($this->customer($request)->load('addresses.district', 'addresses.thana'));
    }

    public function update(UpdateCustomerRequest $request): CustomerResource
    {
        $customer = $this->customer($request);
        $customer->update($request->validated());

        return new CustomerResource($customer->load('addresses.district', 'addresses.thana'));
    }

    /** Orders placed with this phone, newest first (including guest orders from before sign-up). */
    public function orders(Request $request): AnonymousResourceCollection
    {
        $orders = $this->customer($request)->orders()->with('items.product')->latest('id')->paginate(10);

        return CustomerOrderResource::collection($orders);
    }

    /** Points history, newest first. */
    public function points(Request $request): AnonymousResourceCollection
    {
        $transactions = PointTransaction::query()->where('customer_id', $this->customer($request)->id)->with('order:id,number')->latest('id')->paginate(15);

        return PointTransactionResource::collection($transactions);
    }

    public function storeAddress(CustomerAddressRequest $request): CustomerAddressResource
    {
        $customer = $this->customer($request);
        abort_if($customer->addresses()->count() >= 10, 422, 'সর্বোচ্চ ১০টি ঠিকানা রাখা যায়।');

        $address = $customer->addresses()->create($request->validated());

        return new CustomerAddressResource($address->load('district', 'thana'));
    }

    public function updateAddress(CustomerAddressRequest $request, int $address): CustomerAddressResource
    {
        $model = $this->address($request, $address);
        $model->update($request->validated());

        return new CustomerAddressResource($model->load('district', 'thana'));
    }

    public function destroyAddress(Request $request, int $address): Response
    {
        $this->address($request, $address)->delete();

        return response()->noContent();
    }

    private function customer(Request $request): Customer
    {
        return $request->user();
    }

    private function address(Request $request, int $id): CustomerAddress
    {
        return $this->customer($request)->addresses()->findOrFail($id);
    }
}
