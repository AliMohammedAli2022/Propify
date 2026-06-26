# Propify Backend Blueprint

الترشيح التقني: Laravel API + MySQL + Vue 3 PWA.

تم إنشاء Backend Laravel فعلي داخل `laravel-api/` متصل بقاعدة MySQL `propify`. بقي Backend التطوير داخل `backend/` كمرجع احتياطي أثناء الانتقال.

أضيفت مصادقة مبدئية تعتمد على token محفوظ في جدول المستخدمين. يمكن لاحقاً استبدالها بـ Laravel Sanctum عند توسيع صلاحيات المستخدمين والجلسات.

أضيفت إدارة مستخدمين عبر `GET /api/users` و`POST /api/users`، مع كتالوج أدوار وصلاحيات مستقل عبر جداول `roles`, `permissions`, و`permission_role` وendpoint `GET /api/access-control`.

## المرحلة 1

- إنشاء مشروع Laravel داخل `backend/` عند توفر PHP وComposer.
- إعداد MySQL، Sanctum API auth، وCORS للواجهة.
- إضافة أدوار وصلاحيات دقيقة: مدير النظام، مدير المكتب، موظف مبيعات، محاسب، مدخل بيانات، عميل، مشرف عقارات.
- دعم RTL في الرسائل والنصوص وValidation errors.

## الكيانات الأساسية

- يوجد مخطط SQL أولي في `docs/database-schema.sql` ليكون مرجعاً مباشراً عند إنشاء migrations.
- يوجد عقد API أولي في `docs/api-contract.md` لتثبيت شكل الطلبات والاستجابات بين Laravel وVue.
- `users`: بيانات الدخول، الدور، الحالة، الصورة.
- `roles`, `permissions`, `permission_role`: صلاحيات دقيقة مثل إضافة عقار وطباعة عقد وإدارة السندات.
- `clients`: الاسم، الهاتف العراقي، العنوان، رقم البطاقة الوطنية، مصدر العميل، الملاحظات.
- `properties`: رقم داخلي مثل `PR-2026-000145`، النوع، الحالة، الغرض، المحافظة، المنطقة، المساحة، السعر، الوصف، الملاحظات الداخلية.
- `property_media`: صور، فيديو اختياري، مستندات.
- `contracts`: رقم داخلي مثل `CT-2026-000044`، النوع، العقار، الأطراف، السعر، المقدم، المتبقي، العمولة، تاريخ البداية والنهاية.
- `installments`: العقد، رقم القسط، تاريخ الاستحقاق، المبلغ، حالة الدفع.
- `vouchers`: سند قبض وسند دفع، المبلغ، السبب، الطرف، العقار، العقد.
- `ledger_entries`: دفتر أستاذ مبسط لكل حركة مالية.
- `notifications`: نوع الإشعار، المستلم، المحتوى، حالة القراءة.

## Validation

- الهاتف العراقي: `^(075|077|078|079)[0-9]{8}$`
- رقم البطاقة الوطنية: 12 خانة على الأقل، حروف وأرقام فقط.
- السعر والمساحة: مطلوبان، رقميان، أكبر من صفر.
- الصور: `jpg,png,webp`، حجم حتى 5MB، حد أقصى 20 صورة للعقار.
- رسائل الأخطاء تظهر أسفل الحقول باللون الأحمر الهادئ، كما هو مطبق حالياً في واجهة Vue.

## منطق أرقام النظام

- العقارات: `PR-{year}-{sequence}` مثل `PR-2026-000145`.
- العقود: `CT-{year}-{sequence}` مثل `CT-2026-000044`.
- السندات: `RV-{year}-{sequence}` لسند القبض و`PV-{year}-{sequence}` لسند الدفع.
- يجب حفظ التسلسل في قاعدة البيانات وليس حسابه من عدد السجلات فقط في بيئة الإنتاج.

## API endpoints المقترحة

- `POST /api/auth/login`
- `GET /api/dashboard`
- `GET|POST /api/properties`
- `GET|PUT|DELETE /api/properties/{property}`
- `POST /api/properties/{property}/approve`
- `GET|POST /api/clients`
- `GET|POST /api/contracts`
- `POST /api/contracts/{contract}/print`
- `GET|POST /api/vouchers`
- `GET /api/reports/financial`
- `GET /api/reports/properties`
- `GET /api/notifications`

## أوامر البدء عند توفر PHP وComposer

```bash
composer create-project laravel/laravel backend
cd backend
composer require laravel/sanctum
php artisan sanctum:install
php artisan make:model Property -mcr
php artisan make:model Client -mcr
php artisan make:model Contract -mcr
php artisan make:model Voucher -mcr
php artisan make:model LedgerEntry -m
```
## Property Media Storage

- `property_media` stores uploaded property images and documents.
- Files are saved to the Laravel `public` disk under `properties/{propertyCode}`.
- Run `php artisan storage:link` after deployment so URLs under `/storage/...` resolve correctly.
