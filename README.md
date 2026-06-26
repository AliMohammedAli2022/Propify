# Propify

Propify منصة ويب وPWA عربية لإدارة مكتب أو شركة عقارات. الهدف ليس عرض العقارات فقط، بل إدارة دورة العمل كاملة: العقارات، العملاء، CRM، العقود، البيع، الإيجار، التقسيط، السندات، الدفعات، التقارير، الصلاحيات، الطباعة، والإشعارات.

## التقنية

- Frontend: Vue 3 + Vite + PWA
- Backend: Laravel API
- Database: MySQL
- اللغة والاتجاه: عربي كامل RTL

> ملاحظة تنفيذية: يوجد الآن Backend Laravel حقيقي داخل `laravel-api/`. بقيت نسخة Node التطويرية داخل `backend/` كمرجع وتشغيل احتياطي.

## الموجود حالياً

- واجهة Vue 3 أولية داخل `frontend/`.
- Dashboard عربية RTL بثيم فاتح/داكن.
- أقسام للعقارات، العملاء، العقود، الحسابات، التقارير، الإشعارات، والصلاحيات.
- نموذج إضافة عقار مع تحقق من المنطقة، المالك، المساحة، والسعر.
- نموذج إضافة عميل CRM مع تحقق من رقم الهاتف العراقي ورقم البطاقة الوطنية.
- حفظ محلي مؤقت للعقارات والعملاء حتى قبل ربط Laravel API.
- Backend Laravel + MySQL يعمل على `http://127.0.0.1:8000/api`.
- Backend Node التطويري ما زال موجوداً على `backend/` كنسخة احتياطية.
- API للعقود والأقساط والسندات ودفتر الأستاذ المالي المبسط.
- نموذج إنشاء عقد من الواجهة مع حساب المتبقي والعمولة وإنشاء أقساط التقسيط.
- طباعة عقد مبدئية من بطاقة العقد.
- نموذج سند قبض/دفع مرتبط بالـ API مع تحديث رصيد المكتب.
- بحث وفلترة حسب حالة العقار وتصدير CSV قابل للفتح عبر Excel.
- Manifest وService Worker كبداية PWA.
- وثائق backend وdeployment ومخطط قاعدة البيانات وعقد API داخل `docs/`.

## التشغيل

تشغيل Laravel API:

```bash
cd laravel-api
php artisan serve --host=127.0.0.1 --port=8000
```

تشغيل API التطويري الاحتياطي:

```bash
cd backend
npm run dev
```

تشغيل الواجهة:

```bash
cd frontend
npm install
npm run dev
```

## البناء

```bash
cd frontend
npm run build
```

## ملاحظات

لم يتم توليد مشروع Laravel محلياً لأن PHP وComposer غير مثبتين في البيئة الحالية. عند توفرهما، اتبع [Backend Blueprint](docs/backend-blueprint.md)، وراجع [Database Schema](docs/database-schema.sql)، و[API Contract](docs/api-contract.md) لإنشاء API وربطه بالواجهة.
