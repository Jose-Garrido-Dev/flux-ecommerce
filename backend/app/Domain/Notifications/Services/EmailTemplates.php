<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Models\EmailTemplate;
use App\Domain\System\Services\SettingsRepository;

/**
 * Arma el asunto, el título y el texto de un correo a partir de su plantilla.
 * Los valores de las variables se escapan: un nombre de cliente no puede inyectar HTML ni formato.
 */
final class EmailTemplates
{
    /** @return array{subject: string, heading: string, intro: string, enabled: bool} */
    public static function render(string $key, array $vars): array
    {
        $def = EmailTemplateRegistry::get($key);
        $row = EmailTemplate::query()->where('key', $key)->first();
        $vars = ['tienda' => app(SettingsRepository::class)->get('business', 'store_name', config('app.name'))] + $vars;

        return [
            'subject' => self::fill($row->subject ?? $def['subject'], $vars, markdown: false),
            'heading' => self::fill($row->heading ?? $def['heading'], $vars, markdown: true),
            'intro' => self::fill($row->intro ?? $def['intro'], $vars, markdown: true),
            'enabled' => $def['required'] || ($row->enabled ?? true),
        ];
    }

    public static function enabled(string $key): bool
    {
        $def = EmailTemplateRegistry::get($key);

        return $def['required'] || (EmailTemplate::query()->where('key', $key)->value('enabled') ?? true);
    }

    /** Variables desconocidas quedan como están para que se note el error al previsualizar. */
    public static function fill(string $text, array $vars, bool $markdown): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars, $markdown) {
            if (! array_key_exists($m[1], $vars)) {
                return $m[0];
            }
            $value = trim(preg_replace('/\s+/', ' ', (string) $vars[$m[1]]));

            return $markdown ? self::escapeMarkdown($value) : $value;
        }, $text);
    }

    public static function escapeMarkdown(string $value): string
    {
        return e(addcslashes($value, '\\`*_[]#|~'), false);
    }

    /** Variables con valores de ejemplo, para la vista previa del panel. */
    public static function sample(string $key): array
    {
        $samples = [
            'nombre' => 'Ana', 'pedido' => '1001', 'total' => '$25.980', 'monto' => '$12.990', 'motivo' => 'El plazo de pago venció.',
            'courier' => 'Starken', 'seguimiento' => '123456789', 'devolucion' => '1001-D1',
            'resolucion' => 'Hicimos un reembolso de $12.990 a tu tarjeta.',
        ];

        return array_intersect_key($samples, EmailTemplateRegistry::get($key)['variables']);
    }
}
