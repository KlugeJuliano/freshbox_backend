<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    public function execute(Company $company, array $data): Order
    {
        return DB::transaction(function () use ($company, $data) {
            $store = $company->stores()->where('is_active', true)->firstOrFail();

            if ($data['delivery_type'] === 'delivery' && ! $store->accepts_delivery) {
                throw ValidationException::withMessages([
                    'delivery_type' => ['Esta loja não aceita entregas no momento.'],
                ]);
            }

            if ($data['delivery_type'] === 'pickup' && ! $store->accepts_pickup) {
                throw ValidationException::withMessages([
                    'delivery_type' => ['Esta loja não aceita retirada no momento.'],
                ]);
            }

            $productIds = collect($data['items'])->pluck('product_id')->all();

            $products = Product::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $items = [];
            $subtotal = 0.0;

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (! $product || ! $product->is_active || ! $product->is_available) {
                    throw ValidationException::withMessages([
                        'items' => ["Produto {$item['product_id']} não está disponível."],
                    ]);
                }

                $unitPrice = (float) $product->effective_price;
                $itemTotal = $unitPrice * (int) $item['quantity'];
                $subtotal += $itemTotal;

                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_unit' => $product->unit,
                    'unit_price' => $unitPrice,
                    'quantity' => (int) $item['quantity'],
                    'subtotal' => $itemTotal,
                    'observation' => $item['observation'] ?? null,
                ];
            }

            if ((float) $store->min_order_value > 0 && $subtotal < (float) $store->min_order_value) {
                throw ValidationException::withMessages([
                    'subtotal' => ['Valor mínimo do pedido é R$ '.number_format((float) $store->min_order_value, 2, ',', '.').'.'],
                ]);
            }

            $deliveryFee = $data['delivery_type'] === 'delivery' ? (float) $store->delivery_fee : 0.0;

            $order = Order::create([
                'company_id' => $company->id,
                'store_id' => $store->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'delivery_type' => $data['delivery_type'],
                'delivery_street' => data_get($data, 'address.street'),
                'delivery_number' => data_get($data, 'address.number'),
                'delivery_complement' => data_get($data, 'address.complement'),
                'delivery_neighborhood' => data_get($data, 'address.neighborhood'),
                'delivery_city' => data_get($data, 'address.city'),
                'delivery_zip' => data_get($data, 'address.zip'),
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $subtotal + $deliveryFee,
                'payment_method' => $data['payment_method'] ?? null,
                'observations' => $data['observations'] ?? null,
                'status' => 'new',
            ]);

            $order->items()->createMany($items);

            return $order->load(['items', 'store']);
        });
    }
}
