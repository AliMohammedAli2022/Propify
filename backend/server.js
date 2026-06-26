import { createServer } from 'node:http'
import { readFile, writeFile, mkdir } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const DATA_DIR = join(__dirname, 'data')
const DATA_FILE = join(DATA_DIR, 'db.json')
const PORT = Number(process.env.PORT || 8787)

const seeds = {
  properties: [
    {
      code: 'PR-2026-000145',
      type: 'دار سكنية',
      mode: 'بيع',
      province: 'بغداد',
      area: 'المنصور',
      space: 250,
      rooms: 4,
      price: '120,000,000',
      status: 'متاح',
      owner: 'أحمد علي',
      negotiable: true,
    },
    {
      code: 'PR-2026-000146',
      type: 'شقة',
      mode: 'إيجار',
      province: 'بغداد',
      area: 'زيونة',
      space: 140,
      rooms: 3,
      price: '700,000',
      status: 'محجوز',
      owner: 'محمد حسن',
      negotiable: false,
    },
    {
      code: 'PR-2026-000147',
      type: 'أرض',
      mode: 'بيع بالتقسيط',
      province: 'بغداد',
      area: 'الجادرية',
      space: 300,
      rooms: 0,
      price: '150,000,000',
      status: 'قيد المراجعة',
      owner: 'سارة كريم',
      negotiable: true,
    },
  ],
  clients: [
    { name: 'أحمد علي', role: 'مشتري', phone: '07701234567', nationalId: 'A12345678901', stage: 'تفاوض', source: 'إعلان ممول' },
    { name: 'محمد حسن', role: 'مؤجر', phone: '07801234567', nationalId: 'B12345678901', stage: 'عقد نشط', source: 'توصية' },
    { name: 'سارة كريم', role: 'مالك عقار', phone: '07501234567', nationalId: 'C12345678901', stage: 'مراجعة عقار', source: 'الموقع' },
  ],
  contracts: [
    { code: 'CT-2026-000044', propertyCode: 'PR-2026-000145', client: 'أحمد علي', kind: 'بيع نقدي', total: 120000000, paid: 120000000, due: 0, commission: 2400000, status: 'مكتمل' },
    { code: 'CT-2026-000045', propertyCode: 'PR-2026-000147', client: 'سارة كريم', kind: 'تقسيط', total: 150000000, paid: 30000000, due: 120000000, commission: 3000000, status: 'نشط' },
    { code: 'CT-2026-000046', propertyCode: 'PR-2026-000146', client: 'محمد حسن', kind: 'إيجار', total: 8400000, paid: 1400000, due: 7000000, commission: 700000, status: 'شهري' },
  ],
  installments: [
    { contractCode: 'CT-2026-000045', number: 1, dueDate: '2026-07-01', amount: 5000000, paidAmount: 0, status: 'مستحق' },
    { contractCode: 'CT-2026-000045', number: 2, dueDate: '2026-08-01', amount: 5000000, paidAmount: 0, status: 'بانتظار' },
    { contractCode: 'CT-2026-000045', number: 3, dueDate: '2026-09-01', amount: 5000000, paidAmount: 0, status: 'بانتظار' },
  ],
  vouchers: [
    { code: 'RV-2026-000001', type: 'قبض', client: 'أحمد علي', amount: 2000000, reason: 'مقدم شراء عقار', propertyCode: 'PR-2026-000145', contractCode: 'CT-2026-000044', issuedAt: '2026-06-26' },
    { code: 'PV-2026-000001', type: 'دفع', client: 'محمد حسن', amount: 500000, reason: 'مصاريف إعلان وتصوير عقار', propertyCode: 'PR-2026-000146', contractCode: '', issuedAt: '2026-06-26' },
  ],
  ledger: [
    { code: 'LE-2026-000001', direction: 'credit', amount: 2000000, description: 'سند قبض RV-2026-000001', entryDate: '2026-06-26' },
    { code: 'LE-2026-000002', direction: 'debit', amount: 500000, description: 'سند دفع PV-2026-000001', entryDate: '2026-06-26' },
  ],
}

