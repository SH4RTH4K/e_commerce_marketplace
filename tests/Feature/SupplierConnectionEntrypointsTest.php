<?php

namespace Tests\Feature;

use App\Models\DropshipSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierConnectionEntrypointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_pages_expose_connection_access_only_when_feature_and_permission_are_enabled(): void
    {
        $this->withoutVite();
        $supplier = DropshipSupplier::create([
            'key' => 'manual-dropship-test', 'name' => 'Test Supplier', 'supplier_type' => 'dropshipping',
            'driver_key' => 'manual', 'is_active' => true,
        ]);
        $user = User::factory()->create(['role' => 'staff', 'permissions' => ['suppliers.manage', 'dropshipping.manage']]);
        $this->actingAs($user);

        foreach ([[true, true], [false, false]] as [$enabled, $expected]) {
            config(['dropshipping.enabled' => $enabled]);
            foreach (['/admin/suppliers', '/admin/suppliers/create', '/admin/suppliers/'.$supplier->id, '/admin/suppliers/'.$supplier->id.'/edit'] as $url) {
                $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManageDropshipping', $expected));
            }
        }

        config(['dropshipping.enabled' => true]);
        $user->forceFill(['permissions' => ['suppliers.manage']])->save();
        foreach (['/admin/suppliers', '/admin/suppliers/create', '/admin/suppliers/'.$supplier->id, '/admin/suppliers/'.$supplier->id.'/edit'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManageDropshipping', false));
        }
    }
}
