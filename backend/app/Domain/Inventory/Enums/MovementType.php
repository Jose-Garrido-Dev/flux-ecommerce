<?php

namespace App\Domain\Inventory\Enums;

/** Tipos de movimiento de inventario (módulo 6.2). El signo lo define el tipo, no quien registra. */
enum MovementType: string
{
    case PurchaseIn = 'purchase_in';
    case ManualIn = 'manual_in';
    case ReturnIn = 'return_in';
    case AdjustIn = 'adjust_in';
    case SaleOut = 'sale_out';
    case ManualOut = 'manual_out';
    case AdjustOut = 'adjust_out';
    case Shrinkage = 'shrinkage';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Reservation = 'reservation';
    case ReservationRelease = 'reservation_release';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseIn => 'Entrada por compra',
            self::ManualIn => 'Entrada manual',
            self::ReturnIn => 'Devolución',
            self::AdjustIn => 'Ajuste positivo',
            self::SaleOut => 'Salida por venta',
            self::ManualOut => 'Salida manual',
            self::AdjustOut => 'Ajuste negativo',
            self::Shrinkage => 'Merma',
            self::TransferIn => 'Transferencia (entrada)',
            self::TransferOut => 'Transferencia (salida)',
            self::Reservation => 'Reserva',
            self::ReservationRelease => 'Liberación de reserva',
            self::Reversal => 'Contramovimiento',
        };
    }

    /** +1 suma stock físico, -1 resta, 0 no lo cambia. */
    public function sign(): int
    {
        return match ($this) {
            self::PurchaseIn, self::ManualIn, self::ReturnIn, self::AdjustIn, self::TransferIn => 1,
            self::SaleOut, self::ManualOut, self::AdjustOut, self::Shrinkage, self::TransferOut => -1,
            self::Reservation, self::ReservationRelease, self::Reversal => 0,
        };
    }

    /** Tipos que una persona puede registrar a mano desde el panel. */
    public static function manual(): array
    {
        return collect([self::PurchaseIn, self::ManualIn, self::ReturnIn, self::ManualOut, self::Shrinkage])
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }

    public function color(): string
    {
        return match ($this->sign()) {
            1 => 'success',
            -1 => 'danger',
            default => 'gray',
        };
    }
}
