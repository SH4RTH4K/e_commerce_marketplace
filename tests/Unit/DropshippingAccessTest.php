<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class DropshippingAccessTest extends TestCase
{
    public function test_dropshipping_permission_is_registered(): void
    {
        $this->assertArrayHasKey('dropshipping', User::PERMISSIONS);
        $this->assertArrayHasKey('dropshipping.manage', User::PERMISSIONS['dropshipping']['items']);
    }

    public function test_only_users_with_the_permission_or_super_admin_status_are_authorized(): void
    {
        $authorized = new User();
        $authorized->role = 'manager';
        $authorized->permissions = ['dropshipping.manage'];

        $unauthorized = new User();
        $unauthorized->role = 'manager';
        $unauthorized->permissions = ['catalog.manage'];

        $superAdmin = new User();
        $superAdmin->role = 'admin';

        $this->assertTrue($authorized->hasPermission('dropshipping.manage'));
        $this->assertFalse($unauthorized->hasPermission('dropshipping.manage'));
        $this->assertTrue($superAdmin->hasPermission('dropshipping.manage'));
    }
}
