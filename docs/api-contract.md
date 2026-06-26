# Propify API Contract

هذا العقد يحدد شكل الـ API المتوقع من Laravel حتى تبقى واجهة Vue والـ Backend متفقين.

يوجد تطبيق تطويري مؤقت لهذا العقد داخل `backend/server.js` يعمل بـ Node.js بدون حزم خارجية.

## Authentication

`POST /api/auth/login`

Request:

```json
{
  "email": "admin@propify.local",
  "password": "password"
}
```

`GET /api/auth/me`

Requires:

```text
Authorization: Bearer {token}
```

`POST /api/auth/logout`

## Users

`GET /api/users`

`POST /api/users`

```json
{
  "name": "موظف المبيعات",
  "email": "sales@propify.local",
  "password": "password",
  "role": "sales",
  "permissions": ["properties.create", "clients.manage"]
}
```

Response:

```json
{
  "id": 2,
  "name": "موظف المبيعات",
  "email": "sales@propify.local",
  "role": "sales",
  "permissions": ["properties.create", "clients.manage"]
}
```

Response:

```json
{
  "token": "plain-text-token",
  "user": {
    "id": 1,
    "name": "علي محمد",
    "role": "system_admin",
    "permissions": ["properties.create", "contracts.print"]
  }
}
```

## Dashboard

`GET /api/dashboard`

Response:

```json
{
  "properties_total": 248,
  "properties_available": 132,
  "office_profit": 42800000,
  "installments_due": 16,
  "installments_late": 5
}
```

## Properties

`GET /api/properties?search=PR-2026&status=available`

`POST /api/properties`

```json
{
  "type": "دار سكنية",
  "purpose": "sale",
  "province": "بغداد",
  "area": "المنصور",
  "space": 250,
  "rooms": 4,
  "price": 120000000,
  "negotiable": true,
  "owner_client_id": 1,
  "description": "وصف العقار",
  "internal_notes": "ملاحظة داخلية"
}
```

Validation:

- `price`: مطلوب، رقمي، أكبر من صفر.
- `space`: مطلوب، رقمي، أكبر من صفر.
- `images`: `jpg,png,webp`، حد 5MB للصورة، حد 20 صورة للعقار.

## Clients

`GET /api/clients?search=0770`

`POST /api/clients`

```json
{
  "name": "أحمد علي",
  "role": "buyer",
  "phone": "07701234567",
  "national_id": "A12345678901",
  "source": "إعلان ممول",
  "stage": "تفاوض"
}
```

Validation:

- `phone`: يطابق `^(075|077|078|079)[0-9]{8}$`.
- `national_id`: 12 خانة أو أكثر، حروف وأرقام فقط.

## Contracts

`POST /api/contracts`

```json
{
  "property_id": 1,
  "type": "installment_sale",
  "seller_client_id": 3,
  "buyer_client_id": 1,
  "total_amount": 150000000,
  "down_payment": 30000000,
  "installments_count": 24,
  "office_commission_rate": 2,
  "starts_at": "2026-07-01"
}
```

Expected behavior:

- إنشاء رقم عقد مثل `CT-2026-000044`.
- حساب المتبقي والعمولة.
- إنشاء جدول أقساط تلقائي عند البيع بالتقسيط.
- واجهة Vue تستخدم هذا endpoint من نموذج "إنشاء عقد جديد"، وتحدث `GET /api/installments` بعد نجاح إنشاء عقد تقسيط.

## Vouchers

`GET /api/vouchers`

`POST /api/vouchers`

```json
{
  "type": "receipt",
  "client_id": 1,
  "property_id": 1,
  "contract_id": 1,
  "amount": 2000000,
  "reason": "مقدم شراء عقار",
  "issued_at": "2026-06-26"
}
```

Expected behavior:

- إنشاء سند قبض أو دفع.
- إنشاء قيد Ledger مرتبط بالسند.

## Installments

`GET /api/installments`

Response:

```json
[
  {
    "contractCode": "CT-2026-000045",
    "number": 1,
    "dueDate": "2026-07-01",
    "amount": 5000000,
    "paidAmount": 0,
    "status": "مستحق"
  }
]
```

## Ledger

`GET /api/ledger`

يعيد قيود الدفتر المالي المبسط الناتجة من السندات.

## Notifications

`GET /api/notifications`

Returns generated operational alerts from due installments, pending properties, open contract balances, and recent vouchers.

Response item:

```json
{
  "id": "installment-CT-2026-000045-1",
  "type": "installment",
  "severity": "warning",
  "title": "قسط قريب الاستحقاق",
  "message": "القسط 1 للعقد CT-2026-000045 يستحق في 2026-07-01.",
  "createdAt": "2026-06-26 12:00:00"
}
```

## Reports

- `GET /api/reports/properties`
- `GET /api/reports/financial`
- `GET /api/reports/installments`
- `GET /api/reports/employee-performance`

كل تقرير يدعم:

- `from`
- `to`
- `status`
- `export=xlsx`

Implemented report responses:

- `financial`: `income`, `expenses`, `balance`, `contractsTotal`, `contractsPaid`, `contractsDue`, `officeCommission`, `vouchersCount`.
- `properties`: `total`, `totalValue`, `byStatus`, `byMode`, `byProvince`.
- `installments`: `total`, `amountTotal`, `paidTotal`, `remainingTotal`, `byStatus`, `upcoming`.
- `employee-performance`: `usersTotal`, `byRole`, `users`.

## Property Media

`GET /api/properties/{code}/media`

Returns uploaded files for one property.

`POST /api/properties/{code}/media`

Request:

```text
Content-Type: multipart/form-data
files[]: jpg,jpeg,png,webp,pdf,doc,docx
```

Limits:

- Up to 20 files per request.
- Up to 5MB per file.
