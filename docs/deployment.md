# Propify Deployment Guide

هذا الدليل يجهز نشر Propify كواجهة Vue PWA منفصلة عن Laravel API.

## الدومينات المقترحة

- `app.propify.com`: لوحة إدارة Vue PWA.
- `api.propify.com`: Laravel API.
- `propify.com`: واجهة العملاء العامة لاحقاً.

## متطلبات الخادم

- PHP 8.3 أو أحدث.
- Composer 2.
- MySQL 8 أو MariaDB حديثة.
- Node.js 20 أو أحدث لبناء الواجهة.
- إمكانية توجيه Document Root لمجلد `laravel-api/public`.
- صلاحية تنفيذ أوامر Artisan.
- Cron job لتشغيل Laravel scheduler كل دقيقة.

## إعداد Laravel API

انسخ المشروع إلى الخادم، ثم داخل `laravel-api`:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

اضبط قيم الإنتاج في `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.propify.com
FRONTEND_URL=https://app.propify.com
CORS_ALLOWED_ORIGINS=https://app.propify.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=propify
DB_USERNAME=propify_user
DB_PASSWORD=change-me

FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

ثم نفذ:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## إعداد الواجهة

داخل `frontend`:

```bash
npm ci
```

أنشئ ملف `.env.production`:

```dotenv
VITE_API_BASE_URL=https://api.propify.com/api
```

ثم ابن الواجهة:

```bash
npm run build
```

ارفع محتويات `frontend/dist` إلى Document Root الخاص بـ `app.propify.com`.

## Cron

أضف مهمة cron على الخادم:

```bash
* * * * * cd /path/to/Propify/laravel-api && php artisan schedule:run >> /dev/null 2>&1
```

## صلاحيات الملفات

يجب أن يستطيع PHP الكتابة في:

```text
laravel-api/storage
laravel-api/bootstrap/cache
```

وعند رفع صور العقارات والمستندات يجب أن يعمل الرابط:

```text
https://api.propify.com/storage/properties/{propertyCode}/...
```

## فحص ما بعد النشر

تحقق من API:

```bash
curl https://api.propify.com/api/health
```

ثم من المتصفح:

- افتح `https://app.propify.com`.
- سجل الدخول بالحساب الافتراضي أو حساب المدير الذي أنشأته.
- افتح صفحة العقارات والعملاء والعقود للتأكد من أن CORS يعمل.
- ارفع صورة لعقار للتأكد من `storage:link`.
- صدّر نسخة احتياطية ثم جرّب استيرادها على بيئة اختبار قبل استخدامها على الإنتاج.

## تحديث نسخة منشورة

داخل `laravel-api`:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

داخل `frontend`:

```bash
npm ci
npm run build
```

ثم ارفع محتويات `frontend/dist` الجديدة.

## ملاحظات cPanel

- إن لم تستطع توجيه Document Root مباشرة إلى `laravel-api/public`، اجعل subdomain `api.propify.com` يشير إلى هذا المجلد.
- لا تضع ملفات Laravel الحساسة داخل `public_html` مكشوفة مباشرة.
- لا ترفع ملف `.env` إلى GitHub.
- استخدم HTTPS دائماً لأن الواجهة ترسل token في Authorization header.
