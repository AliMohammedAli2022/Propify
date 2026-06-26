<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\LedgerEntry;
use App\Models\Property;
use App\Models\Voucher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AppSetting::query()->create([
            'company_name' => 'Propify',
            'company_phone' => '07700000000',
            'company_email' => 'office@propify.local',
            'company_address' => 'بغداد - العراق',
            'default_currency' => 'دينار',
            'default_commission_rate' => 2,
        ]);

        \App\Models\User::factory()->create([
            'name' => 'علي محمد',
            'email' => 'admin@propify.local',
            'password' => Hash::make('password'),
            'role' => 'system_admin',
            'permissions' => [
                'properties.create',
                'properties.update',
                'properties.approve',
                'clients.manage',
                'contracts.create',
                'contracts.print',
                'vouchers.manage',
                'reports.view',
                'settings.update',
                'users.manage',
            ],
        ]);

        Client::query()->insert([
            ['name' => 'أحمد علي', 'role' => 'مشتري', 'phone' => '07701234567', 'national_id' => 'A12345678901', 'stage' => 'تفاوض', 'source' => 'إعلان ممول', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'محمد حسن', 'role' => 'مؤجر', 'phone' => '07801234567', 'national_id' => 'B12345678901', 'stage' => 'عقد نشط', 'source' => 'توصية', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'سارة كريم', 'role' => 'مالك عقار', 'phone' => '07501234567', 'national_id' => 'C12345678901', 'stage' => 'مراجعة عقار', 'source' => 'الموقع', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Property::query()->insert([
            ['code' => 'PR-2026-000145', 'type' => 'دار سكنية', 'mode' => 'بيع', 'province' => 'بغداد', 'area' => 'المنصور', 'space' => 250, 'rooms' => 4, 'price' => 120000000, 'status' => 'متاح', 'owner' => 'أحمد علي', 'negotiable' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'PR-2026-000146', 'type' => 'شقة', 'mode' => 'إيجار', 'province' => 'بغداد', 'area' => 'زيونة', 'space' => 140, 'rooms' => 3, 'price' => 700000, 'status' => 'محجوز', 'owner' => 'محمد حسن', 'negotiable' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'PR-2026-000147', 'type' => 'أرض', 'mode' => 'بيع بالتقسيط', 'province' => 'بغداد', 'area' => 'الجادرية', 'space' => 300, 'rooms' => 0, 'price' => 150000000, 'status' => 'قيد المراجعة', 'owner' => 'سارة كريم', 'negotiable' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Contract::query()->insert([
            ['code' => 'CT-2026-000044', 'property_code' => 'PR-2026-000145', 'client' => 'أحمد علي', 'kind' => 'بيع نقدي', 'total' => 120000000, 'paid' => 120000000, 'due' => 0, 'commission' => 2400000, 'status' => 'مكتمل', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CT-2026-000045', 'property_code' => 'PR-2026-000147', 'client' => 'سارة كريم', 'kind' => 'تقسيط', 'total' => 150000000, 'paid' => 30000000, 'due' => 120000000, 'commission' => 3000000, 'status' => 'نشط', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CT-2026-000046', 'property_code' => 'PR-2026-000146', 'client' => 'محمد حسن', 'kind' => 'إيجار', 'total' => 8400000, 'paid' => 1400000, 'due' => 7000000, 'commission' => 700000, 'status' => 'شهري', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Installment::query()->insert([
            ['contract_code' => 'CT-2026-000045', 'number' => 1, 'due_date' => '2026-07-01', 'amount' => 5000000, 'paid_amount' => 0, 'status' => 'مستحق', 'created_at' => now(), 'updated_at' => now()],
            ['contract_code' => 'CT-2026-000045', 'number' => 2, 'due_date' => '2026-08-01', 'amount' => 5000000, 'paid_amount' => 0, 'status' => 'بانتظار', 'created_at' => now(), 'updated_at' => now()],
            ['contract_code' => 'CT-2026-000045', 'number' => 3, 'due_date' => '2026-09-01', 'amount' => 5000000, 'paid_amount' => 0, 'status' => 'بانتظار', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Voucher::query()->insert([
            ['code' => 'RV-2026-000001', 'type' => 'قبض', 'client' => 'أحمد علي', 'amount' => 2000000, 'reason' => 'مقدم شراء عقار', 'property_code' => 'PR-2026-000145', 'contract_code' => 'CT-2026-000044', 'issued_at' => '2026-06-26', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'PV-2026-000001', 'type' => 'دفع', 'client' => 'محمد حسن', 'amount' => 500000, 'reason' => 'مصاريف إعلان وتصوير عقار', 'property_code' => 'PR-2026-000146', 'contract_code' => null, 'issued_at' => '2026-06-26', 'created_at' => now(), 'updated_at' => now()],
        ]);

        LedgerEntry::query()->insert([
            ['code' => 'LE-2026-000001', 'direction' => 'credit', 'amount' => 2000000, 'description' => 'سند قبض RV-2026-000001', 'entry_date' => '2026-06-26', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'LE-2026-000002', 'direction' => 'debit', 'amount' => 500000, 'description' => 'سند دفع PV-2026-000001', 'entry_date' => '2026-06-26', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
