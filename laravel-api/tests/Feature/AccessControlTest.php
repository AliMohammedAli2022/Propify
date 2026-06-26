<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_read_access_control_catalog(): void
    {
        $token = $this->createAdminToken();

        $response = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/access-control');

        $response
            ->assertOk()
            ->assertJsonFragment(['key' => 'office_manager'])
            ->assertJsonFragment(['key' => 'users.manage']);
    }

    public function test_user_creation_applies_default_role_permissions_when_none_selected(): void
    {
        $token = $this->createAdminToken();

        $response = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'name' => 'Finance User',
                'email' => 'finance@example.test',
                'password' => 'password',
                'role' => 'accountant',
                'permissions' => [],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('role', 'accountant')
            ->assertJsonPath('permissions.0', 'vouchers.manage')
            ->assertJsonPath('permissions.1', 'reports.view');
    }

    private function createAdminToken(): string
    {
        $token = 'access-control-token';

        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'system_admin',
            'permissions' => ['users.manage'],
            'api_token' => hash('sha256', $token),
        ]);

        return $token;
    }
}
