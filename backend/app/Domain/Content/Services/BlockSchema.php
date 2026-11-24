<?php

namespace App\Domain\Content\Services;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Media;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Esquema de bloques del constructor de páginas (resources/cms/blocks.json, compartido con el storefront).
 * Valida y normaliza los bloques antes de guardar una versión: un bloque inválido nunca llega a la tienda.
 */
final class BlockSchema
{
    public const MAX_BLOCKS = 60;

    private static ?array $schema = null;

    public static function all(): array
    {
        return self::$schema ??= json_decode((string) file_get_contents(resource_path('cms/blocks.json')), true, flags: JSON_THROW_ON_ERROR)['blocks'];
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    /**
     * Acepta la lista del panel ([{type, data}] o con claves uuid) y devuelve [{type, data}] limpia.
     *
     * @throws ValidationException con mensajes en español que indican el bloque y el campo
     */
    public static function validate(array $blocks): array
    {
        $blocks = array_values($blocks);
        if (count($blocks) > self::MAX_BLOCKS) {
            throw ValidationException::withMessages(['blocks' => 'Una página admite hasta '.self::MAX_BLOCKS.' bloques.']);
        }
        $errors = [];
        $clean = [];
        foreach ($blocks as $i => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            $def = is_string($type) ? self::get($type) : null;
            $where = 'Bloque '.($i + 1).($def ? ' ('.$def['label'].')' : '');
            if (! $def) {
                $errors["blocks.$i"] = "$where: tipo de bloque desconocido.";

                continue;
            }
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            [$values, $blockErrors] = self::fields($def['fields'], $data, $where);
            foreach ($blockErrors as $key => $message) {
                $errors["blocks.$i.$key"] = $message;
            }
            $clean[] = ['type' => $type, 'data' => $values];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /** @return array{0: array, 1: array<string, string>} */
    private static function fields(array $fields, array $data, string $where): array
    {
        $values = [];
        $errors = [];
        foreach ($fields as $name => $field) {
            $raw = $data[$name] ?? null;
            $label = '«'.$field['label'].'»';
            $required = ($field['required'] ?? false) || self::requiredIf($field, $data);
            if (self::blank($raw)) {
                if ($required) {
                    $errors[$name] = "$where: $label es obligatorio.";
                } elseif (array_key_exists('default', $field)) {
                    $values[$name] = $field['default'];
                }

                continue;
            }
            try {
                $values[$name] = self::value($field, $raw, $where, $label);
            } catch (BlockFieldError $e) {
                $errors[$name] = $e->getMessage();
            }
        }

        return [$values, $errors];
    }

    private static function requiredIf(array $field, array $data): bool
    {
        foreach ($field['required_if'] ?? [] as $other => $value) {
            if (($data[$other] ?? null) === $value) {
                return true;
            }
        }

        return false;
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || (is_string($value) && trim($value) === '');
    }

    private static function value(array $field, mixed $raw, string $where, string $label): mixed
    {
        $fail = fn (string $why) => throw new BlockFieldError("$where: $label $why");

        switch ($field['type']) {
            case 'text':
            case 'textarea':
            case 'markdown':
                if (! is_string($raw) && ! is_numeric($raw)) {
                    $fail('debe ser texto.');
                }
                $text = trim(str_replace("\r\n", "\n", (string) $raw));
                if ($field['type'] === 'text') {
                    $text = preg_replace('/\s+/u', ' ', $text);
                }
                if (isset($field['max']) && mb_strlen($text) > $field['max']) {
                    $fail("admite hasta {$field['max']} caracteres.");
                }

                return $text;
            case 'link':
                $link = trim((string) $raw);
                if (mb_strlen($link) > 500 || ! self::validLink($link)) {
                    $fail('debe ser una ruta de la tienda que empiece con / (por ejemplo /catalogo) o una dirección https://.');
                }

                return $link;
            case 'video':
                $url = trim((string) $raw);
                if (! VideoEmbed::parse($url)) {
                    $fail('debe ser un enlace de YouTube o Vimeo.');
                }

                return $url;
            case 'select':
                if (! is_string($raw) || ! array_key_exists($raw, $field['options'])) {
                    $fail('tiene una opción no válida.');
                }

                return $raw;
            case 'number':
                if (! is_numeric($raw) || (int) $raw != $raw) {
                    $fail('debe ser un número entero.');
                }
                $n = (int) $raw;
                if ((isset($field['min']) && $n < $field['min']) || (isset($field['max']) && $n > $field['max'])) {
                    $fail("debe estar entre {$field['min']} y {$field['max']}.");
                }

                return $n;
            case 'datetime':
                try {
                    return Carbon::parse((string) $raw)->toIso8601String();
                } catch (Throwable) {
                    $fail('no es una fecha válida.');
                }
            case 'media':
                return self::existing(Media::class, $raw, $fail, 'no existe en la biblioteca.');
            case 'category':
                return self::existing(Category::class, $raw, $fail, 'no existe.');
            case 'brand':
                return self::existing(Brand::class, $raw, $fail, 'no existe.');
            case 'categories':
            case 'brands':
            case 'products':
                $class = ['categories' => Category::class, 'brands' => Brand::class, 'products' => Product::class][$field['type']];
                $ids = array_values(array_unique(array_map('intval', array_filter((array) $raw, 'is_numeric'))));
                if (isset($field['max_items']) && count($ids) > $field['max_items']) {
                    $fail("admite hasta {$field['max_items']}.");
                }
                if ($ids !== [] && $class::query()->whereIn('id', $ids)->count() !== count($ids)) {
                    $fail('incluye elementos que ya no existen.');
                }

                return $ids;
            case 'list':
                if (! is_array($raw)) {
                    $fail('no es una lista.');
                }
                $items = array_values($raw);
                if (isset($field['max_items']) && count($items) > $field['max_items']) {
                    $fail("admite hasta {$field['max_items']} elementos.");
                }
                $out = [];
                foreach ($items as $n => $item) {
                    [$values, $errors] = self::fields($field['fields'], is_array($item) ? $item : [], "$where, $label ".($n + 1));
                    if ($errors !== []) {
                        throw new BlockFieldError(reset($errors));
                    }
                    $out[] = $values;
                }

                return $out;
        }
        $fail('tiene un tipo de campo desconocido.');
    }

    private static function existing(string $class, mixed $raw, callable $fail, string $why): int
    {
        if (! is_numeric($raw) || ! $class::query()->whereKey((int) $raw)->exists()) {
            $fail($why);
        }

        return (int) $raw;
    }

    public static function validLink(string $link): bool
    {
        if (str_starts_with($link, '/')) {
            return ! str_starts_with($link, '//') && ! str_contains($link, '\\') && preg_match('/^[^\s<>"]+$/u', $link) === 1;
        }

        return preg_match('#^https?://[^\s<>"]+$#iu', $link) === 1 && filter_var($link, FILTER_VALIDATE_URL) !== false;
    }

    /** Ids de imágenes de la biblioteca que usan los bloques (para avisar antes de borrar una). @return list<int> */
    public static function mediaIds(array $blocks): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            foreach (self::get($block['type'] ?? '')['fields'] ?? [] as $name => $field) {
                if ($field['type'] === 'media' && is_numeric($block['data'][$name] ?? null)) {
                    $ids[] = (int) $block['data'][$name];
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
