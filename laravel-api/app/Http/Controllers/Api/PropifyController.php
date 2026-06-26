<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\LedgerEntry;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PropifyController extends Controller
{
    public function options(): JsonResponse
    {
        return $this->json([], 204);
    }

    public function health(): JsonResponse
    {
        return $this->json([
            'ok' => true,
            'service' => 'propify-laravel-api',
            'database' => config('database.connections.mysql.database'),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return $this->json([
                'message' => 'بيانات الدخول غير صحيحة.',
                'errors' => ['email' => ['بيانات الدخول غير صحيحة.']],
            ], 422);
        }

        $token = Str::random(64);
        $user->forceFill(['api_token' => hash('sha256', $token)])->save();

        return $this->json([
            'token' => $token,
            'user' => $this->userResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->userFromRequest($request);

        if (! $user) {
            return $this->json(['message' => 'Unauthenticated'], 401);
        }

        return $this->json(['user' => $this->userResource($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->userFromRequest($request);

        if ($user) {
            $user->forceFill(['api_token' => null])->save();
        }

        return $this->json(['ok' => true]);
    }

    public function dashboard(): JsonResponse
    {
        return $this->json([
            'properties_total' => Property::count(),
            'properties_available' => Property::where('status', 'متاح')->count(),
            'properties_reserved' => Property::where('status', 'محجوز')->count(),
            'clients_total' => Client::count(),
            'contracts_total' => Contract::count(),
            'vouchers_total' => Voucher::count(),
            'office_profit' => (int) Contract::sum('commission'),
            'installments_due' => Installment::where('status', 'مستحق')->count(),
            'installments_late' => Installment::where('status', 'متأخر')->count(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->latest();
        $this->applySearch($query, $request, ['name', 'email', 'role']);

        return $this->json($query->get()->map(fn (User $user) => $this->userResource($user)));
    }

    public function storeUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ], $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
            'role' => $request->string('role'),
            'permissions' => $request->input('permissions', []),
        ]);

        return $this->json($this->userResource($user), 201);
    }

    public function properties(Request $request): JsonResponse
    {
        $query = Property::query()->latest();
        $this->applySearch($query, $request, ['code', 'type', 'mode', 'area', 'status', 'owner']);

        if ($request->filled('status') && $request->query('status') !== 'الكل') {
            $query->where('status', $request->query('status'));
        }

        return $this->json($query->get()->map(fn (Property $property) => $this->propertyResource($property)));
    }

    public function storeProperty(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string'],
            'mode' => ['required', 'string'],
            'province' => ['nullable', 'string'],
            'area' => ['required', 'string'],
            'space' => ['required', 'numeric', 'gt:0'],
            'rooms' => ['nullable', 'integer', 'min:0'],
            'price' => ['required'],
            'owner' => ['required', 'string'],
            'status' => ['nullable', 'string'],
            'negotiable' => ['boolean'],
        ], $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $property = Property::create([
            'code' => $this->nextCode(Property::class, 'PR', 144),
            'type' => $request->string('type'),
            'mode' => $request->string('mode'),
            'province' => $request->input('province', 'بغداد'),
            'area' => $request->string('area'),
            'space' => $request->input('space'),
            'rooms' => $request->integer('rooms'),
            'price' => $this->money($request->input('price')),
            'status' => $request->input('status', 'قيد المراجعة'),
            'owner' => $request->string('owner'),
            'negotiable' => $request->boolean('negotiable', true),
        ]);

        return $this->json($this->propertyResource($property), 201);
    }

    public function updateProperty(Request $request, Property $property): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->propertyRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $property->update($this->propertyData($request));

        return $this->json($this->propertyResource($property->refresh()));
    }

    public function approveProperty(Property $property): JsonResponse
    {
        $property->update(['status' => 'متاح']);

        return $this->json($this->propertyResource($property->refresh()));
    }

    public function deleteProperty(Property $property): JsonResponse
    {
        if (Contract::where('property_code', $property->code)->exists()) {
            return $this->json([
                'message' => 'لا يمكن حذف عقار مرتبط بعقد.',
                'errors' => ['property' => ['لا يمكن حذف عقار مرتبط بعقد.']],
            ], 409);
        }

        $media = PropertyMedia::where('property_code', $property->code)->get();
        $media->each(fn (PropertyMedia $item) => Storage::disk('public')->delete($item->path));
        PropertyMedia::where('property_code', $property->code)->delete();
        $property->delete();

        return $this->json(['ok' => true]);
    }

    public function propertyMedia(Property $property): JsonResponse
    {
        return $this->json(
            PropertyMedia::query()
                ->where('property_code', $property->code)
                ->latest()
                ->get()
                ->map(fn (PropertyMedia $media) => $this->mediaResource($media))
        );
    }

    public function storePropertyMedia(Request $request, Property $property): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx'],
        ], $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $created = collect($request->file('files'))->map(function ($file) use ($property) {
            $path = $file->store("properties/{$property->code}", 'public');
            $mime = $file->getClientMimeType();

            $media = PropertyMedia::create([
                'property_code' => $property->code,
                'kind' => Str::startsWith($mime, 'image/') ? 'image' : 'document',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size' => $file->getSize(),
            ]);

            return $this->mediaResource($media);
        });

        return $this->json($created, 201);
    }

    public function clients(Request $request): JsonResponse
    {
        $query = Client::query()->latest();
        $this->applySearch($query, $request, ['name', 'role', 'phone', 'stage', 'source']);

        return $this->json($query->get()->map(fn (Client $client) => $this->clientResource($client)));
    }

    public function storeClient(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->clientRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $client = Client::create($this->clientData($request));

        return $this->json($this->clientResource($client), 201);
    }

    public function updateClient(Request $request, Client $client): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->clientRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $client->update($this->clientData($request));

        return $this->json($this->clientResource($client->refresh()));
    }

    public function deleteClient(Client $client): JsonResponse
    {
        $hasContracts = Contract::where('client', $client->name)->exists();
        $hasVouchers = Voucher::where('client', $client->name)->exists();

        if ($hasContracts || $hasVouchers) {
            return $this->json([
                'message' => 'لا يمكن حذف عميل مرتبط بعقد أو سند.',
                'errors' => ['client' => ['لا يمكن حذف عميل مرتبط بعقد أو سند.']],
            ], 409);
        }

        $client->delete();

        return $this->json(['ok' => true]);
    }

    public function contracts(Request $request): JsonResponse
    {
        $query = Contract::query()->latest();
        $this->applySearch($query, $request, ['code', 'property_code', 'client', 'kind', 'status']);

        return $this->json($query->get()->map(fn (Contract $contract) => $this->contractResource($contract)));
    }

    public function storeContract(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->contractRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $contract = Contract::create([
            'code' => $this->nextCode(Contract::class, 'CT', 43),
            ...$this->contractData($request),
        ]);

        if ($contract->kind === 'تقسيط') {
            $count = $request->integer('installmentsCount', 1);
            $amount = round($contract->due / $count);

            for ($index = 1; $index <= $count; $index++) {
                Installment::create([
                    'contract_code' => $contract->code,
                    'number' => $index,
                    'due_date' => Carbon::now()->addMonths($index)->startOfMonth()->toDateString(),
                    'amount' => $amount,
                    'paid_amount' => 0,
                    'status' => $index === 1 ? 'مستحق' : 'بانتظار',
                ]);
            }
        }

        return $this->json($this->contractResource($contract), 201);
    }

    public function updateContract(Request $request, Contract $contract): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->contractRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $paidInstallments = Installment::where('contract_code', $contract->code)->where('paid_amount', '>', 0)->exists();
        if ($paidInstallments && $request->input('kind') !== $contract->kind) {
            return $this->json([
                'message' => 'لا يمكن تغيير نوع عقد لديه أقساط مدفوعة.',
                'errors' => ['kind' => ['لا يمكن تغيير نوع عقد لديه أقساط مدفوعة.']],
            ], 409);
        }

        $contract->update($this->contractData($request));

        return $this->json($this->contractResource($contract->refresh()));
    }

    public function deleteContract(Contract $contract): JsonResponse
    {
        if (Voucher::where('contract_code', $contract->code)->exists()) {
            return $this->json([
                'message' => 'لا يمكن حذف عقد مرتبط بسندات.',
                'errors' => ['contract' => ['لا يمكن حذف عقد مرتبط بسندات.']],
            ], 409);
        }

        Installment::where('contract_code', $contract->code)->delete();
        $contract->delete();

        return $this->json(['ok' => true]);
    }

    public function installments(Request $request): JsonResponse
    {
        $query = Installment::query()->orderBy('due_date');
        $this->applySearch($query, $request, ['contract_code', 'status']);

        return $this->json($query->get()->map(fn (Installment $installment) => $this->installmentResource($installment)));
    }

    public function payInstallment(Request $request, Installment $installment): JsonResponse
    {
        if ($installment->status === 'مدفوع') {
            return $this->json(['message' => 'القسط مدفوع مسبقاً.', 'errors' => ['installment' => ['القسط مدفوع مسبقاً.']]], 409);
        }

        $remaining = max(0, (float) $installment->amount - (float) $installment->paid_amount);
        $paidAmount = $this->money($request->input('amount', $remaining));

        if ($paidAmount <= 0 || $paidAmount > $remaining) {
            return $this->json(['message' => 'Validation failed', 'errors' => ['amount' => ['مبلغ الدفع غير صحيح.']]], 422);
        }

        $installment->paid_amount = (float) $installment->paid_amount + $paidAmount;
        $installment->status = $installment->paid_amount >= $installment->amount ? 'مدفوع' : 'مدفوع جزئياً';
        $installment->save();

        $contract = Contract::where('code', $installment->contract_code)->first();
        if ($contract) {
            $contract->paid = (float) $contract->paid + $paidAmount;
            $contract->due = max(0, (float) $contract->due - $paidAmount);
            if ($contract->due <= 0) {
                $contract->status = 'مكتمل';
            }
            $contract->save();
        }

        $voucher = Voucher::create([
            'code' => $this->nextCode(Voucher::class, 'RV', 0),
            'type' => 'قبض',
            'client' => $contract?->client ?? 'دفعة قسط',
            'amount' => $paidAmount,
            'reason' => "تسديد قسط {$installment->number} للعقد {$installment->contract_code}",
            'property_code' => $contract?->property_code,
            'contract_code' => $installment->contract_code,
            'issued_at' => Carbon::now()->toDateString(),
        ]);

        LedgerEntry::create([
            'code' => $this->nextCode(LedgerEntry::class, 'LE', 0),
            'direction' => 'credit',
            'amount' => $paidAmount,
            'description' => "تسديد قسط {$installment->number} للعقد {$installment->contract_code}",
            'entry_date' => Carbon::now()->toDateString(),
        ]);

        return $this->json([
            'installment' => $this->installmentResource($installment->refresh()),
            'contract' => $contract ? $this->contractResource($contract->refresh()) : null,
            'voucher' => $this->voucherResource($voucher),
        ]);
    }

    public function vouchers(Request $request): JsonResponse
    {
        $query = Voucher::query()->latest();
        $this->applySearch($query, $request, ['code', 'type', 'client', 'reason', 'property_code', 'contract_code']);

        return $this->json($query->get()->map(fn (Voucher $voucher) => $this->voucherResource($voucher)));
    }

    public function storeVoucher(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:قبض,دفع'],
            'client' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string'],
            'propertyCode' => ['nullable', 'string'],
            'contractCode' => ['nullable', 'string'],
            'issuedAt' => ['nullable', 'date'],
        ], $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $prefix = $request->input('type') === 'قبض' ? 'RV' : 'PV';
        $issuedAt = $request->input('issuedAt', Carbon::now()->toDateString());

        $voucher = Voucher::create([
            'code' => $this->nextCode(Voucher::class, $prefix, 0),
            'type' => $request->string('type'),
            'client' => $request->string('client'),
            'amount' => $this->money($request->input('amount')),
            'reason' => $request->string('reason'),
            'property_code' => $request->input('propertyCode'),
            'contract_code' => $request->input('contractCode'),
            'issued_at' => $issuedAt,
        ]);

        LedgerEntry::create([
            'code' => $this->nextCode(LedgerEntry::class, 'LE', 0),
            'direction' => $voucher->type === 'قبض' ? 'credit' : 'debit',
            'amount' => $voucher->amount,
            'description' => "سند {$voucher->type} {$voucher->code}",
            'entry_date' => $issuedAt,
        ]);

        return $this->json($this->voucherResource($voucher), 201);
    }

    public function ledger(): JsonResponse
    {
        return $this->json(
            LedgerEntry::query()->latest()->get()->map(fn (LedgerEntry $entry) => $this->ledgerResource($entry))
        );
    }

    public function notifications(): JsonResponse
    {
        $dueInstallments = Installment::query()
            ->whereDate('due_date', '<=', Carbon::now()->addDays(7)->toDateString())
            ->where('status', '!=', 'مدفوع')
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->map(fn (Installment $installment) => [
                'id' => "installment-{$installment->contract_code}-{$installment->number}",
                'type' => 'installment',
                'severity' => $installment->due_date->isPast() ? 'danger' : 'warning',
                'title' => 'قسط قريب الاستحقاق',
                'message' => "القسط {$installment->number} للعقد {$installment->contract_code} يستحق في {$installment->due_date->format('Y-m-d')}.",
                'createdAt' => $installment->updated_at?->toDateTimeString(),
            ]);

        $pendingProperties = Property::query()
            ->where('status', 'قيد المراجعة')
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (Property $property) => [
                'id' => "property-{$property->code}",
                'type' => 'property',
                'severity' => 'info',
                'title' => 'عقار بانتظار المراجعة',
                'message' => "العقار {$property->code} في {$property->area} يحتاج مراجعة قبل الاعتماد.",
                'createdAt' => $property->updated_at?->toDateTimeString(),
            ]);

        $openContracts = Contract::query()
            ->where('due', '>', 0)
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (Contract $contract) => [
                'id' => "contract-{$contract->code}",
                'type' => 'contract',
                'severity' => 'warning',
                'title' => 'مبلغ متبق على عقد',
                'message' => "العقد {$contract->code} لديه متبق قدره ".number_format((float) $contract->due).' دينار.',
                'createdAt' => $contract->updated_at?->toDateTimeString(),
            ]);

        $recentVouchers = Voucher::query()
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Voucher $voucher) => [
                'id' => "voucher-{$voucher->code}",
                'type' => 'voucher',
                'severity' => $voucher->type === 'قبض' ? 'success' : 'info',
                'title' => "سند {$voucher->type} جديد",
                'message' => "تم تسجيل {$voucher->code} بقيمة ".number_format((float) $voucher->amount).' دينار.',
                'createdAt' => $voucher->created_at?->toDateTimeString(),
            ]);

        return $this->json(
            $dueInstallments
                ->merge($pendingProperties)
                ->merge($openContracts)
                ->merge($recentVouchers)
                ->sortByDesc('createdAt')
                ->take(12)
                ->values()
        );
    }

    public function financialReport(Request $request): JsonResponse
    {
        $ledgerQuery = LedgerEntry::query();
        $this->applyDateRange($ledgerQuery, $request, 'entry_date');

        $entries = $ledgerQuery->get();
        $income = (float) $entries->where('direction', 'credit')->sum('amount');
        $expenses = (float) $entries->where('direction', 'debit')->sum('amount');

        return $this->json([
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $income - $expenses,
            'contractsTotal' => (float) Contract::sum('total'),
            'contractsPaid' => (float) Contract::sum('paid'),
            'contractsDue' => (float) Contract::sum('due'),
            'officeCommission' => (float) Contract::sum('commission'),
            'vouchersCount' => Voucher::count(),
        ]);
    }

    public function propertiesReport(Request $request): JsonResponse
    {
        $query = Property::query();

        if ($request->filled('status') && $request->query('status') !== 'الكل') {
            $query->where('status', $request->query('status'));
        }

        $properties = $query->get();

        return $this->json([
            'total' => $properties->count(),
            'totalValue' => (float) $properties->sum('price'),
            'byStatus' => $this->groupCounts($properties, 'status'),
            'byMode' => $this->groupCounts($properties, 'mode'),
            'byProvince' => $this->groupCounts($properties, 'province'),
        ]);
    }

    public function installmentsReport(Request $request): JsonResponse
    {
        $query = Installment::query()->orderBy('due_date');
        $this->applyDateRange($query, $request, 'due_date');

        if ($request->filled('status') && $request->query('status') !== 'الكل') {
            $query->where('status', $request->query('status'));
        }

        $installments = $query->get();

        return $this->json([
            'total' => $installments->count(),
            'amountTotal' => (float) $installments->sum('amount'),
            'paidTotal' => (float) $installments->sum('paid_amount'),
            'remainingTotal' => (float) ($installments->sum('amount') - $installments->sum('paid_amount')),
            'byStatus' => $this->groupCounts($installments, 'status'),
            'upcoming' => $installments->take(8)->map(fn (Installment $installment) => $this->installmentResource($installment))->values(),
        ]);
    }

    public function employeePerformanceReport(): JsonResponse
    {
        $users = User::query()->latest()->get();

        return $this->json([
            'usersTotal' => $users->count(),
            'byRole' => $this->groupCounts($users, 'role'),
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'permissionsCount' => count($user->permissions ?? []),
            ])->values(),
        ]);
    }

    private function json(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }

    private function applySearch($query, Request $request, array $columns): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = $request->query('search');
        $query->where(function ($builder) use ($columns, $search) {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    private function propertyRules(): array
    {
        return [
            'type' => ['required', 'string'],
            'mode' => ['required', 'string'],
            'province' => ['nullable', 'string'],
            'area' => ['required', 'string'],
            'space' => ['required', 'numeric', 'gt:0'],
            'rooms' => ['nullable', 'integer', 'min:0'],
            'price' => ['required'],
            'owner' => ['required', 'string'],
            'status' => ['nullable', 'string'],
            'negotiable' => ['boolean'],
        ];
    }

    private function propertyData(Request $request): array
    {
        return [
            'type' => $request->string('type'),
            'mode' => $request->string('mode'),
            'province' => $request->input('province', 'بغداد'),
            'area' => $request->string('area'),
            'space' => $request->input('space'),
            'rooms' => $request->integer('rooms'),
            'price' => $this->money($request->input('price')),
            'status' => $request->input('status', 'قيد المراجعة'),
            'owner' => $request->string('owner'),
            'negotiable' => $request->boolean('negotiable', true),
        ];
    }

    private function clientRules(): array
    {
        return [
            'name' => ['required', 'string'],
            'role' => ['required', 'string'],
            'phone' => ['required', 'regex:/^(075|077|078|079)[0-9]{8}$/'],
            'nationalId' => ['required', 'regex:/^[A-Za-z0-9]{12,}$/'],
            'stage' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
        ];
    }

    private function clientData(Request $request): array
    {
        return [
            'name' => $request->string('name'),
            'role' => $request->string('role'),
            'phone' => $request->string('phone'),
            'national_id' => $request->string('nationalId'),
            'stage' => $request->input('stage', 'عميل محتمل'),
            'source' => $request->input('source', 'الموقع'),
        ];
    }

    private function contractRules(): array
    {
        return [
            'propertyCode' => ['required', 'string'],
            'client' => ['required', 'string'],
            'kind' => ['required', 'string'],
            'total' => ['required', 'numeric', 'gt:0'],
            'paid' => ['nullable', 'numeric', 'min:0', 'lte:total'],
            'commissionRate' => ['nullable', 'numeric', 'min:0'],
            'installmentsCount' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string'],
        ];
    }

    private function contractData(Request $request): array
    {
        $total = $this->money($request->input('total'));
        $paid = $this->money($request->input('paid', 0));
        $commissionRate = (float) $request->input('commissionRate', 0);

        return [
            'property_code' => $request->string('propertyCode'),
            'client' => $request->string('client'),
            'kind' => $request->string('kind'),
            'total' => $total,
            'paid' => $paid,
            'due' => $total - $paid,
            'commission' => round($total * ($commissionRate / 100)),
            'status' => $request->input('status', 'نشط'),
        ];
    }

    private function applyDateRange($query, Request $request, string $column): void
    {
        if ($request->filled('from')) {
            $query->whereDate($column, '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate($column, '<=', $request->query('to'));
        }
    }

    private function groupCounts($collection, string $key)
    {
        return $collection
            ->groupBy($key)
            ->map(fn ($items, $label) => ['label' => (string) $label, 'count' => $items->count()])
            ->values();
    }

    private function nextCode(string $model, string $prefix, int $start): string
    {
        $year = now()->year;
        $latest = $model::query()
            ->where('code', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('code')
            ->value('code');

        $sequence = $latest ? (int) Str::afterLast($latest, '-') : $start;

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence + 1);
    }

    private function money(mixed $value): float
    {
        return (float) str_replace(',', '', (string) $value);
    }

    private function messages(): array
    {
        return [
            'required' => 'هذا الحقل إجباري.',
            'numeric' => 'القيمة يجب أن تكون رقمية.',
            'gt' => 'القيمة يجب أن تكون أكبر من صفر.',
            'min' => 'القيمة غير صحيحة.',
            'integer' => 'القيمة يجب أن تكون رقماً صحيحاً.',
            'regex' => 'صيغة الحقل غير صحيحة.',
            'in' => 'القيمة المختارة غير صحيحة.',
            'lte' => 'المدفوع لا يمكن أن يتجاوز قيمة العقد.',
            'email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'unique' => 'القيمة مستخدمة مسبقاً.',
            'file' => 'يرجى اختيار ملف صحيح.',
            'mimes' => 'نوع الملف غير مدعوم.',
            'max' => 'القيمة تتجاوز الحد المسموح.',
        ];
    }

    private function userFromRequest(Request $request): ?User
    {
        $header = $request->header('Authorization', '');
        $token = Str::startsWith($header, 'Bearer ') ? Str::after($header, 'Bearer ') : null;

        if (! $token) {
            return null;
        }

        return User::where('api_token', hash('sha256', $token))->first();
    }

    private function userResource(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'permissions' => $user->permissions ?? [],
        ];
    }

    private function propertyResource(Property $property): array
    {
        return [
            'code' => $property->code,
            'type' => $property->type,
            'mode' => $property->mode,
            'province' => $property->province,
            'area' => $property->area,
            'space' => (float) $property->space,
            'rooms' => $property->rooms,
            'price' => number_format((float) $property->price),
            'status' => $property->status,
            'owner' => $property->owner,
            'negotiable' => (bool) $property->negotiable,
            'mediaCount' => PropertyMedia::where('property_code', $property->code)->count(),
        ];
    }

    private function mediaResource(PropertyMedia $media): array
    {
        return [
            'id' => $media->id,
            'propertyCode' => $media->property_code,
            'kind' => $media->kind,
            'name' => $media->original_name,
            'mimeType' => $media->mime_type,
            'size' => $media->size,
            'url' => asset('storage/'.$media->path),
            'createdAt' => $media->created_at?->toDateTimeString(),
        ];
    }

    private function clientResource(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'role' => $client->role,
            'phone' => $client->phone,
            'nationalId' => $client->national_id,
            'stage' => $client->stage,
            'source' => $client->source,
        ];
    }

    private function contractResource(Contract $contract): array
    {
        return [
            'code' => $contract->code,
            'propertyCode' => $contract->property_code,
            'client' => $contract->client,
            'kind' => $contract->kind,
            'total' => (float) $contract->total,
            'paid' => (float) $contract->paid,
            'due' => (float) $contract->due,
            'commission' => (float) $contract->commission,
            'status' => $contract->status,
        ];
    }

    private function installmentResource(Installment $installment): array
    {
        return [
            'id' => $installment->id,
            'contractCode' => $installment->contract_code,
            'number' => $installment->number,
            'dueDate' => $installment->due_date->format('Y-m-d'),
            'amount' => (float) $installment->amount,
            'paidAmount' => (float) $installment->paid_amount,
            'status' => $installment->status,
        ];
    }

    private function voucherResource(Voucher $voucher): array
    {
        return [
            'code' => $voucher->code,
            'type' => $voucher->type,
            'client' => $voucher->client,
            'amount' => (float) $voucher->amount,
            'reason' => $voucher->reason,
            'propertyCode' => $voucher->property_code,
            'contractCode' => $voucher->contract_code,
            'issuedAt' => $voucher->issued_at->format('Y-m-d'),
        ];
    }

    private function ledgerResource(LedgerEntry $entry): array
    {
        return [
            'code' => $entry->code,
            'direction' => $entry->direction,
            'amount' => (float) $entry->amount,
            'description' => $entry->description,
            'entryDate' => $entry->entry_date->format('Y-m-d'),
        ];
    }
}
