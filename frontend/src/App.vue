<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
  AlertCircle,
  Bell,
  Building2,
  CalendarClock,
  ChevronLeft,
  ChevronRight,
  CircleDollarSign,
  ClipboardCheck,
  Download,
  FileCheck2,
  FileText,
  Home,
  Moon,
  PanelRightClose,
  PanelRightOpen,
  Plus,
  Printer,
  Save,
  Search,
  Settings,
  ShieldCheck,
  Sun,
  Users,
  WalletCards,
} from '@lucide/vue'

const darkMode = ref(false)
const collapsed = ref(false)
const activeSection = ref('dashboard')
const welcomeVisible = ref(true)
const searchQuery = ref('')
const statusFilter = ref('الكل')
const apiOnline = ref(false)

setTimeout(() => {
  welcomeVisible.value = false
}, 2200)

const navItems = [
  { id: 'dashboard', label: 'لوحة المؤشرات', icon: Home },
  { id: 'properties', label: 'العقارات', icon: Building2 },
  { id: 'clients', label: 'العملاء و CRM', icon: Users },
  { id: 'contracts', label: 'العقود والتقسيط', icon: FileCheck2 },
  { id: 'finance', label: 'الحسابات والسندات', icon: WalletCards },
  { id: 'reports', label: 'التقارير', icon: ClipboardCheck },
  { id: 'permissions', label: 'الصلاحيات', icon: ShieldCheck },
  { id: 'settings', label: 'الإعدادات', icon: Settings },
]

const propertySeeds = [
  {
    code: 'PR-2026-000145',
    type: 'دار سكنية',
    mode: 'بيع',
    area: 'المنصور',
    price: '120,000,000',
    status: 'متاح',
    owner: 'أحمد علي',
  },
  {
    code: 'PR-2026-000146',
    type: 'شقة',
    mode: 'إيجار',
    area: 'زيونة',
    price: '700,000',
    status: 'محجوز',
    owner: 'محمد حسن',
  },
  {
    code: 'PR-2026-000147',
    type: 'أرض',
    mode: 'بيع بالتقسيط',
    area: 'الجادرية',
    price: '150,000,000',
    status: 'قيد المراجعة',
    owner: 'سارة كريم',
  },
]

const clientSeeds = [
  { name: 'أحمد علي', role: 'مشتري', phone: '07701234567', stage: 'تفاوض', source: 'إعلان ممول' },
  { name: 'محمد حسن', role: 'مؤجر', phone: '07801234567', stage: 'عقد نشط', source: 'توصية' },
  { name: 'سارة كريم', role: 'مالكة عقار', phone: '07501234567', stage: 'مراجعة عقار', source: 'الموقع' },
]

const loadStoredArray = (key, fallback) => {
  try {
    const value = localStorage.getItem(key)
    return value ? JSON.parse(value) : fallback
  } catch {
    return fallback
  }
}

const properties = ref(loadStoredArray('propify.properties', propertySeeds))
const clients = ref(loadStoredArray('propify.clients', clientSeeds))

const propertyForm = ref({
  type: 'دار سكنية',
  mode: 'بيع',
  province: 'بغداد',
  area: '',
  space: '',
  rooms: '',
  price: '',
  owner: '',
  negotiable: true,
  status: 'قيد المراجعة',
})

const clientForm = ref({
  name: '',
  role: 'مشتري',
  phone: '',
  nationalId: '',
  source: 'الموقع',
  stage: 'عميل محتمل',
})

const propertyErrors = ref({})
const clientErrors = ref({})
const successMessage = ref('')
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8787/api'

const iraqiPhonePattern = /^(075|077|078|079)[0-9]{8}$/
const nationalIdPattern = /^[A-Za-z0-9]{12,}$/

const nextPropertyCode = computed(() => {
  const next = properties.value.length + 145
  return `PR-2026-${String(next).padStart(6, '0')}`
})

