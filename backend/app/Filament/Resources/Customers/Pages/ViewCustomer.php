<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Domain\Customers\Models\Customer;
use App\Domain\System\Services\AuditLogger;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function getTitle(): string
    {
        return $this->record->fullName();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('block')->label('Bloquear')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                ->visible(fn (Customer $record) => $record->status === 'active')
                ->authorize(fn () => auth()->user()?->can('customers.update'))
                ->requiresConfirmation()
                ->modalHeading('Bloquear cliente')
                ->modalDescription('No podrá ingresar a su cuenta y se cierran sus sesiones abiertas. Sus pedidos se mantienen.')
                ->modalSubmitActionLabel('Bloquear')
                ->action(fn (Customer $record) => $this->setStatus($record, 'blocked')),
            Action::make('unblock')->label('Desbloquear')->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (Customer $record) => $record->status === 'blocked')
                ->authorize(fn () => auth()->user()?->can('customers.update'))
                ->requiresConfirmation()
                ->modalHeading('Desbloquear cliente')
                ->modalSubmitActionLabel('Desbloquear')
                ->action(fn (Customer $record) => $this->setStatus($record, 'active')),
        ];
    }

    private function setStatus(Customer $customer, string $status): void
    {
        $old = $customer->status;
        $customer->update(['status' => $status]);
        if ($status === 'blocked') {
            $customer->tokens()->delete();
        }
        app(AuditLogger::class)->record('Customer.'.($status === 'blocked' ? 'blocked' : 'unblocked'), $customer, ['status' => $old], ['status' => $status]);
        Notification::make()->success()->title($status === 'blocked' ? 'Cliente bloqueado' : 'Cliente desbloqueado')->send();
    }
}
