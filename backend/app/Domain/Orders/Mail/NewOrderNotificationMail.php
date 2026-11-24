<?php

namespace App\Domain\Orders\Mail;

/** A la tienda: llegó un pedido nuevo. */
class NewOrderNotificationMail extends OrderMail
{
    public function templateKey(): string
    {
        return 'new_order_admin';
    }

    protected function markdownView(): string
    {
        return 'mail.orders.new-order';
    }

    protected function variables(): array
    {
        return ['nombre' => $this->order->fullName()];
    }

    protected function viewData(): array
    {
        return ['adminUrl' => url('/admin/orders/'.$this->order->getRouteKey())];
    }

    protected function logContext(): array
    {
        return ['order_id' => $this->order->id];
    }
}
