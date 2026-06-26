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
  return JSON.parse(await readFile(DATA_FILE, 'utf8'))
}

const writeDatabase = async (database) => {
  await writeFile(DATA_FILE, JSON.stringify(database, null, 2), 'utf8')
}

const onlyDigits = (value) => String(value ?? '').replaceAll(',', '')
const isPositiveNumber = (value) => Number.isFinite(Number(onlyDigits(value))) && Number(onlyDigits(value)) > 0
const iraqiPhonePattern = /^(075|077|078|079)[0-9]{8}$/
const nationalIdPattern = /^[A-Za-z0-9]{12,}$/

const nextPropertyCode = (properties) => {
  const currentYear = new Date().getFullYear()
  const maxSequence = properties.reduce((max, property) => {
    const match = String(property.code).match(/PR-\d{4}-(\d{6})/)
    return match ? Math.max(max, Number(match[1])) : max
  }, 144)
  return `PR-${currentYear}-${String(maxSequence + 1).padStart(6, '0')}`
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
    return {
      properties_total: database.properties.length,
      properties_available: available,
      properties_reserved: reserved,
      clients_total: database.clients.length,
      office_profit: 42800000,
      installments_due: 16,
      installments_late: 5,
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
