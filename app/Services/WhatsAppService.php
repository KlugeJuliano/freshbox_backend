<?php

namespace App\Services;

use App\Models\Order;

class WhatsAppService
{
    public function buildOrderUrl(Order $order): string
    {
        $phone = preg_replace('/\D/', '', (string) $order->store->whatsapp);
        $message = $this->buildMessage($order);

        if (strlen($message) > 4000) {
            $message = substr($message, 0, 3990)."\n\n[mensagem truncada]";
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    public function buildMessage(Order $order): string
    {
        $type = $order->delivery_type === 'delivery' ? 'Entrega' : 'Retirada';

        $lines = [
            "Pedido #{$order->id}",
            '',
            "Cliente: {$order->customer_name}",
            "Telefone: {$order->customer_phone}",
            "Tipo: {$type}",
            '',
            'Itens:',
        ];

        foreach ($order->items as $item) {
            $lines[] = "- {$item->quantity}x {$item->product_name} ({$item->product_unit}) - R$ ".number_format((float) $item->subtotal, 2, ',', '.');
        }

        $lines[] = '';
        $lines[] = 'Subtotal: R$ '.number_format((float) $order->subtotal, 2, ',', '.');

        if ((float) $order->delivery_fee > 0) {
            $lines[] = 'Entrega: R$ '.number_format((float) $order->delivery_fee, 2, ',', '.');
        }

        $lines[] = 'Total: R$ '.number_format((float) $order->total, 2, ',', '.');

        if ($order->delivery_type === 'delivery') {
            $address = trim(implode(', ', array_filter([
                $order->delivery_street,
                $order->delivery_number,
                $order->delivery_complement,
            ])));

            $cityLine = trim(implode(' - ', array_filter([
                $order->delivery_neighborhood,
                $order->delivery_city,
            ])));

            $lines[] = '';
            $lines[] = 'Endereço: '.trim(implode(' | ', array_filter([$address, $cityLine])));
        }

        if ($order->payment_method) {
            $lines[] = 'Pagamento: '.$order->payment_method;
        }

        if ($order->observations) {
            $lines[] = '';
            $lines[] = 'Observações: '.$order->observations;
        }

        return implode("\n", $lines);
    }
}
