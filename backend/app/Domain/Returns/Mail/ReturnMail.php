<?php

namespace App\Domain\Returns\Mail;

use App\Domain\Orders\Mail\OrderMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Models\ReturnRequest;

/** Correos de devoluciones: al cliente (solicitada, aprobada, rechazada, resuelta) y aviso a la tienda. */
class ReturnMail extends OrderMail
{
    public const KINDS = ['requested', 'approved', 'rejected', 'processed', 'new_admin'];

    public function __construct(Order $order, public ReturnRequest $returnRequest, public string $kind)
    {
        parent::__construct($order);
    }

    public function templateKey(): string
    {
        return $this->kind === 'new_admin' ? 'return_new_admin' : 'return_'.$this->kind;
    }

    protected function markdownView(): string
    {
        return 'mail.returns.status';
    }

    protected function variables(): array
    {
        $r = $this->returnRequest;

        return [
            'devolucion' => $r->label(),
            'motivo' => $this->kind === 'rejected' ? (string) $r->rejection_reason : (ReturnRequest::REASONS[$r->reason] ?? $r->reason),
            'resolucion' => (string) $r->resolution,
            ...($this->kind === 'new_admin' ? ['nombre' => $this->order->fullName()] : []),
        ];
    }

    protected function viewData(): array
    {
        $this->returnRequest->loadMissing('items.orderItem');

        return [
            'returnRequest' => $this->returnRequest,
            'kind' => $this->kind,
            'adminUrl' => url('/admin/returns/'.$this->returnRequest->getRouteKey()),
        ];
    }

    protected function logContext(): array
    {
        return $this->kind === 'new_admin' ? ['order_id' => $this->order->id] : parent::logContext();
    }
}
