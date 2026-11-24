<?php

namespace App\Domain\Content\Services;

/**
 * Temas incluidos. Cada uno es un paquete de tokens que la tienda aplica como variables CSS:
 * colores para modo claro y oscuro, tipografía de títulos, radios y mayúsculas en títulos.
 * Todos cumplen contraste WCAG AA (lo comprueba una prueba).
 */
final class ThemePresets
{
    public const COLOR_KEYS = [
        'bg', 'surface', 'subtle', 'fg', 'muted', 'accent', 'accent-fg', 'sale', 'ok', 'hot', 'border', 'focus',
        'field', 'field-fg', 'field-muted', 'ink', 'ink-2', 'ink-fg', 'ink-muted',
    ];

    public const DARK_KEYS = ['bg', 'surface', 'subtle', 'fg', 'muted', 'accent', 'accent-fg', 'sale', 'ok', 'border', 'focus', 'ink', 'ink-2'];

    public const FONTS = ['brand' => 'Bricolage (la de la tienda)', 'serif' => 'Serif clásica', 'system' => 'Del sistema (neutra)'];

    public static function all(): array
    {
        return [
            'lapislazuli' => [
                'name' => 'Lapislázuli', 'description' => 'El tema original de Flux: azul lapislázuli, tipografía angosta y pesada.',
                'tokens' => self::tokens(
                    ['bg' => '#f4f5f1', 'surface' => '#ffffff', 'subtle' => '#e8eae4', 'fg' => '#0c1433', 'muted' => '#4f5672', 'accent' => '#1f3fd0', 'accent-fg' => '#ffffff', 'sale' => '#c2410c', 'ok' => '#15803d', 'hot' => '#ff8a4c', 'border' => '#d9dcd4', 'focus' => '#1f3fd0', 'field' => '#1f3fd0', 'field-fg' => '#ffffff', 'field-muted' => '#d3daf8', 'ink' => '#0c1433', 'ink-2' => '#172150', 'ink-fg' => '#f1f3fa', 'ink-muted' => '#aab2d0'],
                    ['bg' => '#080d22', 'surface' => '#0f1631', 'subtle' => '#161e3f', 'fg' => '#eef0f8', 'muted' => '#a3aac4', 'accent' => '#8ea2ff', 'accent-fg' => '#080d22', 'sale' => '#ff9a63', 'ok' => '#4ade80', 'border' => '#232c52', 'focus' => '#8ea2ff', 'ink' => '#050918', 'ink-2' => '#10183a'],
                    'brand', [4, 8], false,
                ),
            ],
            'minimalista' => [
                'name' => 'Minimalista', 'description' => 'Blanco y negro, esquinas rectas y mucho aire. Deja todo el protagonismo a las fotos.',
                'tokens' => self::tokens(
                    ['bg' => '#fafafa', 'surface' => '#ffffff', 'subtle' => '#f0f0f0', 'fg' => '#111111', 'muted' => '#5c5c5c', 'accent' => '#111111', 'accent-fg' => '#ffffff', 'sale' => '#b42318', 'ok' => '#15803d', 'hot' => '#ff6b4a', 'border' => '#e4e4e4', 'focus' => '#111111', 'field' => '#ececec', 'field-fg' => '#111111', 'field-muted' => '#4a4a4a', 'ink' => '#111111', 'ink-2' => '#1d1d1d', 'ink-fg' => '#f5f5f5', 'ink-muted' => '#a8a8a8'],
                    ['bg' => '#0b0b0b', 'surface' => '#141414', 'subtle' => '#1c1c1c', 'fg' => '#f2f2f2', 'muted' => '#a3a3a3', 'accent' => '#f2f2f2', 'accent-fg' => '#0b0b0b', 'sale' => '#ff8a73', 'ok' => '#4ade80', 'border' => '#2a2a2a', 'focus' => '#f2f2f2', 'ink' => '#050505', 'ink-2' => '#161616'],
                    'system', [2, 4], false,
                ),
            ],
            'fashion' => [
                'name' => 'Fashion', 'description' => 'Tonos crema y vino, títulos con serif y botones en píldora. Para moda y accesorios.',
                'tokens' => self::tokens(
                    ['bg' => '#f7f2ee', 'surface' => '#fffdfb', 'subtle' => '#efe6df', 'fg' => '#1f1a17', 'muted' => '#6b5f58', 'accent' => '#8a3b4b', 'accent-fg' => '#ffffff', 'sale' => '#b4232c', 'ok' => '#2f7a4b', 'hot' => '#f08a8a', 'border' => '#e3d7ce', 'focus' => '#8a3b4b', 'field' => '#e9d8cf', 'field-fg' => '#1f1a17', 'field-muted' => '#5c4d45', 'ink' => '#241c19', 'ink-2' => '#352a26', 'ink-fg' => '#f7f0eb', 'ink-muted' => '#c2b2a8'],
                    ['bg' => '#14100e', 'surface' => '#1d1715', 'subtle' => '#29211e', 'fg' => '#f4ece7', 'muted' => '#b9aaa1', 'accent' => '#e7a3b0', 'accent-fg' => '#14100e', 'sale' => '#ff9a9a', 'ok' => '#6ee7a0', 'border' => '#3a2f2b', 'focus' => '#e7a3b0', 'ink' => '#0d0a09', 'ink-2' => '#221b18'],
                    'serif', [0, 0], false,
                ),
            ],
            'streetwear' => [
                'name' => 'Streetwear', 'description' => 'Alto contraste, negro con naranja intenso, títulos en mayúsculas y esquinas rectas.',
                'tokens' => self::tokens(
                    ['bg' => '#f2f2ee', 'surface' => '#ffffff', 'subtle' => '#e3e3dc', 'fg' => '#0a0a0a', 'muted' => '#4d4d4d', 'accent' => '#c92a00', 'accent-fg' => '#ffffff', 'sale' => '#be123c', 'ok' => '#15803d', 'hot' => '#ff4d00', 'border' => '#cfcfc8', 'focus' => '#0a0a0a', 'field' => '#0a0a0a', 'field-fg' => '#f2f2ee', 'field-muted' => '#b5b5ad', 'ink' => '#0a0a0a', 'ink-2' => '#1a1a1a', 'ink-fg' => '#f2f2ee', 'ink-muted' => '#a3a39b'],
                    ['bg' => '#0a0a0a', 'surface' => '#141414', 'subtle' => '#1e1e1e', 'fg' => '#f2f2ee', 'muted' => '#a8a8a0', 'accent' => '#ff6a3d', 'accent-fg' => '#0a0a0a', 'sale' => '#ff6b8a', 'ok' => '#4ade80', 'border' => '#2b2b2b', 'focus' => '#ff6a3d', 'ink' => '#000000', 'ink-2' => '#141414'],
                    'brand', [0, 0], true,
                ),
            ],
            'tecnologia' => [
                'name' => 'Tecnología', 'description' => 'Azul eléctrico sobre gris frío, secciones oscuras y esquinas suaves.',
                'tokens' => self::tokens(
                    ['bg' => '#f3f6f9', 'surface' => '#ffffff', 'subtle' => '#e6ecf2', 'fg' => '#0b1220', 'muted' => '#4a5568', 'accent' => '#0b63ce', 'accent-fg' => '#ffffff', 'sale' => '#c2410c', 'ok' => '#15803d', 'hot' => '#22d3ee', 'border' => '#d5dde6', 'focus' => '#0b63ce', 'field' => '#0b1220', 'field-fg' => '#e8f1ff', 'field-muted' => '#9fb3cc', 'ink' => '#0b1220', 'ink-2' => '#142033', 'ink-fg' => '#e8eef7', 'ink-muted' => '#9aa8bd'],
                    ['bg' => '#070b14', 'surface' => '#0e1522', 'subtle' => '#152033', 'fg' => '#e6edf7', 'muted' => '#94a3b8', 'accent' => '#5aa8ff', 'accent-fg' => '#070b14', 'sale' => '#ff9a63', 'ok' => '#4ade80', 'border' => '#1f2b40', 'focus' => '#5aa8ff', 'ink' => '#04070d', 'ink-2' => '#0e1626'],
                    'system', [6, 12], false,
                ),
            ],
            'lujo' => [
                'name' => 'Lujo', 'description' => 'Marfil, negro y dorado envejecido, títulos con serif y líneas finas.',
                'tokens' => self::tokens(
                    ['bg' => '#f6f3ec', 'surface' => '#fffdf8', 'subtle' => '#ece6d9', 'fg' => '#16130e', 'muted' => '#5f574a', 'accent' => '#7d5f26', 'accent-fg' => '#ffffff', 'sale' => '#9f1d1d', 'ok' => '#2f6b3f', 'hot' => '#d4b26a', 'border' => '#ddd4c3', 'focus' => '#7d5f26', 'field' => '#16130e', 'field-fg' => '#f6f0e2', 'field-muted' => '#b8ad97', 'ink' => '#16130e', 'ink-2' => '#221d16', 'ink-fg' => '#f4efe4', 'ink-muted' => '#b3a993'],
                    ['bg' => '#0e0c09', 'surface' => '#17140f', 'subtle' => '#221d16', 'fg' => '#f2ecdf', 'muted' => '#b5ab98', 'accent' => '#d4b26a', 'accent-fg' => '#0e0c09', 'sale' => '#f08a8a', 'ok' => '#6ee7a0', 'border' => '#332c21', 'focus' => '#d4b26a', 'ink' => '#080705', 'ink-2' => '#1a1611'],
                    'serif', [0, 0], false,
                ),
            ],
            'hogar' => [
                'name' => 'Hogar', 'description' => 'Lino cálido y verde salvia, esquinas redondeadas. Para deco, cocina y jardín.',
                'tokens' => self::tokens(
                    ['bg' => '#f5f1e8', 'surface' => '#fffcf5', 'subtle' => '#ebe4d4', 'fg' => '#2a241b', 'muted' => '#6a604f', 'accent' => '#3f6b4f', 'accent-fg' => '#ffffff', 'sale' => '#b4501c', 'ok' => '#2f7a4b', 'hot' => '#e8a76b', 'border' => '#ddd3bf', 'focus' => '#3f6b4f', 'field' => '#dfe8dc', 'field-fg' => '#22301f', 'field-muted' => '#4d5e48', 'ink' => '#2a241b', 'ink-2' => '#3a3226', 'ink-fg' => '#f7f2e8', 'ink-muted' => '#c4b8a2'],
                    ['bg' => '#12100c', 'surface' => '#1b1813', 'subtle' => '#26221b', 'fg' => '#f1ebdf', 'muted' => '#b8ad99', 'accent' => '#8fc29f', 'accent-fg' => '#12100c', 'sale' => '#ffab7a', 'ok' => '#6ee7a0', 'border' => '#363026', 'focus' => '#8fc29f', 'ink' => '#0b0a07', 'ink-2' => '#1f1b15'],
                    'brand', [10, 16], false,
                ),
            ],
        ];
    }

