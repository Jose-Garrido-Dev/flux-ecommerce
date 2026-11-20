<?php

namespace App\Support;

use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Nombres en español para valores internos que se muestran en el panel
 * (modelos en auditoría, acciones, orígenes, roles). Regla del proyecto: nada de inglés en pantalla.
 */
final class Labels
{
    public const MODELS = [
        'Product' => 'Producto', 'ProductVariant' => 'Variante', 'VariantPrice' => 'Precio', 'Category' => 'Categoría',
        'Brand' => 'Marca', 'Attribute' => 'Atributo', 'AttributeValue' => 'Valor de atributo', 'Media' => 'Imagen',
        'ProductAttributeValue' => 'Especificación', 'ProductRelation' => 'Producto relacionado', 'CatalogImport' => 'Importación',
        'TaxClass' => 'Impuesto', 'Region' => 'Región', 'Commune' => 'Comuna', 'Setting' => 'Configuración', 'User' => 'Usuario',
        'Role' => 'Rol', 'ModuleStatus' => 'Estado de módulo', 'Warehouse' => 'Bodega', 'InventoryItem' => 'Inventario de variante',
        'InventoryLevel' => 'Existencia', 'StockMovement' => 'Movimiento de inventario', 'StockTransfer' => 'Transferencia',
        'StockReservation' => 'Reserva de stock', 'Order' => 'Pedido', 'Customer' => 'Cliente', 'ShippingMethod' => 'Método de envío',
        'Payment' => 'Pago', 'Address' => 'Dirección', 'PaymentGateway' => 'Pasarela de pago', 'Refund' => 'Reembolso',
    ];

    public const VERBS = [
        'created' => 'creación', 'updated' => 'modificación', 'deleted' => 'eliminación', 'completed' => 'completada',
        'login' => 'inicio de sesión', 'logout' => 'cierre de sesión', 'failed' => 'fallida',
        'placed' => 'creado', 'payment_confirmed' => 'pago confirmado', 'cancelled' => 'cancelado',
        'blocked' => 'bloqueo', 'unblocked' => 'desbloqueo', 'refunded' => 'reembolso', 'payment_failed' => 'pago rechazado', 'verified' => 'verificación',
    ];

    public const ACTORS = ['user' => 'Usuario', 'system' => 'Sistema', 'provider' => 'Proveedor', 'customer' => 'Cliente'];

    public const RESULTS = ['success' => 'Correcto', 'failure' => 'Fallido', 'failed' => 'Fallido', 'denied' => 'Denegado'];

    public static function model(?string $class): string
    {
        if (! $class) {
            return '—';
        }
        $base = class_basename($class);

        return self::MODELS[$base] ?? $base;
    }

    /** "Product.created" → "Producto: creación" */
    public static function action(?string $action): string
    {
        if (! $action) {
            return '—';
        }
        [$model, $verb] = array_pad(explode('.', $action, 2), 2, null);

        return self::model($model).($verb ? ': '.(self::VERBS[$verb] ?? $verb) : '');
    }

    public static function actor(?string $type): string
    {
        return self::ACTORS[$type] ?? (string) $type;
    }

    public static function result(?string $result): string
    {
        return self::RESULTS[$result] ?? (string) $result;
    }

    public static function role(string $name): string
    {
        return RolesAndPermissionsSeeder::ROLES[$name]['label'] ?? $name;
    }
}
