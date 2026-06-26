# Deployment Notes

## الدومينات المقترحة

- `propify.com`: واجهة العملاء العامة لاحقاً.
- `app.propify.com`: لوحة إدارة Vue PWA.
- `api.propify.com`: Laravel API.
- `storage.propify.com`: الصور والملفات إذا سمحت الاستضافة.

## cPanel Shared Hosting

المسار المقترح:

```text
GitHub Repository
  -> GitHub Actions
  -> FTP / SFTP / SSH
  -> cPanel
  -> public_html أو subdomain
  -> Propify.com
```

قبل النشر يجب التأكد من:

- إصدار PHP مدعوم من Laravel.
- توفر Extensions المطلوبة.
- إمكانية توجيه Document Root إلى مجلد `public`.
- توفر MySQL ومهام cron للـ Laravel scheduler.
- مساحة تخزين كافية للصور والمستندات.

## تشغيل الواجهة

```bash
cd frontend
npm install
npm run dev
```

## بناء الواجهة للإنتاج

```bash
cd frontend
npm run build
```
