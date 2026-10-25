<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Staff roles from docs/MASTER_PLAN.md §6 with sensible default permissions.
 * Run again after `shield:generate` so new resources reach the manager role.
 */
class RoleSeeder extends Seeder
{
    public const ROLES = [
        'super_admin',
        'manager',
        'order_manager',
        'warehouse',
        'catalog_manager',
        'marketer',
        'content_editor',
    ];

    public const LABELS = [
        'super_admin' => 'সুপার অ্যাডমিন (সব কিছু)',
        'manager' => 'ম্যানেজার (স্টাফ আর রোল ছাড়া সব)',
        'order_manager' => 'অর্ডার ম্যানেজার',
        'warehouse' => 'ওয়্যারহাউস / প্যাকিং',
        'catalog_manager' => 'ক্যাটালগ ম্যানেজার',
        'marketer' => 'মার্কেটার',
        'content_editor' => 'কনটেন্ট এডিটর',
    ];

    private const EDIT = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Reorder', 'Replicate', 'Restore', 'RestoreAny'];

    private const READ = ['ViewAny', 'View'];

    /**
     * Default permissions per role (super_admin passes every check via Gate::before; manager gets
     * everything except staff/role management and permanent deletes). Seeding only adds: anything
     * granted later from the Shield "Roles" screen stays.
     *
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        $model = fn (string $model, array $abilities) => array_map(fn (string $a) => "{$a}:{$model}", $abilities);
        $pages = fn (string ...$pages) => array_map(fn (string $p) => "View:{$p}", $pages);

        return [
            'order_manager' => [
                ...$model('Order', ['ViewAny', 'View', 'Create', 'Update']),
                ...$model('SmsLog', ['ViewAny', 'View', 'Update']),
                ...$model('DeliveryZone', self::READ),
                ...$model('Thana', self::READ),
                ...$model('Product', self::READ),
                ...$model('IncompleteOrder', ['ViewAny', 'View', 'Update']),
                ...$model('Customer', ['ViewAny', 'View', 'Create', 'Update']),
                ...$model('Review', self::READ),
                ...$model('ProductQuestion', ['ViewAny', 'View', 'Update']),
                ...$pages('ActionNeededWidget', 'OrderStatusChartWidget', 'LowStockWidget'),
            ],
            'warehouse' => [
                ...$model('Order', ['ViewAny', 'View', 'Update']),
                ...$model('Product', ['ViewAny', 'View', 'Update']),
                ...$model('StockMovement', ['ViewAny', 'View']),
                ...$model('PurchaseOrder', ['ViewAny', 'View', 'Update']),
                ...$model('Supplier', self::READ),
                ...$pages('ActionNeededWidget', 'LowStockWidget'),
            ],
            'catalog_manager' => [
                ...$model('Product', self::EDIT),
                ...$model('Category', self::EDIT),
                ...$model('Brand', self::EDIT),
                ...$model('Attribute', self::EDIT),
                ...$model('Review', self::EDIT),
                ...$model('ProductQuestion', self::EDIT),
                ...$model('StockMovement', ['ViewAny', 'View']),
                ...$model('PurchaseOrder', self::EDIT),
                ...$model('Supplier', self::EDIT),
                ...$model('SearchQuery', ['ViewAny', 'View']),
                ...$pages('ManageSearchSettings', 'ProductFeeds', 'ActionNeededWidget', 'TopProductsWidget', 'LowStockWidget'),
            ],
            'marketer' => [
                ...$model('Banner', self::EDIT),
                ...$model('Coupon', self::EDIT),
                ...$model('FlashSale', self::EDIT),
                ...$model('Bundle', self::EDIT),
                ...$model('OrderBump', self::EDIT),
                ...$model('LandingPage', self::EDIT),
                ...$model('SearchQuery', ['ViewAny', 'View']),
                ...$model('Product', self::READ),
                ...$model('Order', self::READ),
                ...$pages('ManageHomepage', 'ManageSeasonalSettings', 'ManageTrackingSettings', 'ProductFeeds', 'Rewards', 'Reports'),
                ...$pages('ActionNeededWidget', 'SalesStatsWidget', 'SalesChartWidget', 'OrderStatusChartWidget', 'TopProductsWidget'),
            ],
            'content_editor' => [
                ...$model('Page', self::EDIT),
                ...$model('MenuItem', self::EDIT),
                ...$model('Banner', self::EDIT),
                ...$model('Review', self::EDIT),
                ...$model('ProductQuestion', self::EDIT),
                ...$pages('ManageHomepage', 'ManageSeasonalSettings', 'ManageSiteSettings', 'ActionNeededWidget'),
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach (self::defaults() as $role => $permissions) {
            Role::findByName($role, 'web')->givePermissionTo(
                array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $permissions),
            );
        }

        // Manager: every generated permission except staff/role management and permanent deletes.
        Role::findByName('manager', 'web')->givePermissionTo(
            Permission::query()
                ->where('name', 'not like', '%:Role')
                ->where('name', 'not like', '%:User')
                ->where('name', 'not like', 'ForceDelete%')
                ->get(),
        );

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set; skipping super admin.');

            return;
        }

        // .env is the source of truth for this account: re-seeding resets its name and password.
        $admin = User::firstOrNew(['email' => $email]);
        $admin->fill(['name' => env('ADMIN_NAME', 'Super Admin'), 'password' => $password])->save();
        $admin->assignRole('super_admin');
    }
}
