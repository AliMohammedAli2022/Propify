<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppSetting;
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
use Illuminate\Support\Facades\DB;
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
        $this->logActivity($request, 'login', 'user', (string) $user->id, "تسجيل دخول {$user->name}", [], $user);

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
            $this->logActivity($request, 'logout', 'user', (string) $user->id, "تسجيل خروج {$user->name}", [], $user);
        }

        return $this->json(['ok' => true]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request)) {
            return $guard;
        }

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
        if ($guard = $this->guard($request, 'users.manage')) {
            return $guard;
        }

        $query = User::query()->latest();
        $this->applySearch($query, $request, ['name', 'email', 'role']);
        $users = $query->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse([
                ['الاسم', 'البريد', 'الدور', 'عدد الصلاحيات', 'الصلاحيات'],
                ...$users->map(fn (User $user) => [
                    $user->name,
                    $user->email,
                    $user->role,
                    count($user->permissions ?? []),
                    implode(' | ', $user->permissions ?? []),
                ])->all(),
            ], 'propify-users.csv');
        }

        return $this->json($users->map(fn (User $user) => $this->userResource($user)));
    }

    public function storeUser(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'users.manage')) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->userRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::create($this->userData($request, true));
        $this->logActivity($request, 'create', 'user', (string) $user->id, "إضافة مستخدم {$user->name}");

        return $this->json($this->userResource($user), 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        if ($guard = $this->guard($request, 'users.manage')) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->userRules($user), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        if ($user->role === 'system_admin' && $request->input('role') !== 'system_admin' && User::where('role', 'system_admin')->count() <= 1) {
            return $this->json([
                'message' => 'لا يمكن إزالة آخر مدير نظام.',
                'errors' => ['role' => ['لا يمكن إزالة آخر مدير نظام.']],
            ], 409);
        }

        $user->update($this->userData($request, false));
        $this->logActivity($request, 'update', 'user', (string) $user->id, "تعديل مستخدم {$user->name}");

        return $this->json($this->userResource($user->refresh()));
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($guard = $this->guard($request, 'users.manage')) {
            return $guard;
        }

        if ($user->role === 'system_admin' && User::where('role', 'system_admin')->count() <= 1) {
            return $this->json([
                'message' => 'لا يمكن حذف آخر مدير نظام.',
                'errors' => ['user' => ['لا يمكن حذف آخر مدير نظام.']],
            ], 409);
        }

        $summary = "حذف مستخدم {$user->name}";
        $subjectId = (string) $user->id;
        $user->delete();
        $this->logActivity($request, 'delete', 'user', $subjectId, $summary);

        return $this->json(['ok' => true]);
    }

    public function properties(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request)) {
            return $guard;
        }

        $query = Property::query()->latest();
        $this->applySearch($query, $request, ['code', 'type', 'mode', 'area', 'status', 'owner']);

        if ($request->filled('status') && $request->query('status') !== 'الكل') {
            $query->where('status', $request->query('status'));
        }

        return $this->json($query->get()->map(fn (Property $property) => $this->propertyResource($property)));
    }

    public function storeProperty(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'properties.create')) {
            return $guard;
        }

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
        $this->logActivity($request, 'create', 'property', $property->code, "إضافة عقار {$property->code}");

        return $this->json($this->propertyResource($property), 201);
    }

    public function updateProperty(Request $request, Property $property): JsonResponse
    {
        if ($guard = $this->guard($request, 'properties.update')) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->propertyRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $property->update($this->propertyData($request));
        $this->logActivity($request, 'update', 'property', $property->code, "تعديل عقار {$property->code}");

        return $this->json($this->propertyResource($property->refresh()));
    }

    public function approveProperty(Request $request, Property $property): JsonResponse
    {
        if ($guard = $this->guard($request, 'properties.approve')) {
            return $guard;
        }

        $property->update(['status' => 'متاح']);
        $this->logActivity($request, 'approve', 'property', $property->code, "اعتماد عقار {$property->code}");

        return $this->json($this->propertyResource($property->refresh()));
    }

    public function deleteProperty(Request $request, Property $property): JsonResponse
    {
        if ($guard = $this->guard($request, 'properties.update')) {
            return $guard;
        }

        if (Contract::where('property_code', $property->code)->exists()) {
            return $this->json([
                'message' => 'لا يمكن حذف عقار مرتبط بعقد.',
                'errors' => ['property' => ['لا يمكن حذف عقار مرتبط بعقد.']],
            ], 409);
        }

        $media = PropertyMedia::where('property_code', $property->code)->get();
        $media->each(fn (PropertyMedia $item) => Storage::disk('public')->delete($item->path));
        PropertyMedia::where('property_code', $property->code)->delete();
        $summary = "حذف عقار {$property->code}";
        $subjectId = $property->code;
        $property->delete();
        $this->logActivity($request, 'delete', 'property', $subjectId, $summary);

        return $this->json(['ok' => true]);
    }

    public function propertyMedia(Request $request, Property $property): JsonResponse
    {
        if ($guard = $this->guard($request)) {
            return $guard;
        }

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
        if ($guard = $this->guard($request, 'properties.update')) {
            return $guard;
        }

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
        $this->logActivity($request, 'upload', 'property', $property->code, "رفع ملفات للعقار {$property->code}", ['files_count' => $created->count()]);

        return $this->json($created, 201);
    }

    public function deletePropertyMedia(Request $request, Property $property, PropertyMedia $media): JsonResponse
    {
        if ($guard = $this->guard($request, 'properties.update')) {
            return $guard;
        }

        if ($media->property_code !== $property->code) {
            return $this->json(['message' => 'Media file does not belong to this property.'], 404);
        }

        Storage::disk('public')->delete($media->path);
        $mediaName = $media->original_name;
        $media->delete();

        $this->logActivity($request, 'delete', 'property_media', (string) $media->id, "حذف ملف من العقار {$property->code}", [
            'property_code' => $property->code,
            'file_name' => $mediaName,
        ]);

        return $this->json(['ok' => true]);
    }

    public function clients(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'clients.manage')) {
            return $guard;
        }

        $query = Client::query()->latest();
        $this->applySearch($query, $request, ['name', 'role', 'phone', 'stage', 'source']);
        $clients = $query->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse([
                ['الاسم', 'العلاقة', 'الهاتف', 'رقم البطاقة', 'المرحلة', 'المصدر'],
                ...$clients->map(fn (Client $client) => [
                    $client->name,
                    $client->role,
                    $client->phone,
                    $client->national_id,
                    $client->stage,
                    $client->source,
                ])->all(),
            ], 'propify-clients.csv');
        }

        return $this->json($clients->map(fn (Client $client) => $this->clientResource($client)));
    }

    public function storeClient(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'clients.manage')) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->clientRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $client = Client::create($this->clientData($request));
        $this->logActivity($request, 'create', 'client', (string) $client->id, "إضافة عميل {$client->name}");

        return $this->json($this->clientResource($client), 201);
    }

    public function updateClient(Request $request, Client $client): JsonResponse
    {
        if ($guard = $this->guard($request, 'clients.manage')) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->clientRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $client->update($this->clientData($request));
        $this->logActivity($request, 'update', 'client', (string) $client->id, "تعديل عميل {$client->name}");

        return $this->json($this->clientResource($client->refresh()));
    }

    public function deleteClient(Request $request, Client $client): JsonResponse
    {
        if ($guard = $this->guard($request, 'clients.manage')) {
            return $guard;
        }

        $hasContracts = Contract::where('client', $client->name)->exists();
        $hasVouchers = Voucher::where('client', $client->name)->exists();

        if ($hasContracts || $hasVouchers) {
            return $this->json([
                'message' => 'لا يمكن حذف عميل مرتبط بعقد أو سند.',
                'errors' => ['client' => ['لا يمكن حذف عميل مرتبط بعقد أو سند.']],
            ], 409);
        }

        $summary = "حذف عميل {$client->name}";
        $subjectId = (string) $client->id;
        $client->delete();
        $this->logActivity($request, 'delete', 'client', $subjectId, $summary);

        return $this->json(['ok' => true]);
    }

    public function contracts(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request)) {
            return $guard;
        }

        $query = Contract::query()->latest();
        $this->applySearch($query, $request, ['code', 'property_code', 'client', 'kind', 'status']);
        $contracts = $query->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse([
                ['رقم العقد', 'العقار', 'العميل', 'النوع', 'الإجمالي', 'المدفوع', 'المتبقي', 'العمولة', 'الحالة'],
                ...$contracts->map(fn (Contract $contract) => [
                    $contract->code,
                    $contract->property_code,
                    $contract->client,
                    $contract->kind,
                    $contract->total,
                    $contract->paid,
                    $contract->due,
                    $contract->commission,
                    $contract->status,
                ])->all(),
            ], 'propify-contracts.csv');
        }

        return $this->json($contracts->map(fn (Contract $contract) => $this->contractResource($contract)));
    }

    public function storeContract(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'contracts.create')) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->contractRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $contract = Contract::create([
            'code' => $this->nextCode(Contract::class, 'CT', 43),
            ...$this->contractData($request),
        ]);
        $this->logActivity($request, 'create', 'contract', $contract->code, "إنشاء عقد {$contract->code}");

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
        if ($guard = $this->guard($request, 'contracts.create')) {
            return $guard;
        }

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
        $this->logActivity($request, 'update', 'contract', $contract->code, "تعديل عقد {$contract->code}");

        return $this->json($this->contractResource($contract->refresh()));
    }

    public function deleteContract(Request $request, Contract $contract): JsonResponse
    {
        if ($guard = $this->guard($request, 'contracts.create')) {
            return $guard;
        }

        if (Voucher::where('contract_code', $contract->code)->exists()) {
            return $this->json([
                'message' => 'لا يمكن حذف عقد مرتبط بسندات.',
                'errors' => ['contract' => ['لا يمكن حذف عقد مرتبط بسندات.']],
            ], 409);
        }

        Installment::where('contract_code', $contract->code)->delete();
        $summary = "حذف عقد {$contract->code}";
        $subjectId = $contract->code;
        $contract->delete();
        $this->logActivity($request, 'delete', 'contract', $subjectId, $summary);

        return $this->json(['ok' => true]);
    }

    public function printContract(Request $request, Contract $contract)
    {
        if ($guard = $this->guard($request, 'contracts.print')) {
            return $guard;
        }

        $property = Property::where('code', $contract->property_code)->first();
        $installments = Installment::where('contract_code', $contract->code)->orderBy('number')->get();

        $rows = [
            ['رقم العقد', $contract->code],
            ['نوع العقد', $contract->kind],
            ['رقم العقار', $contract->property_code ?: '-'],
            ['العميل', $contract->client],
            ['قيمة العقد', number_format((float) $contract->total).' دينار'],
            ['المدفوع', number_format((float) $contract->paid).' دينار'],
            ['المتبقي', number_format((float) $contract->due).' دينار'],
            ['عمولة المكتب', number_format((float) $contract->commission).' دينار'],
            ['الحالة', $contract->status],
            ['تاريخ الإصدار', now()->format('Y-m-d')],
        ];

        if ($property) {
            $rows[] = ['موقع العقار', "{$property->province} / {$property->area}"];
            $rows[] = ['نوع العقار', $property->type];
            $rows[] = ['المساحة', number_format((float) $property->space).' م2'];
        }

        $installmentRows = $installments->map(fn (Installment $installment) => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            e($installment->number),
            e($installment->due_date->format('Y-m-d')),
            e(number_format((float) $installment->amount)),
            e(number_format((float) $installment->paid_amount)),
            e($installment->status),
        ))->implode('');

        $schedule = $installments->isEmpty() ? '' : '
            <section>
                <h2>جدول الأقساط</h2>
                <table>
                    <thead><tr><th>القسط</th><th>الاستحقاق</th><th>المبلغ</th><th>المدفوع</th><th>الحالة</th></tr></thead>
                    <tbody>'.$installmentRows.'</tbody>
                </table>
            </section>';

        return $this->documentResponse(
            "عقد {$contract->kind} {$contract->code}",
            $this->documentTable($rows).$schedule.$this->signatureBlock(['الطرف الأول', 'الطرف الثاني', 'ختم المكتب'])
        );
    }

    public function installments(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request)) {
            return $guard;
        }

        $query = Installment::query()->orderBy('due_date');
        $this->applySearch($query, $request, ['contract_code', 'status']);

        return $this->json($query->get()->map(fn (Installment $installment) => $this->installmentResource($installment)));
    }

    public function payInstallment(Request $request, Installment $installment): JsonResponse
    {
        if ($guard = $this->guard($request, 'vouchers.manage')) {
            return $guard;
        }

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
        $this->logActivity($request, 'pay', 'installment', (string) $installment->id, "تسديد قسط {$installment->number} للعقد {$installment->contract_code}", ['amount' => $paidAmount]);

        return $this->json([
            'installment' => $this->installmentResource($installment->refresh()),
            'contract' => $contract ? $this->contractResource($contract->refresh()) : null,
            'voucher' => $this->voucherResource($voucher),
        ]);
    }

    public function vouchers(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'vouchers.manage')) {
            return $guard;
        }

        $query = Voucher::query()->latest();
        $this->applySearch($query, $request, ['code', 'type', 'client', 'reason', 'property_code', 'contract_code']);
        $vouchers = $query->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse([
                ['رقم السند', 'النوع', 'الطرف', 'المبلغ', 'السبب', 'العقار', 'العقد', 'تاريخ الإصدار'],
                ...$vouchers->map(fn (Voucher $voucher) => [
                    $voucher->code,
                    $voucher->type,
                    $voucher->client,
                    $voucher->amount,
                    $voucher->reason,
                    $voucher->property_code,
                    $voucher->contract_code,
                    $voucher->issued_at->format('Y-m-d'),
                ])->all(),
            ], 'propify-vouchers.csv');
        }

        return $this->json($vouchers->map(fn (Voucher $voucher) => $this->voucherResource($voucher)));
    }

    public function storeVoucher(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'vouchers.manage')) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->voucherRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $prefix = $request->input('type') === 'قبض' ? 'RV' : 'PV';
        $voucher = Voucher::create([
            'code' => $this->nextCode(Voucher::class, $prefix, 0),
            ...$this->voucherData($request),
        ]);

        $this->syncVoucherLedger($voucher);
        $this->logActivity($request, 'create', 'voucher', $voucher->code, "إنشاء سند {$voucher->type} {$voucher->code}");

        return $this->json($this->voucherResource($voucher), 201);
    }

    public function updateVoucher(Request $request, Voucher $voucher): JsonResponse
    {
        if ($guard = $this->guard($request, 'vouchers.manage')) {
            return $guard;
        }

        if (Str::startsWith($voucher->reason, 'تسديد قسط')) {
            return $this->json([
                'message' => 'لا يمكن تعديل سند ناتج عن تسديد قسط.',
                'errors' => ['voucher' => ['لا يمكن تعديل سند ناتج عن تسديد قسط.']],
            ], 409);
        }

        $validator = Validator::make($request->all(), $this->voucherRules(), $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        LedgerEntry::where('description', 'like', "%{$voucher->code}%")->delete();
        $voucher->update($this->voucherData($request));
        $this->syncVoucherLedger($voucher->refresh());
        $this->logActivity($request, 'update', 'voucher', $voucher->code, "تعديل سند {$voucher->code}");

        return $this->json($this->voucherResource($voucher->refresh()));
    }

    public function deleteVoucher(Request $request, Voucher $voucher): JsonResponse
    {
        if ($guard = $this->guard($request, 'vouchers.manage')) {
            return $guard;
        }

        if (Str::startsWith($voucher->reason, 'تسديد قسط')) {
            return $this->json([
                'message' => 'لا يمكن حذف سند ناتج عن تسديد قسط.',
                'errors' => ['voucher' => ['لا يمكن حذف سند ناتج عن تسديد قسط.']],
            ], 409);
        }

        LedgerEntry::where('description', 'like', "%{$voucher->code}%")->delete();
        $summary = "حذف سند {$voucher->code}";
        $subjectId = $voucher->code;
        $voucher->delete();
        $this->logActivity($request, 'delete', 'voucher', $subjectId, $summary);

        return $this->json(['ok' => true]);
    }

    public function printVoucher(Request $request, Voucher $voucher)
    {
        if ($guard = $this->guard($request, 'vouchers.manage')) {
            return $guard;
        }

        $rows = [
            ['رقم السند', $voucher->code],
            ['نوع السند', $voucher->type],
            ['الطرف', $voucher->client],
            ['المبلغ', number_format((float) $voucher->amount).' دينار'],
            ['السبب', $voucher->reason],
            ['العقار', $voucher->property_code ?: '-'],
            ['العقد', $voucher->contract_code ?: '-'],
            ['تاريخ السند', $voucher->issued_at->format('Y-m-d')],
        ];

        return $this->documentResponse(
            "سند {$voucher->type} {$voucher->code}",
            $this->documentTable($rows).$this->signatureBlock(['المحاسب', 'المستلم / الدافع', 'ختم المكتب'])
        );
    }

    public function ledger(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'vouchers.manage')) {
            return $guard;
        }

        $entries = LedgerEntry::query()->latest()->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse([
                ['رقم القيد', 'الاتجاه', 'المبلغ', 'الوصف', 'التاريخ'],
                ...$entries->map(fn (LedgerEntry $entry) => [
                    $entry->code,
                    $entry->direction === 'credit' ? 'إيراد' : 'مصروف',
                    $entry->amount,
                    $entry->description,
                    $entry->entry_date->format('Y-m-d'),
                ])->all(),
            ], 'propify-ledger.csv');
        }

        return $this->json($entries->map(fn (LedgerEntry $entry) => $this->ledgerResource($entry)));
    }

    public function notifications(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request)) {
            return $guard;
        }

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

    public function activityLogs(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'reports.view')) {
            return $guard;
        }

        return $this->json(
            ActivityLog::query()
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (ActivityLog $activity) => $this->activityResource($activity))
        );
    }

    public function exportBackup(Request $request)
    {
        if ($guard = $this->guard($request, 'settings.update')) {
            return $guard;
        }

        $createdAt = now();
        $payload = [
            'meta' => [
                'app' => 'Propify',
                'version' => 1,
                'createdAt' => $createdAt->toDateTimeString(),
                'database' => config('database.connections.mysql.database'),
            ],
            'settings' => $this->settingsResource($this->appSettings()),
            'users' => User::query()->latest()->get()->map(fn (User $user) => $this->userResource($user))->values(),
            'clients' => Client::query()->latest()->get()->map(fn (Client $client) => $this->clientResource($client))->values(),
            'properties' => Property::query()->latest()->get()->map(fn (Property $property) => $this->propertyResource($property))->values(),
            'contracts' => Contract::query()->latest()->get()->map(fn (Contract $contract) => $this->contractResource($contract))->values(),
            'installments' => Installment::query()->orderBy('due_date')->get()->map(fn (Installment $installment) => $this->installmentResource($installment))->values(),
            'vouchers' => Voucher::query()->latest()->get()->map(fn (Voucher $voucher) => $this->voucherResource($voucher))->values(),
            'ledger' => LedgerEntry::query()->latest()->get()->map(fn (LedgerEntry $entry) => $this->ledgerResource($entry))->values(),
            'media' => PropertyMedia::query()->latest()->get()->map(fn (PropertyMedia $media) => $this->mediaResource($media))->values(),
            'activityLogs' => ActivityLog::query()->latest()->limit(500)->get()->map(fn (ActivityLog $activity) => $this->activityResource($activity))->values(),
        ];

        $this->logActivity($request, 'export', 'backup', $createdAt->format('Ymd-His'), 'تصدير نسخة احتياطية للنظام');

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="propify-backup-'.$createdAt->format('Ymd-His').'.json"',
            'Access-Control-Allow-Origin' => '*',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function importBackup(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'settings.update')) {
            return $guard;
        }

        $validator = Validator::make($request->all(), [
            'backup' => ['required', 'file', 'max:51200'],
        ], $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $payload = json_decode((string) file_get_contents($request->file('backup')->getRealPath()), true);
        if (! is_array($payload) || data_get($payload, 'meta.app') !== 'Propify') {
            return $this->json(['message' => 'Invalid backup file', 'errors' => ['backup' => ['ملف النسخة الاحتياطية غير صالح.']]], 422);
        }

        $summary = DB::transaction(function () use ($payload) {
            $summary = [
                'settings' => 0,
                'users' => 0,
                'clients' => 0,
                'properties' => 0,
                'contracts' => 0,
                'installments' => 0,
                'vouchers' => 0,
                'ledger' => 0,
            ];

            if ($settings = data_get($payload, 'settings')) {
                $this->appSettings()->update([
                    'company_name' => data_get($settings, 'companyName', 'Propify'),
                    'company_phone' => data_get($settings, 'companyPhone'),
                    'company_email' => data_get($settings, 'companyEmail'),
                    'company_address' => data_get($settings, 'companyAddress'),
                    'default_currency' => data_get($settings, 'defaultCurrency', 'دينار'),
                    'default_commission_rate' => (float) data_get($settings, 'defaultCommissionRate', 2),
                ]);
                $summary['settings'] = 1;
            }

            foreach (data_get($payload, 'users', []) as $userData) {
                $email = data_get($userData, 'email');
                if (! $email) {
                    continue;
                }

                $user = User::firstOrNew(['email' => $email]);
                $user->fill([
                    'name' => data_get($userData, 'name', $email),
                    'role' => data_get($userData, 'role', 'sales'),
                    'permissions' => data_get($userData, 'permissions', []),
                ]);
                if (! $user->exists) {
                    $user->password = Hash::make(Str::random(24));
                }
                $user->save();
                $summary['users']++;
            }

            foreach (data_get($payload, 'clients', []) as $clientData) {
                $phone = data_get($clientData, 'phone');
                if (! $phone) {
                    continue;
                }

                Client::updateOrCreate(['phone' => $phone], [
                    'name' => data_get($clientData, 'name', 'عميل'),
                    'role' => data_get($clientData, 'role', 'مشتري'),
                    'national_id' => data_get($clientData, 'nationalId'),
                    'stage' => data_get($clientData, 'stage', 'عميل محتمل'),
                    'source' => data_get($clientData, 'source'),
                ]);
                $summary['clients']++;
            }

            foreach (data_get($payload, 'properties', []) as $propertyData) {
                $code = data_get($propertyData, 'code');
                if (! $code) {
                    continue;
                }

                Property::updateOrCreate(['code' => $code], [
                    'type' => data_get($propertyData, 'type', 'عقار'),
                    'mode' => data_get($propertyData, 'mode', 'بيع'),
                    'province' => data_get($propertyData, 'province', 'بغداد'),
                    'area' => data_get($propertyData, 'area', '-'),
                    'space' => (float) data_get($propertyData, 'space', 1),
                    'rooms' => (int) data_get($propertyData, 'rooms', 0),
                    'price' => $this->money(data_get($propertyData, 'price', 0)),
                    'status' => data_get($propertyData, 'status', 'قيد المراجعة'),
                    'owner' => data_get($propertyData, 'owner', '-'),
                    'negotiable' => (bool) data_get($propertyData, 'negotiable', true),
                ]);
                $summary['properties']++;
            }

            foreach (data_get($payload, 'contracts', []) as $contractData) {
                $code = data_get($contractData, 'code');
                if (! $code) {
                    continue;
                }

                Contract::updateOrCreate(['code' => $code], [
                    'property_code' => data_get($contractData, 'propertyCode', ''),
                    'client' => data_get($contractData, 'client', ''),
                    'kind' => data_get($contractData, 'kind', 'بيع نقدي'),
                    'total' => (float) data_get($contractData, 'total', 0),
                    'paid' => (float) data_get($contractData, 'paid', 0),
                    'due' => (float) data_get($contractData, 'due', 0),
                    'commission' => (float) data_get($contractData, 'commission', 0),
                    'status' => data_get($contractData, 'status', 'نشط'),
                ]);
                $summary['contracts']++;
            }

            foreach (data_get($payload, 'installments', []) as $installmentData) {
                $contractCode = data_get($installmentData, 'contractCode');
                $number = data_get($installmentData, 'number');
                if (! $contractCode || ! $number) {
                    continue;
                }

                Installment::updateOrCreate(['contract_code' => $contractCode, 'number' => $number], [
                    'due_date' => data_get($installmentData, 'dueDate', now()->toDateString()),
                    'amount' => (float) data_get($installmentData, 'amount', 0),
                    'paid_amount' => (float) data_get($installmentData, 'paidAmount', 0),
                    'status' => data_get($installmentData, 'status', 'بانتظار'),
                ]);
                $summary['installments']++;
            }

            foreach (data_get($payload, 'vouchers', []) as $voucherData) {
                $code = data_get($voucherData, 'code');
                if (! $code) {
                    continue;
                }

                Voucher::updateOrCreate(['code' => $code], [
                    'type' => data_get($voucherData, 'type', 'قبض'),
                    'client' => data_get($voucherData, 'client', '-'),
                    'amount' => (float) data_get($voucherData, 'amount', 0),
                    'reason' => data_get($voucherData, 'reason', '-'),
                    'property_code' => data_get($voucherData, 'propertyCode'),
                    'contract_code' => data_get($voucherData, 'contractCode'),
                    'issued_at' => data_get($voucherData, 'issuedAt', now()->toDateString()),
                ]);
                $summary['vouchers']++;
            }

            foreach (data_get($payload, 'ledger', []) as $entryData) {
                $code = data_get($entryData, 'code');
                if (! $code) {
                    continue;
                }

                LedgerEntry::updateOrCreate(['code' => $code], [
                    'direction' => data_get($entryData, 'direction', 'credit'),
                    'amount' => (float) data_get($entryData, 'amount', 0),
                    'description' => data_get($entryData, 'description', '-'),
                    'entry_date' => data_get($entryData, 'entryDate', now()->toDateString()),
                ]);
                $summary['ledger']++;
            }

            return $summary;
        });

        $this->logActivity($request, 'import', 'backup', (string) now()->timestamp, 'استيراد نسخة احتياطية للنظام', $summary);

        return $this->json(['ok' => true, 'summary' => $summary]);
    }

    public function settings(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request)) {
            return $guard;
        }

        return $this->json($this->settingsResource($this->appSettings()));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request, 'settings.update')) {
            return $guard;
        }

        $validator = Validator::make($request->all(), [
            'companyName' => ['required', 'string', 'max:120'],
            'companyPhone' => ['nullable', 'string', 'max:40'],
            'companyEmail' => ['nullable', 'email', 'max:120'],
            'companyAddress' => ['nullable', 'string', 'max:180'],
            'defaultCurrency' => ['required', 'string', 'max:30'],
            'defaultCommissionRate' => ['required', 'numeric', 'min:0', 'max:100'],
        ], $this->messages());

        if ($validator->fails()) {
            return $this->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $settings = $this->appSettings();
        $settings->update($this->settingsData($request));
        $this->logActivity($request, 'update', 'settings', (string) $settings->id, 'تحديث إعدادات المكتب');

        return $this->json($this->settingsResource($settings->refresh()));
    }

    public function financialReport(Request $request)
    {
        if ($guard = $this->guard($request, 'reports.view')) {
            return $guard;
        }

        $ledgerQuery = LedgerEntry::query();
        $this->applyDateRange($ledgerQuery, $request, 'entry_date');

        $entries = $ledgerQuery->get();
        $income = (float) $entries->where('direction', 'credit')->sum('amount');
        $expenses = (float) $entries->where('direction', 'debit')->sum('amount');
        $report = [
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $income - $expenses,
            'contractsTotal' => (float) Contract::sum('total'),
            'contractsPaid' => (float) Contract::sum('paid'),
            'contractsDue' => (float) Contract::sum('due'),
            'officeCommission' => (float) Contract::sum('commission'),
            'vouchersCount' => Voucher::count(),
        ];

        if ($request->query('export') === 'csv') {
            return $this->csvResponse([
                ['البند', 'القيمة'],
                ['الإيرادات', $report['income']],
                ['المصروفات', $report['expenses']],
                ['الرصيد', $report['balance']],
                ['إجمالي العقود', $report['contractsTotal']],
                ['المدفوع من العقود', $report['contractsPaid']],
                ['المتبقي من العقود', $report['contractsDue']],
                ['عمولات المكتب', $report['officeCommission']],
                ['عدد السندات', $report['vouchersCount']],
            ], 'propify-financial-report.csv');
        }

        return $this->json($report);
    }

    public function propertiesReport(Request $request)
    {
        if ($guard = $this->guard($request, 'reports.view')) {
            return $guard;
        }

        $query = Property::query();

        if ($request->filled('status') && $request->query('status') !== 'الكل') {
            $query->where('status', $request->query('status'));
        }

        $properties = $query->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse([
                ['رقم العقار', 'النوع', 'الغرض', 'المحافظة', 'المنطقة', 'المساحة', 'السعر', 'الحالة', 'المالك'],
                ...$properties->map(fn (Property $property) => [
                    $property->code,
                    $property->type,
                    $property->mode,
                    $property->province,
                    $property->area,
                    $property->space,
                    $property->price,
                    $property->status,
                    $property->owner,
                ])->all(),
            ], 'propify-properties-report.csv');
        }

        return $this->json([
            'total' => $properties->count(),
            'totalValue' => (float) $properties->sum('price'),
            'byStatus' => $this->groupCounts($properties, 'status'),
            'byMode' => $this->groupCounts($properties, 'mode'),
            'byProvince' => $this->groupCounts($properties, 'province'),
        ]);
    }

    public function installmentsReport(Request $request)
    {
        if ($guard = $this->guard($request, 'reports.view')) {
            return $guard;
        }

        $query = Installment::query()->orderBy('due_date');
        $this->applyDateRange($query, $request, 'due_date');

        if ($request->filled('status') && $request->query('status') !== 'الكل') {
            $query->where('status', $request->query('status'));
        }

        $installments = $query->get();

        if ($request->query('export') === 'csv') {
            return $this->csvResponse([
                ['العقد', 'رقم القسط', 'تاريخ الاستحقاق', 'المبلغ', 'المدفوع', 'المتبقي', 'الحالة'],
                ...$installments->map(fn (Installment $installment) => [
                    $installment->contract_code,
                    $installment->number,
                    $installment->due_date->format('Y-m-d'),
                    $installment->amount,
                    $installment->paid_amount,
                    (float) $installment->amount - (float) $installment->paid_amount,
                    $installment->status,
                ])->all(),
            ], 'propify-installments-report.csv');
        }

        return $this->json([
            'total' => $installments->count(),
            'amountTotal' => (float) $installments->sum('amount'),
            'paidTotal' => (float) $installments->sum('paid_amount'),
            'remainingTotal' => (float) ($installments->sum('amount') - $installments->sum('paid_amount')),
            'byStatus' => $this->groupCounts($installments, 'status'),
            'upcoming' => $installments->take(8)->map(fn (Installment $installment) => $this->installmentResource($installment))->values(),
        ]);
    }

    public function employeePerformanceReport(Request $request)
    {
        if ($guard = $this->guard($request, 'reports.view')) {
            return $guard;
        }

        $users = User::query()->latest()->get();
        $report = [
            'usersTotal' => $users->count(),
            'byRole' => $this->groupCounts($users, 'role'),
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'permissionsCount' => count($user->permissions ?? []),
            ])->values(),
        ];

        if ($request->query('export') === 'csv') {
            return $this->csvResponse([
                ['الاسم', 'البريد', 'الدور', 'عدد الصلاحيات'],
                ...$users->map(fn (User $user) => [
                    $user->name,
                    $user->email,
                    $user->role,
                    count($user->permissions ?? []),
                ])->all(),
            ], 'propify-employee-performance-report.csv');
        }

        return $this->json($report);
    }

    private function csvResponse(array $rows, string $filename)
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response("\xEF\xBB\xBF".$csv, 200)->withHeaders([
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function documentResponse(string $title, string $body)
    {
        $settings = $this->appSettings();
        $safeTitle = e($title);
        $companyName = e($settings->company_name);
        $companyMeta = collect([$settings->company_phone, $settings->company_email, $settings->company_address])
            ->filter()
            ->map(fn (string $item) => e($item))
            ->implode(' · ');
        $printedAt = now()->format('Y-m-d H:i');
        $html = <<<HTML
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle}</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; color: #111827; margin: 0; padding: 32px; background: #fff; }
        header { border-bottom: 3px solid #147d73; padding-bottom: 14px; margin-bottom: 24px; display: flex; justify-content: space-between; gap: 24px; }
        h1 { margin: 0 0 8px; font-size: 26px; }
        h2 { margin: 28px 0 12px; font-size: 18px; }
        .meta { color: #64748b; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #d1d5db; padding: 10px 12px; text-align: right; }
        th { background: #f3f4f6; }
        td:first-child { width: 32%; background: #f8fafc; font-weight: 700; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-top: 86px; text-align: center; }
        .signatures div { border-top: 1px solid #111827; padding-top: 10px; min-height: 32px; }
        @media print { body { padding: 20px; } button { display: none; } }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>{$companyName}</h1>
            <strong>{$safeTitle}</strong>
            <div class="meta">{$companyMeta}</div>
        </div>
        <div class="meta">تاريخ الطباعة: {$printedAt}</div>
    </header>
    {$body}
    <script>window.addEventListener('load', () => window.print())</script>
</body>
</html>
HTML;

        return response($html, 200)->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function documentTable(array $rows): string
    {
        $htmlRows = collect($rows)->map(fn (array $row) => sprintf(
            '<tr><td>%s</td><td>%s</td></tr>',
            e($row[0]),
            e($row[1]),
        ))->implode('');

        return "<table><tbody>{$htmlRows}</tbody></table>";
    }

    private function signatureBlock(array $labels): string
    {
        $items = collect($labels)->map(fn (string $label) => '<div>'.e($label).'</div>')->implode('');

        return "<div class=\"signatures\">{$items}</div>";
    }

    private function logActivity(Request $request, string $action, ?string $subjectType, ?string $subjectId, string $summary, array $meta = [], ?User $actor = null): void
    {
        $user = $actor ?? $this->userFromRequest($request);

        ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => $summary,
            'meta' => $meta,
        ]);
    }

    private function appSettings(): AppSetting
    {
        return AppSetting::query()->firstOrCreate([], [
            'company_name' => 'Propify',
            'company_phone' => '07700000000',
            'company_email' => 'office@propify.local',
            'company_address' => 'بغداد - العراق',
            'default_currency' => 'دينار',
            'default_commission_rate' => 2,
        ]);
    }

    private function settingsData(Request $request): array
    {
        return [
            'company_name' => $request->string('companyName'),
            'company_phone' => $request->input('companyPhone'),
            'company_email' => $request->input('companyEmail'),
            'company_address' => $request->input('companyAddress'),
            'default_currency' => $request->string('defaultCurrency'),
            'default_commission_rate' => $request->input('defaultCommissionRate'),
        ];
    }

    private function settingsResource(AppSetting $settings): array
    {
        return [
            'companyName' => $settings->company_name,
            'companyPhone' => $settings->company_phone,
            'companyEmail' => $settings->company_email,
            'companyAddress' => $settings->company_address,
            'defaultCurrency' => $settings->default_currency,
            'defaultCommissionRate' => (float) $settings->default_commission_rate,
        ];
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

    private function userRules(?User $user = null): array
    {
        $emailRule = $user ? 'unique:users,email,'.$user->id : 'unique:users,email';

        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'email', $emailRule],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6'],
            'role' => ['required', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ];
    }

    private function userData(Request $request, bool $requirePassword): array
    {
        $data = [
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'role' => $request->string('role'),
            'permissions' => $request->input('permissions', []),
        ];

        if ($requirePassword || $request->filled('password')) {
            $data['password'] = Hash::make($request->string('password'));
        }

        return $data;
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
        $commissionRate = (float) $request->input('commissionRate', $this->appSettings()->default_commission_rate);

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

    private function voucherRules(): array
    {
        return [
            'type' => ['required', 'in:قبض,دفع'],
            'client' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string'],
            'propertyCode' => ['nullable', 'string'],
            'contractCode' => ['nullable', 'string'],
            'issuedAt' => ['nullable', 'date'],
        ];
    }

    private function voucherData(Request $request): array
    {
        return [
            'type' => $request->string('type'),
            'client' => $request->string('client'),
            'amount' => $this->money($request->input('amount')),
            'reason' => $request->string('reason'),
            'property_code' => $request->input('propertyCode'),
            'contract_code' => $request->input('contractCode'),
            'issued_at' => $request->input('issuedAt', Carbon::now()->toDateString()),
        ];
    }

    private function syncVoucherLedger(Voucher $voucher): void
    {
        LedgerEntry::updateOrCreate(
            ['description' => "سند {$voucher->type} {$voucher->code}"],
            [
                'code' => LedgerEntry::where('description', "سند {$voucher->type} {$voucher->code}")->value('code') ?? $this->nextCode(LedgerEntry::class, 'LE', 0),
                'direction' => $voucher->type === 'قبض' ? 'credit' : 'debit',
                'amount' => $voucher->amount,
                'entry_date' => $voucher->issued_at,
            ],
        );
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

    private function guard(Request $request, ?string $permission = null): ?JsonResponse
    {
        $user = $this->userFromRequest($request);

        if (! $user) {
            return $this->json([
                'message' => 'يرجى تسجيل الدخول أولاً.',
                'errors' => ['auth' => ['يرجى تسجيل الدخول أولاً.']],
            ], 401);
        }

        if (! $permission || $user->role === 'system_admin' || in_array($permission, $user->permissions ?? [], true)) {
            return null;
        }

        return $this->json([
            'message' => 'ليست لديك صلاحية لتنفيذ هذا الإجراء.',
            'errors' => ['permission' => ['ليست لديك صلاحية لتنفيذ هذا الإجراء.']],
        ], 403);
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

    private function activityResource(ActivityLog $activity): array
    {
        return [
            'id' => $activity->id,
            'userName' => $activity->user_name ?? 'النظام',
            'action' => $activity->action,
            'subjectType' => $activity->subject_type,
            'subjectId' => $activity->subject_id,
            'summary' => $activity->summary,
            'meta' => $activity->meta ?? [],
            'createdAt' => $activity->created_at?->toDateTimeString(),
        ];
    }
}
