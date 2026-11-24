<?php

namespace App\Domain\Orders\Mail;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Refund;

/** Al cliente: hicimos un reembolso. */
class RefundMail extends OrderMail
{
    public function __construct(Order $order, public Refund $refund)
    {
        parent::__construct($order);
    }

    public function templateKey(): string
    {
        return 'refund';
    }

    protected function markdownView(): string
    {
        return 'mail.orders.refunded';
    }

    protected function variables(): array
    {
        return ['monto' => self::money($this->refund->amount)];
    }

    protected function viewData(): array
    {
        $method = $this->refund->payment?->method_code;

        return ['refund' => $this->refund, 'byCard' => $method !== null && $method !== 'bank_transfer'];
    }
}
