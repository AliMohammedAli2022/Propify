<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackupImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_import_backup_data(): void
    {
        $token = 'test-import-token';

        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'system_admin',
            'permissions' => [],
            'api_token' => hash('sha256', $token),
        ]);

        $backup = [
            'meta' => ['app' => 'Propify', 'version' => 1],
            'settings' => [
                'companyName' => 'Imported Office',
                'companyPhone' => '07700000000',
                'companyEmail' => 'office@example.test',
                'companyAddress' => 'Baghdad',
                'defaultCurrency' => 'IQD',
                'defaultCommissionRate' => 3.5,
            ],
            'clients' => [[
                'name' => 'Imported Client',
                'role' => 'buyer',
                'phone' => '07712345678',
                'nationalId' => 'ABC123456789',
                'stage' => 'lead',
                'source' => 'backup',
            ]],
            'properties' => [[
                'code' => 'PR-TST-001',
                'type' => 'house',
                'mode' => 'sale',
                'province' => 'Baghdad',
                'area' => 'Karrada',
                'space' => 250,
                'rooms' => 4,
                'price' => 125000,
                'status' => 'available',
                'owner' => 'Owner Name',
                'negotiable' => true,
            ]],
        ];

        $response = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/backup/import', [
                'backup' => UploadedFile::fake()->createWithContent('backup.json', json_encode($backup)),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('summary.settings', 1)
            ->assertJsonPath('summary.clients', 1)
            ->assertJsonPath('summary.properties', 1);

        $this->assertDatabaseHas('app_settings', ['company_name' => 'Imported Office']);
        $this->assertDatabaseHas('clients', ['phone' => '07712345678', 'name' => 'Imported Client']);
        $this->assertDatabaseHas('properties', ['code' => 'PR-TST-001', 'area' => 'Karrada']);
    }
}