const jsonResponse = (response, status, body) => {
  response.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET,POST,OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type,Authorization',
  })
  response.end(JSON.stringify(body))
}

const readBody = async (request) => {
  const chunks = []
  for await (const chunk of request) chunks.push(chunk)
  const raw = Buffer.concat(chunks).toString('utf8')
  return raw ? JSON.parse(raw) : {}
}

const ensureDatabase = async () => {
  await mkdir(DATA_DIR, { recursive: true })
  if (!existsSync(DATA_FILE)) {
    await writeFile(DATA_FILE, JSON.stringify(seeds, null, 2), 'utf8')
  }
}

const readDatabase = async () => {
  await ensureDatabase()
  const database = JSON.parse(await readFile(DATA_FILE, 'utf8'))
  let changed = false
  for (const [key, value] of Object.entries(seeds)) {
    if (!Array.isArray(database[key])) {
      database[key] = value
      changed = true
    }
  }
  if (changed) await writeDatabase(database)
  return database
}

const writeDatabase = async (database) => {
  await writeFile(DATA_FILE, JSON.stringify(database, null, 2), 'utf8')
}

const onlyDigits = (value) => String(value ?? '').replaceAll(',', '')
const isPositiveNumber = (value) => Number.isFinite(Number(onlyDigits(value))) && Number(onlyDigits(value)) > 0
const iraqiPhonePattern = /^(075|077|078|079)[0-9]{8}$/
const nationalIdPattern = /^[A-Za-z0-9]{12,}$/

const nextPropertyCode = (properties) => {
  return nextCode(properties, 'code', 'PR', 144)
}

const nextCode = (records, field, prefix, start = 0) => {
  const currentYear = new Date().getFullYear()
  const maxSequence = records.reduce((max, record) => {
    const match = String(record[field]).match(new RegExp(`${prefix}-\\d{4}-(\\d{6})`))
    return match ? Math.max(max, Number(match[1])) : max
  }, start)
  return `${prefix}-${currentYear}-${String(maxSequence + 1).padStart(6, '0')}`
}

const validateProperty = (property) => {
  const errors = {}
  if (!String(property.area ?? '').trim()) errors.area = 'يرجى إدخال المنطقة.'
  if (!String(property.owner ?? '').trim()) errors.owner = 'يرجى إدخال اسم المالك.'
  if (!isPositiveNumber(property.space)) errors.space = 'المساحة إجبارية ويجب أن تكون أكبر من صفر.'
  if (!isPositiveNumber(property.price)) errors.price = 'السعر إجباري ويجب أن يكون رقماً أكبر من صفر.'
  return errors
}

const validateClient = (client) => {
  const errors = {}
  if (!String(client.name ?? '').trim()) errors.name = 'يرجى إدخال اسم العميل.'
  if (!iraqiPhonePattern.test(String(client.phone ?? ''))) errors.phone = 'يرجى إدخال رقم هاتف عراقي صحيح مكوّن من 11 رقماً.'
  if (!nationalIdPattern.test(String(client.nationalId ?? ''))) errors.nationalId = 'رقم البطاقة يجب ألا يقل عن 12 خانة ويسمح بالحروف والأرقام فقط.'
  return errors
}

const validateContract = (contract) => {
  const errors = {}
  if (!String(contract.propertyCode ?? '').trim()) errors.propertyCode = 'يرجى اختيار رقم العقار.'
  if (!String(contract.client ?? '').trim()) errors.client = 'يرجى إدخال اسم العميل.'
  if (!isPositiveNumber(contract.total)) errors.total = 'قيمة العقد يجب أن تكون أكبر من صفر.'
  if (Number(onlyDigits(contract.paid || 0)) > Number(onlyDigits(contract.total))) errors.paid = 'المدفوع لا يمكن أن يتجاوز قيمة العقد.'
  return errors
}

