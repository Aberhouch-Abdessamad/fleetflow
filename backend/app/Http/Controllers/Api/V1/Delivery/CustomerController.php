<?php

namespace App\Http\Controllers\Api\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Delivery\AddressRequest;
use App\Http\Requests\Api\V1\Delivery\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DeliveryResource;
use App\Models\Customer;
use App\Services\Customer\CustomerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): JsonResponse
    {
        $q = Customer::with('addresses')->withCount('deliveries')
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', $this->like($s))
                ->orWhere('email', 'like', $this->like($s))->orWhere('phone', 'like', $this->like($s))))
            ->when($request->query('type'), fn ($q, $t) => $q->where('customer_type', $t));

        return $this->paginated($this->sort($q, $request, ['name', 'created_at'], 'name'), CustomerResource::class, $request);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        return ApiResponse::created(new CustomerResource($this->customers->create($request->validated())), 'Customer created.');
    }

    public function show(Customer $customer): JsonResponse
    {
        return ApiResponse::ok(new CustomerResource($customer->load('addresses')->loadCount('deliveries')));
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        return ApiResponse::ok(new CustomerResource($this->customers->update($customer, collect($request->validated())->except('addresses')->all())), 'Customer updated.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return ApiResponse::ok(null, 'Customer archived.');
    }

    public function deliveries(Request $request, Customer $customer): JsonResponse
    {
        return $this->paginated($customer->deliveries()->with('driver')->latest(), DeliveryResource::class, $request);
    }

    public function storeAddress(AddressRequest $request, Customer $customer): JsonResponse
    {
        $this->customers->saveAddress($customer, $request->validated());

        return ApiResponse::created(new CustomerResource($customer->load('addresses')), 'Address saved.');
    }

    public function updateAddress(AddressRequest $request, Customer $customer, int $address): JsonResponse
    {
        $this->customers->saveAddress($customer, $request->validated(), $customer->addresses()->findOrFail($address));

        return ApiResponse::ok(new CustomerResource($customer->load('addresses')), 'Address saved.');
    }

    public function destroyAddress(Customer $customer, int $address): JsonResponse
    {
        $customer->addresses()->findOrFail($address)->delete();

        return ApiResponse::ok(new CustomerResource($customer->load('addresses')), 'Address removed.');
    }
}