    private static function tokens(array $light, array $dark, string $font, array $radius, bool $uppercase): array
    {
        return ['light' => $light, 'dark' => $dark, 'font_display' => $font, 'radius_sm' => $radius[0], 'radius' => $radius[1], 'uppercase_headings' => $uppercase];
    }

    /** Relación de contraste WCAG entre dos colores #rrggbb. */
    public static function contrast(string $a, string $b): float
    {
        $l = function (string $hex): float {
            $c = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
            $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$l($a), $l($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    /**
     * Pares que deben ser legibles (texto normal: 4,5). @return list<array{0: string, 1: string, 2: string}>
     * [color del texto, color del fondo, descripción]
     */
    public static function pairs(bool $dark): array
    {
        $pairs = [['fg', 'bg', 'texto sobre el fondo'], ['muted', 'bg', 'texto secundario'], ['accent', 'bg', 'enlaces y destacados'], ['accent-fg', 'accent', 'texto de los botones'], ['sale', 'bg', 'precios de oferta'], ['fg', 'surface', 'texto sobre tarjetas']];
        if (! $dark) {
            $pairs[] = ['field-fg', 'field', 'texto sobre el campo de color de la portada'];
            $pairs[] = ['ink-fg', 'ink', 'texto en secciones oscuras'];
        }

        return $pairs;
    }

    /** Problemas de contraste de un juego de tokens. @return list<string> */
    public static function contrastProblems(array $tokens): array
    {
        $problems = [];
        foreach (['light' => false, 'dark' => true] as $mode => $dark) {
            $colors = $dark ? [...$tokens['light'], ...$tokens['dark']] : $tokens['light'];
            foreach (self::pairs($dark) as [$text, $bg, $what]) {
                $ratio = self::contrast($colors[$text], $colors[$bg]);
                if ($ratio < 4.5) {
                    $problems[] = sprintf('%s (modo %s): contraste %.1f, mínimo 4,5.', ucfirst($what), $dark ? 'oscuro' : 'claro', $ratio);
                }
            }
        }

        return $problems;
    }
}
