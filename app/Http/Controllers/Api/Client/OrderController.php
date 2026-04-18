<?php

namespace App\Http\Controllers\Api\Client;

use App\Actions\PlaceOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\WhatsAppService;

class OrderController extends Controller
{
    public function store(PlaceOrderRequest $request, PlaceOrderAction $action, WhatsAppService $whatsApp)
    {
        $order = $action->execute(app('current_company'), $request->validated());

        return response()->json([
            'order' => new OrderResource($order),
            'whatsapp_url' => $whatsApp->buildOrderUrl($order),
        ], 201);
    }
}
