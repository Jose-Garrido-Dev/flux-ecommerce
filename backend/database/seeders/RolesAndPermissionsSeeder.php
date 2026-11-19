<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles iniciales del módulo 22 y permisos granulares "modulo.accion".
 * Idempotente. Los roles personalizados se crean luego desde el panel.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const MODULES = [
        'dashboard', 'products', 'categories', 'brands', 'attributes', 'inventory', 'warehouses',
        'orders', 'payments', 'refunds', 'returns', 'shipping', 'customers', 'reviews',
        'coupons', 'promotions', 'marketing', 'content', 'media', 'seo', 'ai',
        'finance', 'reports', 'settings', 'users', 'roles', 'audit', 'system',
    ];

    public const ACTIONS = ['view', 'create', 'update', 'archive', 'delete', 'export', 'import'];

    /** Permisos especiales fuera del CRUD */
    public const SPECIAL = [
        'payments.confirm', 'payments.reconcile', 'refunds.process', 'returns.approve',
        'orders.cancel', 'orders.fulfill', 'shipping.dispatch', 'reviews.moderate',
        'marketing.send', 'content.publish', 'settings.manage', 'ai.configure',
    ];

    public const ROLES = [
        'super-admin' => ['label' => 'Superadministrador técnico', 'grant' => '*'],
        'store-admin' => ['label' => 'Administrador de tienda', 'grant' => ['*' => ['view', 'create', 'update', 'archive', 'export', 'import']], 'deny' => ['system', 'roles'], 'special' => '*'],
        'catalog-manager' => ['label' => 'Gestor de productos', 'grant' => ['products' => '*', 'categories' => '*', 'brands' => '*', 'attributes' => '*', 'media' => '*', 'seo' => '*', 'reviews' => ['view'], 'dashboard' => ['view']], 'special' => ['reviews.moderate']],
        'inventory' => ['label' => 'Inventario', 'grant' => ['inventory' => '*', 'warehouses' => '*', 'products' => ['view'], 'dashboard' => ['view']]],
        'sales' => ['label' => 'Ventas', 'grant' => ['orders' => ['view', 'update', 'export'], 'customers' => ['view'], 'payments' => ['view'], 'returns' => ['view', 'update'], 'dashboard' => ['view']], 'special' => ['orders.cancel', 'returns.approve']],
        'logistics' => ['label' => 'Logística', 'grant' => ['shipping' => '*', 'orders' => ['view'], 'returns' => ['view', 'update'], 'dashboard' => ['view']], 'special' => ['orders.fulfill', 'shipping.dispatch']],
        'marketing' => ['label' => 'Marketing', 'grant' => ['coupons' => '*', 'promotions' => '*', 'marketing' => '*', 'content' => '*', 'media' => '*', 'seo' => '*', 'dashboard' => ['view']], 'special' => ['marketing.send', 'content.publish']],
        'customer-service' => ['label' => 'Atención al cliente', 'grant' => ['customers' => ['view', 'update'], 'orders' => ['view'], 'returns' => ['view', 'create', 'update'], 'reviews' => ['view', 'update'], 'dashboard' => ['view']], 'special' => ['reviews.moderate']],
        'finance' => ['label' => 'Finanzas', 'grant' => ['finance' => '*', 'reports' => '*', 'payments' => ['view', 'export'], 'refunds' => ['view', 'create', 'export'], 'orders' => ['view', 'export'], 'dashboard' => ['view']], 'special' => ['payments.confirm', 'payments.reconcile', 'refunds.process']],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::MODULES)
            ->crossJoin(self::ACTIONS)
            ->map(fn ($pair) => "{$pair[0]}.{$pair[1]}")
            ->merge(self::SPECIAL);

        $all->each(fn (string $name) => Permission::findOrCreate($name, 'web'));

        foreach (self::ROLES as $name => $def) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($this->permissionsFor($def, $all->all()));
        }
    }

    /** @return list<string> */
    private function permissionsFor(array $def, array $all): array
    {
        if ($def['grant'] === '*') {
            return $all;
        }

        $granted = [];
        foreach ($def['grant'] as $module => $actions) {
            $modules = $module === '*' ? array_diff(self::MODULES, $def['deny'] ?? []) : [$module];
            $actions = $actions === '*' ? self::ACTIONS : $actions;
            foreach ($modules as $m) {
                foreach ($actions as $a) {
                    $granted[] = "{$m}.{$a}";
                }
            }
        }

        $special = $def['special'] ?? [];
        $granted = array_merge($granted, $special === '*' ? self::SPECIAL : $special);

        return array_values(array_unique($granted));
    }
}