const showSuccess = (message) => {
  successMessage.value = message
  window.setTimeout(() => {
    successMessage.value = ''
  }, 2600)
}

const apiRequest = async (path, options = {}) => {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  })
  const data = await response.json()
  if (!response.ok) {
    const error = new Error(data.message || 'API request failed')
    error.errors = data.errors || {}
    throw error
  }
  return data
}

const loadApiData = async () => {
  try {
    const [serverProperties, serverClients] = await Promise.all([apiRequest('/properties'), apiRequest('/clients')])
    properties.value = serverProperties
    clients.value = serverClients
    apiOnline.value = true
  } catch {
    apiOnline.value = false
  }
}

const validateProperty = () => {
  const errors = {}
  const price = Number(String(propertyForm.value.price).replaceAll(',', ''))
  const space = Number(propertyForm.value.space)

  if (!propertyForm.value.area.trim()) errors.area = 'يرجى إدخال المنطقة.'
  if (!propertyForm.value.owner.trim()) errors.owner = 'يرجى إدخال اسم المالك.'
  if (!Number.isFinite(space) || space <= 0) errors.space = 'المساحة إجبارية ويجب أن تكون أكبر من صفر.'
  if (!Number.isFinite(price) || price <= 0) errors.price = 'السعر إجباري ويجب أن يكون رقماً أكبر من صفر.'

  propertyErrors.value = errors
  return Object.keys(errors).length === 0
}

