<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'permissions', 'phone', 'address', 'city', 'postal_code', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STAFF_ROLES = ['admin', 'manager', 'support', 'inventory', 'staff'];

    public const PERMISSIONS = [
        'products' => [
            'label' => 'Products & Inventory',
            'items' => [
                'products.view'   => 'View Products & Inventory',
                'products.create' => 'Add & Edit Products',
                'products.delete' => 'Delete Products',
            ],
        ],
        'orders' => [
            'label' => 'Orders & Sales',
            'items' => [
                'orders.view'   => 'View Orders & Invoices',
                'orders.manage' => 'Update Status & Send to Courier',
                'orders.delete' => 'Delete & Reject Orders',
            ],
        ],
        'crm' => [
            'label' => 'CRM & Reviews',
            'items' => [
                'customers.manage' => 'Customer CRM & Abandoned Carts',
                'reviews.manage'   => 'Approve & Delete Reviews',
            ],
        ],
        'marketing' => [
            'label' => 'Marketing & Catalog',
            'items' => [
                'catalog.manage'   => 'Categories, Banners & Features',
                'marketing.manage' => 'Coupons, Flash Sale & Landing Pages',
                'messages.manage'  => 'Contact Messages',
            ],
        ],
        'dropshipping' => [
            'label' => 'Dropshipping',
            'items' => [
                'dropshipping.manage' => 'Manage Suppliers & Product Sync',
            ],
        ],
        'system' => [
            'label' => 'System & Management',
            'items' => [
                'media.manage'       => 'Media Manager',
                'fraud_guard.manage' => 'Fraud Guard & Blocked IPs',
                'admins.manage'      => 'Manage Staff & Roles',
                'settings.manage'    => 'Store Settings & Payments',
            ],
        ],
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'permissions'       => 'array',
            'is_active'         => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        // Older cPanel databases may contain values such as "Admin" or
        // "admin " after a manual import. Treat those as the same role as
        // the canonical value used by the application.
        return $this->normalisedRole() === 'admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->normalisedRole(), self::STAFF_ROLES, true);
    }

    public function isCustomer(): bool
    {
        return ! $this->isAdmin();
    }

    public function isActive(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? true);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $perms = $this->permissions ?? [];

        return is_array($perms) && in_array($permission, $perms, true);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function normalisedRole(): string
    {
        return strtolower(trim((string) $this->role));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
