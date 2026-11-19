<?php

namespace App\Domain\System\Enums;

enum ModuleState: string
{
    case Pending = 'pending';
    case InDevelopment = 'in_development';
    case Implemented = 'implemented';
    case Testing = 'testing';
    case Validated = 'validated';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InDevelopment => 'En desarrollo',
            self::Implemented => 'Implementado',
            self::Testing => 'En pruebas',
            self::Validated => 'Validado',
            self::Blocked => 'Bloqueado',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