const addProperty = () => {
  if (!validateProperty()) return

  const price = Number(String(propertyForm.value.price).replaceAll(',', ''))
  const payload = {
    code: nextPropertyCode.value,
    type: propertyForm.value.type,
    mode: propertyForm.value.mode,
    province: propertyForm.value.province,
    area: propertyForm.value.area,
    space: propertyForm.value.space,
    rooms: propertyForm.value.rooms,
    price: price.toLocaleString('en-US'),
    status: propertyForm.value.status,
    owner: propertyForm.value.owner,
    negotiable: propertyForm.value.negotiable,
  }

  apiRequest('/properties', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
    .then((createdProperty) => {
      properties.value.unshift(createdProperty)
      apiOnline.value = true
      showSuccess('تمت إضافة العقار وحفظه في API.')
    })
    .catch((error) => {
      apiOnline.value = false
      if (Object.keys(error.errors || {}).length > 0) {
        propertyErrors.value = error.errors
        return
      }
      properties.value.unshift(payload)
      showSuccess('تمت إضافة العقار محلياً. شغّل API لحفظه في السيرفر.')
    })

  propertyForm.value.area = ''
  propertyForm.value.space = ''
  propertyForm.value.rooms = ''
  propertyForm.value.price = ''
  propertyForm.value.owner = ''
}

const validateClient = () => {
  const errors = {}

  if (!clientForm.value.name.trim()) errors.name = 'يرجى إدخال اسم العميل.'
  if (!iraqiPhonePattern.test(clientForm.value.phone)) {
    errors.phone = 'يرجى إدخال رقم هاتف عراقي صحيح مكوّن من 11 رقماً.'
  }
  if (!nationalIdPattern.test(clientForm.value.nationalId)) {
    errors.nationalId = 'رقم البطاقة يجب ألا يقل عن 12 خانة ويسمح بالحروف والأرقام فقط.'
  }

  clientErrors.value = errors
  return Object.keys(errors).length === 0
}

const addClient = () => {
  if (!validateClient()) return

  const payload = {
    name: clientForm.value.name,
    role: clientForm.value.role,
    phone: clientForm.value.phone,
    nationalId: clientForm.value.nationalId,
    stage: clientForm.value.stage,
    source: clientForm.value.source,
  }

  apiRequest('/clients', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
    .then((createdClient) => {
      clients.value.unshift(createdClient)
      apiOnline.value = true
      showSuccess('تمت إضافة العميل وحفظه في API.')
    })
    .catch((error) => {
      apiOnline.value = false
      if (Object.keys(error.errors || {}).length > 0) {
        clientErrors.value = error.errors
        return
      }
      clients.value.unshift(payload)
      showSuccess('تمت إضافة العميل محلياً. شغّل API لحفظه في السيرفر.')
    })

  clientForm.value.name = ''
  clientForm.value.phone = ''
  clientForm.value.nationalId = ''
}

const contracts = [
  { code: 'CT-2026-000044', client: 'أحمد علي', kind: 'بيع نقدي', paid: '120,000,000', due: '0', status: 'مكتمل' },
  { code: 'CT-2026-000045', client: 'سارة كريم', kind: 'تقسيط', paid: '30,000,000', due: '120,000,000', status: 'نشط' },
  { code: 'CT-2026-000046', client: 'محمد حسن', kind: 'إيجار', paid: '1,400,000', due: '700,000', status: 'شهري' },
]

const notifications = [
  'قسط مستحق اليوم للعقد CT-2026-000045',
  'عقار جديد بانتظار الموافقة PR-2026-000147',
  'موعد معاينة غداً الساعة 10:30 صباحاً',
  'سند قبض جديد بقيمة 2,000,000 دينار',
]

const permissions = [
  'إضافة عقار',
  'تعديل عقار',
  'قبول عقار من عميل',
  'طباعة عقد',
  'إدارة السندات',
  'مشاهدة التقارير',
  'إدارة المستخدمين',
  'تعديل الإعدادات',
]

const chartBars = [
  { label: 'كانون', sales: 52, rent: 36 },
  { label: 'شباط', sales: 68, rent: 44 },
  { label: 'آذار', sales: 57, rent: 51 },
  { label: 'نيسان', sales: 76, rent: 48 },
  { label: 'أيار', sales: 84, rent: 62 },
  { label: 'حزيران', sales: 71, rent: 58 },
]

const stats = computed(() => {
  const available = properties.value.filter((property) => property.status === 'متاح').length
  const reserved = properties.value.filter((property) => property.status === 'محجوز').length

  return [
    { label: 'إجمالي العقارات', value: String(properties.value.length), trend: `${available} متاح`, icon: Building2 },
    { label: 'العقارات المتاحة', value: String(available), trend: `${reserved} محجوز`, icon: Home },
    { label: 'أرباح المكتب', value: '42.8م', trend: '+12% عن الشهر السابق', icon: CircleDollarSign },
    { label: 'أقساط مستحقة', value: '16', trend: '5 متأخرة', icon: CalendarClock },
  ]
})

const propertyStatuses = computed(() => ['الكل', ...new Set(properties.value.map((property) => property.status))])

const filteredProperties = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return properties.value.filter((property) => {
    const matchesStatus = statusFilter.value === 'الكل' || property.status === statusFilter.value
    const searchable = [property.code, property.type, property.mode, property.area, property.price, property.status, property.owner]
      .join(' ')
      .toLowerCase()

    return matchesStatus && (!query || searchable.includes(query))
  })
})

