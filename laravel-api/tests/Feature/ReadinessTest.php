<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_requires_authentication(): void
    {
        $this->get('/api/readiness')->assertUnauthorized();
    }

    public function test_system_admin_can_read_deployment_readiness(): void
    {
        $token = 'readiness-token';

        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'system_admin',
            'permissions' => ['settings.update'],
            'api_token' => hash('sha256', $token),
        ]);

        $response = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/readiness');

        $this->assertTrue(in_array($response->getStatusCode(), [200, 207], true));

        $response->assertJsonStructure([
                'ok',
                'environment',
                'checkedAt',
                'counts' => ['users', 'properties', 'clients', 'contracts', 'vouchers'],
                'checks' => [
                    'database' => ['label', 'ok', 'message'],
                    'storage' => ['label', 'ok', 'message'],
                    'appKey' => ['label', 'ok', 'message'],
                ],
            ]);
    }
}
