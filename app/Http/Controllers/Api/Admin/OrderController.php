<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;

class OrderController extends Controller
{
    private function companyId(): string
    {
        return app('current_company')->id;
    }

    public function index()
    {
        $orders = Order::query()
            ->where('company_id', $this->companyId())
            ->when(request('status'), fn ($query, $status) => $query->where('status', $status))
            ->with('items')
            ->latest()
            ->paginate(30);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorizeCompany($order);

        return new OrderResource($order->load(['items', 'store']));
    }

    public function updateStatus(StoreOrderStatusRequest $request, Order $order): OrderResource
    {
        $this->authorizeCompany($order);

        $data = $request->validated();

        $extra = [];

        if ($data['status'] === 'completed' && ! $order->completed_at) {
            $extra['completed_at'] = now();
        }

        if (in_array($data['status'], ['preparing', 'ready', 'dispatched', 'completed'], true) && ! $order->confirmed_at) {
            $extra['confirmed_at'] = now();
        }

        $order->update([
            'status' => $data['status'],
            ...$extra,
        ]);

        return new OrderResource($order->fresh()->load('items'));
    }

    private function authorizeCompany(Order $order): void
    {
        abort_if($order->company_id !== $this->companyId(), 403);
    }
}
