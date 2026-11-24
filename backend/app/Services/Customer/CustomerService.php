<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create(fillable(Customer::class, $data));
            foreach ($data['addresses'] ?? [] as $address) {
                $this->saveAddress($customer, $address);
            }
            $this->logger->log('customer.created', $customer);

            return $customer->load('addresses');
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update(fillable(Customer::class, $data));

        return $customer->load('addresses');
    }

    public function saveAddress(Customer $customer, array $data, ?CustomerAddress $address = null): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data, $address) {
            $address ??= new CustomerAddress;
            $address->fill(fillable(CustomerAddress::class, $data));
            $address->customer()->associate($customer);
            if ($address->is_default || ! $customer->addresses()->exists()) {
                $customer->addresses()->where('address_type', $address->address_type)->whereKeyNot($address->id)->update(['is_default' => false]);
                $address->is_default = true;
            }
            $address->save();

            return $address;
        });
    }
}