const downloadFile = (filename, content, type = 'text/csv;charset=utf-8') => {
  const blob = new Blob(['\ufeff', content], { type })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

const exportProperties = () => {
  const rows = [
    ['رقم العقار', 'النوع', 'الغرض', 'المنطقة', 'السعر', 'الحالة', 'المالك'],
    ...filteredProperties.value.map((property) => [
      property.code,
      property.type,
      property.mode,
      property.area,
      property.price,
      property.status,
      property.owner,
    ]),
  ]
  const csv = rows.map((row) => row.map((cell) => `"${String(cell).replaceAll('"', '""')}"`).join(',')).join('\n')
  downloadFile('propify-properties.csv', csv)
}

watch(
  properties,
  (value) => {
    localStorage.setItem('propify.properties', JSON.stringify(value))
  },
  { deep: true },
)

watch(
  clients,
  (value) => {
    localStorage.setItem('propify.clients', JSON.stringify(value))
  },
  { deep: true },
)

const statusClass = (status) =>
  ({
    متاح: 'success',
    محجوز: 'warning',
    'قيد المراجعة': 'muted',
    مكتمل: 'success',
    نشط: 'info',
    شهري: 'warning',
  })[status] || 'muted'

const shellClasses = computed(() => ({
  'is-dark': darkMode.value,
  'is-collapsed': collapsed.value,
}))

onMounted(loadApiData)
</script>

<template>
  <div class="app-shell" :class="shellClasses" dir="rtl">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar" aria-hidden="true">ع</div>
        <div class="profile-text">
          <strong>علي محمد</strong>
          <span>مدير النظام</span>
        </div>
      </div>

      <nav aria-label="أقسام النظام">
        <button
          v-for="item in navItems"
          :key="item.id"
          class="nav-item"
          :class="{ active: activeSection === item.id }"
          :title="item.label"
          type="button"
          @click="activeSection = item.id"
        >
          <component :is="item.icon" :size="20" />
          <span>{{ item.label }}</span>
        </button>
      </nav>

      <button class="collapse-button" type="button" :title="collapsed ? 'توسيع القائمة' : 'طي القائمة'" @click="collapsed = !collapsed">
        <PanelRightOpen v-if="collapsed" :size="20" />
        <PanelRightClose v-else :size="20" />
        <span>{{ collapsed ? 'توسيع القائمة' : 'طي القائمة' }}</span>
      </button>
    </aside>

    <main class="workspace">
      <header class="topbar">
        <div>
          <p class="eyebrow">Propify Real Estate OS</p>
          <h1>إدارة أعمالك العقارية من مكان واحد</h1>
        </div>
        <div class="toolbar">
          <label class="search">
            <Search :size="18" />
            <input v-model="searchQuery" type="search" placeholder="ابحث برقم عقار، عميل، عقد..." />
          </label>
          <button class="icon-button" type="button" title="الإشعارات">
            <Bell :size="20" />
          </button>
          <span class="api-status" :class="{ online: apiOnline }">
            {{ apiOnline ? 'API متصل' : 'حفظ محلي' }}
          </span>
          <button class="icon-button" type="button" :title="darkMode ? 'الثيم الفاتح' : 'الثيم الداكن'" @click="darkMode = !darkMode">
            <Sun v-if="darkMode" :size="20" />
            <Moon v-else :size="20" />
          </button>
        </div>
      </header>

      <section v-if="welcomeVisible" class="welcome" aria-live="polite">
        <strong>مرحباً بك، علي</strong>
        <span>نتمنى لك يوماً موفقاً في إدارة أعمالك العقارية</span>
      </section>

      <section class="stats-grid" aria-label="ملخص المؤشرات">
        <article v-for="stat in stats" :key="stat.label" class="stat-card">
          <div class="stat-icon"><component :is="stat.icon" :size="22" /></div>
          <span>{{ stat.label }}</span>
          <strong>{{ stat.value }}</strong>
          <small>{{ stat.trend }}</small>
        </article>
      </section>

      <section v-if="successMessage" class="success-toast" aria-live="polite">
        <Save :size="18" />
        <span>{{ successMessage }}</span>
      </section>

      <section class="operations-grid">
        <article class="panel form-panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">إدارة العقارات</p>
              <h2>إضافة عقار جديد</h2>
            </div>
            <span class="code-pill">{{ nextPropertyCode }}</span>
          </div>
          <form class="smart-form" @submit.prevent="addProperty">
            <label>
              <span>نوع العقار</span>
              <select v-model="propertyForm.type">
                <option>دار سكنية</option>
                <option>شقة</option>
                <option>أرض</option>
                <option>محل تجاري</option>
                <option>بناية</option>
              </select>
            </label>
            <label>
              <span>الغرض</span>
              <select v-model="propertyForm.mode">
                <option>بيع</option>
                <option>إيجار</option>
                <option>بيع بالتقسيط</option>
              </select>
            </label>
            <label>
              <span>المحافظة</span>
              <input v-model="propertyForm.province" type="text" />
            </label>
            <label>
              <span>المنطقة</span>
              <input v-model="propertyForm.area" type="text" placeholder="مثال: المنصور" />
              <small v-if="propertyErrors.area" class="field-error"><AlertCircle :size="14" />{{ propertyErrors.area }}</small>
            </label>
            <label>
              <span>المساحة م²</span>
              <input v-model="propertyForm.space" inputmode="decimal" placeholder="250" />
              <small v-if="propertyErrors.space" class="field-error"><AlertCircle :size="14" />{{ propertyErrors.space }}</small>
            </label>
            <label>
              <span>عدد الغرف</span>
              <input v-model="propertyForm.rooms" inputmode="numeric" placeholder="4" />
            </label>
            <label>
              <span>السعر / دينار</span>
              <input v-model="propertyForm.price" inputmode="numeric" placeholder="120000000" />
              <small v-if="propertyErrors.price" class="field-error"><AlertCircle :size="14" />{{ propertyErrors.price }}</small>
            </label>
            <label>
              <span>المالك</span>
              <input v-model="propertyForm.owner" type="text" placeholder="اسم مالك العقار" />
              <small v-if="propertyErrors.owner" class="field-error"><AlertCircle :size="14" />{{ propertyErrors.owner }}</small>
            </label>
            <label class="check-line">
              <input v-model="propertyForm.negotiable" type="checkbox" />
              <span>السعر قابل للتفاوض</span>
            </label>
            <button class="submit-button" type="submit"><Plus :size="18" /> إضافة العقار</button>
          </form>
        </article>

        <article class="panel form-panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">CRM</p>
              <h2>إضافة عميل</h2>
            </div>
            <Users :size="22" />
          </div>
          <form class="smart-form" @submit.prevent="addClient">
            <label>
              <span>اسم العميل</span>
              <input v-model="clientForm.name" type="text" placeholder="مثال: أحمد علي" />
              <small v-if="clientErrors.name" class="field-error"><AlertCircle :size="14" />{{ clientErrors.name }}</small>
            </label>
            <label>
              <span>نوع العلاقة</span>
              <select v-model="clientForm.role">
                <option>بائع</option>
                <option>مشتري</option>
                <option>مؤجر</option>
                <option>مستأجر</option>
                <option>شاهد</option>
                <option>وسيط</option>
                <option>مالك عقار</option>
              </select>
            </label>
            <label>
              <span>رقم الهاتف العراقي</span>
              <input v-model="clientForm.phone" inputmode="numeric" placeholder="07701234567" />
              <small v-if="clientErrors.phone" class="field-error"><AlertCircle :size="14" />{{ clientErrors.phone }}</small>
            </label>
            <label>
              <span>رقم البطاقة الوطنية</span>
              <input v-model="clientForm.nationalId" placeholder="12 خانة أو أكثر" />
              <small v-if="clientErrors.nationalId" class="field-error"><AlertCircle :size="14" />{{ clientErrors.nationalId }}</small>
            </label>
            <label>
              <span>مصدر العميل</span>
              <select v-model="clientForm.source">
                <option>الموقع</option>
                <option>إعلان ممول</option>
                <option>توصية</option>
                <option>زيارة المكتب</option>
              </select>
            </label>
            <label>
              <span>مرحلة الصفقة</span>
              <select v-model="clientForm.stage">
                <option>عميل محتمل</option>
                <option>تفاوض</option>
                <option>معاينة</option>
                <option>عقد نشط</option>
                <option>مغلق</option>
              </select>
            </label>
            <button class="submit-button" type="submit"><Plus :size="18" /> إضافة العميل</button>
          </form>
        </article>
      </section>

      <section class="content-grid">
        <article class="panel wide">
          <div class="panel-header">
            <div>
              <p class="eyebrow">العقارات</p>
              <h2>مخزون العقارات الحالي</h2>
            </div>
            <div class="panel-actions">
              <select v-model="statusFilter" class="compact-select" aria-label="فلترة حسب حالة العقار">
                <option v-for="status in propertyStatuses" :key="status">{{ status }}</option>
              </select>
              <button class="text-button" type="button" @click="exportProperties"><Download :size="18" /> تصدير Excel</button>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>رقم العقار</th>
                  <th>النوع</th>
                  <th>الغرض</th>
                  <th>المنطقة</th>
                  <th>السعر / دينار</th>
                  <th>الحالة</th>
                  <th>المالك</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="property in filteredProperties" :key="property.code">
                  <td>{{ property.code }}</td>
                  <td>{{ property.type }}</td>
                  <td>{{ property.mode }}</td>
                  <td>{{ property.area }}</td>
                  <td>{{ property.price }}</td>
                  <td><span class="badge" :class="statusClass(property.status)">{{ property.status }}</span></td>
                  <td>{{ property.owner }}</td>
                </tr>
                <tr v-if="filteredProperties.length === 0">
                  <td colspan="7" class="empty-cell">لا توجد عقارات مطابقة للبحث أو الفلتر الحالي.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        <article class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">CRM</p>
              <h2>متابعة العملاء</h2>
            </div>
            <Users :size="22" />
          </div>
          <div class="stack-list">
            <div v-for="client in clients" :key="client.phone" class="list-row">
              <div>
                <strong>{{ client.name }}</strong>
                <span>{{ client.role }} · {{ client.source }}</span>
              </div>
              <small>{{ client.stage }}</small>
            </div>
          </div>
        </article>

        <article class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">الإشعارات</p>
              <h2>تنبيهات اليوم</h2>
            </div>
            <Bell :size="22" />
          </div>
          <div class="notification-list">
            <p v-for="notification in notifications" :key="notification">{{ notification }}</p>
          </div>
        </article>

        <article class="panel wide">
          <div class="panel-header">
            <div>
              <p class="eyebrow">العقود</p>
              <h2>البيع، الإيجار، التقسيط</h2>
            </div>
            <button class="text-button" type="button"><Printer :size="18" /> طباعة عقد</button>
          </div>
          <div class="contracts">
            <div v-for="contract in contracts" :key="contract.code" class="contract-card">
              <FileText :size="22" />
              <div>
                <strong>{{ contract.code }}</strong>
                <span>{{ contract.client }} · {{ contract.kind }}</span>
              </div>
              <div class="money">
                <span>مدفوع {{ contract.paid }}</span>
                <span>متبقي {{ contract.due }}</span>
              </div>
              <span class="badge" :class="statusClass(contract.status)">{{ contract.status }}</span>
            </div>
          </div>
        </article>

        <article class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">التقارير</p>
              <h2>مبيعات وإيجارات شهرية</h2>
            </div>
            <ChevronLeft :size="22" />
          </div>
          <div class="chart" aria-label="مخطط مبيعات وإيجارات">
            <div v-for="bar in chartBars" :key="bar.label" class="chart-column">
              <div class="bars">
                <span class="sales" :style="{ height: `${bar.sales}%` }"></span>
                <span class="rent" :style="{ height: `${bar.rent}%` }"></span>
              </div>
              <small>{{ bar.label }}</small>
            </div>
          </div>
        </article>

        <article class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">الصلاحيات</p>
              <h2>أدوار دقيقة</h2>
            </div>
            <ShieldCheck :size="22" />
          </div>
          <div class="permission-grid">
            <span v-for="permission in permissions" :key="permission"><ChevronRight :size="15" />{{ permission }}</span>
          </div>
        </article>
      </section>
    </main>
  </div>
</template>
