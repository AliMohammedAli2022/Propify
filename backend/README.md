# Propify Backend Runtime

بيئة API جاهزة للتطوير تعمل بـ Node.js بدون حزم خارجية. الهدف منها تشغيل Backend فعلي الآن بنفس مفاهيم Laravel API إلى حين توفر PHP وComposer.

## التشغيل

```bash
cd backend
npm run dev
```

الرابط:

```text
http://127.0.0.1:8787/api
```

## Endpoints

- `GET /api/health`
- `GET /api/dashboard`
- `GET /api/properties`
- `POST /api/properties`
- `GET /api/clients`
- `POST /api/clients`
- `GET /api/contracts`
- `POST /api/contracts`
- `GET /api/installments`
- `GET /api/vouchers`
- `POST /api/vouchers`
- `GET /api/ledger`

البيانات تحفظ في:

```text
backend/data/db.json
```

هذا السيرفر مؤقت للتطوير، وعقده موثق في `docs/api-contract.md` ليتم نقله لاحقاً إلى Laravel.