const validateVoucher = (voucher) => {
  const errors = {}
  if (!['قبض', 'دفع'].includes(voucher.type)) errors.type = 'نوع السند يجب أن يكون قبض أو دفع.'
  if (!String(voucher.client ?? '').trim()) errors.client = 'يرجى إدخال اسم الطرف.'
  if (!isPositiveNumber(voucher.amount)) errors.amount = 'مبلغ السند يجب أن يكون أكبر من صفر.'
  if (!String(voucher.reason ?? '').trim()) errors.reason = 'يرجى إدخال سبب السند.'
  return errors
}

const filterRecords = (records, search, status) => {
  const query = String(search ?? '').trim().toLowerCase()
  return records.filter((record) => {
    const matchesStatus = !status || status === 'الكل' || record.status === status
    const searchable = Object.values(record).join(' ').toLowerCase()
    return matchesStatus && (!query || searchable.includes(query))
  })
}

const routes = {
  'GET /api/health': async () => ({ ok: true, service: 'propify-api', storage: DATA_FILE }),
  'GET /api/dashboard': async () => {
    const database = await readDatabase()
    const available = database.properties.filter((property) => property.status === 'متاح').length
    const reserved = database.properties.filter((property) => property.status === 'محجوز').length
    const officeProfit = database.contracts.reduce((sum, contract) => sum + Number(contract.commission || 0), 0)
    const installmentsDue = database.installments.filter((installment) => installment.status === 'مستحق').length
    const installmentsLate = database.installments.filter((installment) => installment.status === 'متأخر').length
    return {
      properties_total: database.properties.length,
      properties_available: available,
      properties_reserved: reserved,
      clients_total: database.clients.length,
      contracts_total: database.contracts.length,
      vouchers_total: database.vouchers.length,
      office_profit: officeProfit,
      installments_due: installmentsDue,
      installments_late: installmentsLate,
    }
  },
  'GET /api/properties': async ({ url }) => {
    const database = await readDatabase()
    return filterRecords(database.properties, url.searchParams.get('search'), url.searchParams.get('status'))
  },
  'POST /api/properties': async ({ request }) => {
    const database = await readDatabase()
    const payload = await readBody(request)
    const errors = validateProperty(payload)
    if (Object.keys(errors).length > 0) return { status: 422, body: { message: 'Validation failed', errors } }

    const property = {
      code: nextPropertyCode(database.properties),
      type: payload.type,
      mode: payload.mode,
      province: payload.province,
      area: payload.area,
      space: Number(payload.space),
      rooms: Number(payload.rooms || 0),
      price: Number(onlyDigits(payload.price)).toLocaleString('en-US'),
      status: payload.status || 'قيد المراجعة',
      owner: payload.owner,
      negotiable: Boolean(payload.negotiable),
    }
    database.properties.unshift(property)
    await writeDatabase(database)
    return { status: 201, body: property }
  },
  'GET /api/clients': async ({ url }) => {
    const database = await readDatabase()
    return filterRecords(database.clients, url.searchParams.get('search'))
  },
  'GET /api/contracts': async ({ url }) => {
    const database = await readDatabase()
    return filterRecords(database.contracts, url.searchParams.get('search'), url.searchParams.get('status'))
  },
  'POST /api/contracts': async ({ request }) => {
    const database = await readDatabase()
    const payload = await readBody(request)
    const errors = validateContract(payload)
    if (Object.keys(errors).length > 0) return { status: 422, body: { message: 'Validation failed', errors } }

    const total = Number(onlyDigits(payload.total))
    const paid = Number(onlyDigits(payload.paid || 0))
    const commissionRate = Number(payload.commissionRate || 0)
    const contract = {
      code: nextCode(database.contracts, 'code', 'CT', 43),
      propertyCode: payload.propertyCode,
      client: payload.client,
      kind: payload.kind || 'بيع نقدي',
      total,
      paid,
      due: total - paid,
      commission: Math.round(total * (commissionRate / 100)),
      status: payload.status || 'نشط',
    }

    database.contracts.unshift(contract)

    if (contract.kind === 'تقسيط') {
      const installmentsCount = Number(payload.installmentsCount || 1)
      const amount = Math.round(contract.due / installmentsCount)
      for (let index = 1; index <= installmentsCount; index += 1) {
        database.installments.push({
          contractCode: contract.code,
          number: index,
          dueDate: new Date(Date.UTC(new Date().getFullYear(), new Date().getMonth() + index, 1)).toISOString().slice(0, 10),
          amount,
          paidAmount: 0,
          status: index === 1 ? 'مستحق' : 'بانتظار',
        })
      }
    }

    await writeDatabase(database)
    return { status: 201, body: contract }
  },
  'GET /api/installments': async ({ url }) => {
    const database = await readDatabase()
    return filterRecords(database.installments, url.searchParams.get('search'), url.searchParams.get('status'))
  },
  'GET /api/vouchers': async ({ url }) => {
    const database = await readDatabase()
    return filterRecords(database.vouchers, url.searchParams.get('search'), url.searchParams.get('type'))
  },
  'POST /api/vouchers': async ({ request }) => {
    const database = await readDatabase()
    const payload = await readBody(request)
    const errors = validateVoucher(payload)
    if (Object.keys(errors).length > 0) return { status: 422, body: { message: 'Validation failed', errors } }

    const prefix = payload.type === 'قبض' ? 'RV' : 'PV'
    const voucher = {
      code: nextCode(database.vouchers.filter((item) => item.type === payload.type), 'code', prefix, 0),
      type: payload.type,
      client: payload.client,
      amount: Number(onlyDigits(payload.amount)),
      reason: payload.reason,
      propertyCode: payload.propertyCode || '',
      contractCode: payload.contractCode || '',
      issuedAt: payload.issuedAt || new Date().toISOString().slice(0, 10),
    }

    const ledgerEntry = {
      code: nextCode(database.ledger, 'code', 'LE', 0),
      direction: voucher.type === 'قبض' ? 'credit' : 'debit',
      amount: voucher.amount,
      description: `سند ${voucher.type} ${voucher.code}`,
      entryDate: voucher.issuedAt,
    }

    database.vouchers.unshift(voucher)
    database.ledger.unshift(ledgerEntry)
    await writeDatabase(database)
    return { status: 201, body: voucher }
  },
  'GET /api/ledger': async () => {
    const database = await readDatabase()
    return database.ledger
  },
  'POST /api/clients': async ({ request }) => {
    const database = await readDatabase()
    const payload = await readBody(request)
    const errors = validateClient(payload)
    if (Object.keys(errors).length > 0) return { status: 422, body: { message: 'Validation failed', errors } }

    const client = {
      name: payload.name,
      role: payload.role,
      phone: payload.phone,
      nationalId: payload.nationalId,
      stage: payload.stage || 'عميل محتمل',
      source: payload.source || 'الموقع',
    }
    database.clients.unshift(client)
    await writeDatabase(database)
    return { status: 201, body: client }
  },
}

const server = createServer(async (request, response) => {
  if (request.method === 'OPTIONS') {
    return jsonResponse(response, 204, {})
  }

  const url = new URL(request.url, `http://${request.headers.host}`)
  const route = routes[`${request.method} ${url.pathname}`]

  if (!route) {
    return jsonResponse(response, 404, { message: 'Not found' })
  }

  try {
    const result = await route({ request, url })
    const status = result?.status || 200
    const body = result?.body || result
    return jsonResponse(response, status, body)
  } catch (error) {
    return jsonResponse(response, 500, { message: 'Server error', detail: error.message })
  }
})

await ensureDatabase()
server.listen(PORT, '127.0.0.1', () => {
  console.log(`Propify API ready at http://127.0.0.1:${PORT}/api`)
})
