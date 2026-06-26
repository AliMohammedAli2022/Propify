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
const authToken = ref(localStorage.getItem('propify.authToken') || '')
const currentUser = ref(null)
const authErrors = ref({})
const loginForm = ref({
  email: 'admin@propify.local',
  password: 'password',
})

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

const activeSectionLabel = computed(() => navItems.find((item) => item.id === activeSection.value)?.label || 'Propify')
const showSection = (...sections) => sections.includes(activeSection.value)

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
const editingPropertyCode = ref('')

const clientForm = ref({
  name: '',
  role: 'مشتري',
  phone: '',
  nationalId: '',
  source: 'الموقع',
  stage: 'عميل محتمل',
})
const editingClientId = ref('')

const propertyErrors = ref({})
const clientErrors = ref({})
const voucherErrors = ref({})
const contractErrors = ref({})
const userErrors = ref({})
const successMessage = ref('')
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api'
const contracts = ref([])
const installments = ref([])
const vouchers = ref([])
const ledger = ref([])
const users = ref([])
const financialReport = ref(null)
const propertiesReport = ref(null)
const installmentsReport = ref(null)
const notifications = ref([])
const propertyMedia = ref([])
const mediaErrors = ref({})
const mediaForm = ref({
  propertyCode: '',
  files: [],
})

const iraqiPhonePattern = /^(075|077|078|079)[0-9]{8}$/
const nationalIdPattern = /^[A-Za-z0-9]{12,}$/

const availablePermissions = [
  { key: 'properties.create', label: 'إضافة عقار' },
  { key: 'properties.update', label: 'تعديل عقار' },
  { key: 'properties.approve', label: 'قبول عقار من عميل' },
  { key: 'clients.manage', label: 'إدارة العملاء' },
  { key: 'contracts.create', label: 'إنشاء عقد' },
  { key: 'contracts.print', label: 'طباعة عقد' },
  { key: 'vouchers.manage', label: 'إدارة السندات' },
  { key: 'reports.view', label: 'مشاهدة التقارير' },
  { key: 'settings.update', label: 'تعديل الإعدادات' },
  { key: 'users.manage', label: 'إدارة المستخدمين' },
]

const userForm = ref({
  name: '',
  email: '',
  password: '',
  role: 'sales',
  permissions: ['properties.create', 'clients.manage'],
})

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
    headers: {
      'Content-Type': 'application/json',
      ...(authToken.value ? { Authorization: `Bearer ${authToken.value}` } : {}),
      ...(options.headers || {}),
    },
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

const apiUpload = async (path, formData) => {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: 'POST',
    headers: {
      ...(authToken.value ? { Authorization: `Bearer ${authToken.value}` } : {}),
    },
    body: formData,
  })
  const data = await response.json()
  if (!response.ok) {
    const error = new Error(data.message || 'API upload failed')
    error.errors = data.errors || {}
    throw error
  }
  return data
}

const login = async () => {
  authErrors.value = {}

  try {
    const data = await apiRequest('/auth/login', {
      method: 'POST',
      body: JSON.stringify(loginForm.value),
    })
    authToken.value = data.token
    currentUser.value = data.user
    localStorage.setItem('propify.authToken', data.token)
    apiOnline.value = true
    await loadApiData()
  } catch (error) {
    apiOnline.value = false
    authErrors.value = error.errors || { email: ['تعذر تسجيل الدخول.'] }
  }
}

const loadCurrentUser = async () => {
  if (!authToken.value) return

  try {
    const data = await apiRequest('/auth/me')
    currentUser.value = data.user
    apiOnline.value = true
    await loadApiData()
  } catch {
    authToken.value = ''
    currentUser.value = null
    localStorage.removeItem('propify.authToken')
  }
}

const logout = async () => {
  try {
    await apiRequest('/auth/logout', { method: 'POST' })
  } finally {
    authToken.value = ''
    currentUser.value = null
    localStorage.removeItem('propify.authToken')
  }
}

const loadApiData = async () => {
  try {
    const [
      serverProperties,
      serverClients,
      serverContracts,
      serverInstallments,
      serverVouchers,
      serverLedger,
      serverUsers,
      serverFinancialReport,
      serverPropertiesReport,
      serverInstallmentsReport,
      serverNotifications,
    ] = await Promise.all([
      apiRequest('/properties'),
      apiRequest('/clients'),
      apiRequest('/contracts'),
      apiRequest('/installments'),
      apiRequest('/vouchers'),
      apiRequest('/ledger'),
      apiRequest('/users'),
      apiRequest('/reports/financial'),
      apiRequest('/reports/properties'),
      apiRequest('/reports/installments'),
      apiRequest('/notifications'),
    ])
    properties.value = serverProperties
    clients.value = serverClients
    contracts.value = serverContracts
    installments.value = serverInstallments
    vouchers.value = serverVouchers
    ledger.value = serverLedger
    users.value = serverUsers
    financialReport.value = serverFinancialReport
    propertiesReport.value = serverPropertiesReport
    installmentsReport.value = serverInstallmentsReport
    notifications.value = serverNotifications
    if (!mediaForm.value.propertyCode && serverProperties.length > 0) {
      mediaForm.value.propertyCode = serverProperties[0].code
      await loadPropertyMedia(serverProperties[0].code)
    }
    apiOnline.value = true
  } catch {
    apiOnline.value = false
  }
}

const loadPropertyMedia = async (propertyCode = mediaForm.value.propertyCode) => {
  if (!propertyCode) return

  try {
    propertyMedia.value = await apiRequest(`/properties/${propertyCode}/media`)
    apiOnline.value = true
  } catch {
    propertyMedia.value = []
    apiOnline.value = false
  }
}

const onMediaFilesChange = (event) => {
  mediaForm.value.files = Array.from(event.target.files || [])
}

const uploadPropertyMedia = () => {
  mediaErrors.value = {}

  if (!mediaForm.value.propertyCode) {
    mediaErrors.value.propertyCode = ['يرجى اختيار العقار.']
    return
  }

  if (mediaForm.value.files.length === 0) {
    mediaErrors.value.files = ['يرجى اختيار صورة أو مستند.']
    return
  }

  const formData = new FormData()
  mediaForm.value.files.forEach((file) => formData.append('files[]', file))

  apiUpload(`/properties/${mediaForm.value.propertyCode}/media`, formData)
    .then((createdMedia) => {
      propertyMedia.value = [...createdMedia, ...propertyMedia.value]
      mediaForm.value.files = []
      mediaErrors.value = {}
      apiOnline.value = true
      showSuccess('تم رفع ملفات العقار بنجاح.')
      return loadApiData()
    })
    .catch((error) => {
      apiOnline.value = false
      mediaErrors.value = error.errors || {}
    })
}

const validateUser = () => {
  const errors = {}

  if (!userForm.value.name.trim()) errors.name = ['يرجى إدخال اسم المستخدم.']
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(userForm.value.email)) errors.email = ['يرجى إدخال بريد إلكتروني صحيح.']
  if (userForm.value.password.length < 6) errors.password = ['كلمة المرور يجب ألا تقل عن 6 أحرف.']

  userErrors.value = errors
  return Object.keys(errors).length === 0
}

const addUser = () => {
  if (!validateUser()) return

  apiRequest('/users', {
    method: 'POST',
    body: JSON.stringify(userForm.value),
  })
    .then((createdUser) => {
      users.value.unshift(createdUser)
      userErrors.value = {}
      apiOnline.value = true
      showSuccess('تم إنشاء المستخدم وتحديد صلاحياته.')
      userForm.value.name = ''
      userForm.value.email = ''
      userForm.value.password = ''
      userForm.value.role = 'sales'
      userForm.value.permissions = ['properties.create', 'clients.manage']
    })
    .catch((error) => {
      apiOnline.value = false
      userErrors.value = error.errors || {}
    })
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

const resetPropertyForm = () => {
  propertyForm.value.area = ''
  propertyForm.value.space = ''
  propertyForm.value.rooms = ''
  propertyForm.value.price = ''
  propertyForm.value.owner = ''
  propertyForm.value.status = 'قيد المراجعة'
  propertyForm.value.negotiable = true
  editingPropertyCode.value = ''
}

const startEditProperty = (property) => {
  editingPropertyCode.value = property.code
  propertyForm.value.type = property.type
  propertyForm.value.mode = property.mode
  propertyForm.value.province = property.province || 'بغداد'
  propertyForm.value.area = property.area
  propertyForm.value.space = property.space
  propertyForm.value.rooms = property.rooms || ''
  propertyForm.value.price = String(property.price || '').replaceAll(',', '')
  propertyForm.value.owner = property.owner
  propertyForm.value.status = property.status
  propertyForm.value.negotiable = property.negotiable ?? true
  activeSection.value = 'properties'
  showSuccess(`تم تحميل بيانات العقار ${property.code} للتعديل.`)
}

const addProperty = () => {
  if (!validateProperty()) return

  const price = Number(String(propertyForm.value.price).replaceAll(',', ''))
  const payload = {
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
  const endpoint = editingPropertyCode.value ? `/properties/${editingPropertyCode.value}` : '/properties'
  const method = editingPropertyCode.value ? 'PUT' : 'POST'

  apiRequest(endpoint, {
    method,
    body: JSON.stringify(payload),
  })
    .then((createdProperty) => {
      if (editingPropertyCode.value) {
        properties.value = properties.value.map((property) => (property.code === createdProperty.code ? createdProperty : property))
      } else {
        properties.value.unshift(createdProperty)
      }
      apiOnline.value = true
      showSuccess(editingPropertyCode.value ? 'تم تحديث بيانات العقار.' : 'تمت إضافة العقار وحفظه في API.')
      resetPropertyForm()
    })
    .catch((error) => {
      apiOnline.value = false
      if (Object.keys(error.errors || {}).length > 0) {
        propertyErrors.value = error.errors
        return
      }
      if (!editingPropertyCode.value) {
        properties.value.unshift({ ...payload, code: nextPropertyCode.value })
        showSuccess('تمت إضافة العقار محلياً. شغّل API لحفظه في السيرفر.')
      }
    })
}

const approveProperty = (property) => {
  apiRequest(`/properties/${property.code}/approve`, { method: 'POST' })
    .then((approvedProperty) => {
      properties.value = properties.value.map((item) => (item.code === approvedProperty.code ? approvedProperty : item))
      apiOnline.value = true
      showSuccess(`تم اعتماد العقار ${property.code}.`)
      return loadApiData()
    })
    .catch((error) => {
      apiOnline.value = false
      propertyErrors.value = error.errors || {}
    })
}

const deleteProperty = (property) => {
  if (!window.confirm(`هل تريد حذف العقار ${property.code}؟`)) return

  apiRequest(`/properties/${property.code}`, { method: 'DELETE' })
    .then(() => {
      properties.value = properties.value.filter((item) => item.code !== property.code)
      apiOnline.value = true
      showSuccess(`تم حذف العقار ${property.code}.`)
      return loadApiData()
    })
    .catch((error) => {
      apiOnline.value = false
      const message = error.errors?.property?.[0] || 'تعذر حذف العقار.'
      showSuccess(message)
    })
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
  const endpoint = editingClientId.value ? `/clients/${editingClientId.value}` : '/clients'
  const method = editingClientId.value ? 'PUT' : 'POST'

  apiRequest(endpoint, {
    method,
    body: JSON.stringify(payload),
  })
    .then((createdClient) => {
      if (editingClientId.value) {
        clients.value = clients.value.map((client) => (client.id === createdClient.id ? createdClient : client))
      } else {
        clients.value.unshift(createdClient)
      }
      apiOnline.value = true
      showSuccess(editingClientId.value ? 'تم تحديث بيانات العميل.' : 'تمت إضافة العميل وحفظه في API.')
      resetClientForm()
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
}

const resetClientForm = () => {
  clientForm.value.name = ''
  clientForm.value.phone = ''
  clientForm.value.nationalId = ''
  clientForm.value.role = 'مشتري'
  clientForm.value.source = 'الموقع'
  clientForm.value.stage = 'عميل محتمل'
  editingClientId.value = ''
}

const startEditClient = (client) => {
  if (!client.id) {
    showSuccess('هذا العميل محلي فقط، أعد تحميل API قبل تعديله.')
    return
  }

  editingClientId.value = client.id
  clientForm.value.name = client.name
  clientForm.value.role = client.role
  clientForm.value.phone = client.phone
  clientForm.value.nationalId = client.nationalId
  clientForm.value.source = client.source
  clientForm.value.stage = client.stage
  activeSection.value = 'clients'
  showSuccess(`تم تحميل بيانات العميل ${client.name} للتعديل.`)
}

const deleteClient = (client) => {
  if (!client.id) {
    clients.value = clients.value.filter((item) => item.phone !== client.phone)
    showSuccess(`تم حذف العميل المحلي ${client.name}.`)
    return
  }

  if (!window.confirm(`هل تريد حذف العميل ${client.name}؟`)) return

  apiRequest(`/clients/${client.id}`, { method: 'DELETE' })
    .then(() => {
      clients.value = clients.value.filter((item) => item.id !== client.id)
      apiOnline.value = true
      showSuccess(`تم حذف العميل ${client.name}.`)
      return loadApiData()
    })
    .catch((error) => {
      apiOnline.value = false
      const message = error.errors?.client?.[0] || 'تعذر حذف العميل.'
      showSuccess(message)
    })
}

const voucherForm = ref({
  type: 'قبض',
  client: '',
  amount: '',
  reason: '',
  propertyCode: '',
  contractCode: '',
})

const contractForm = ref({
  propertyCode: '',
  client: '',
  kind: 'بيع نقدي',
  total: '',
  paid: '',
  commissionRate: 2,
  installmentsCount: 12,
  status: 'نشط',
})

const validateContract = () => {
  const errors = {}
  const total = Number(String(contractForm.value.total).replaceAll(',', ''))
  const paid = Number(String(contractForm.value.paid || 0).replaceAll(',', ''))

  if (!contractForm.value.propertyCode.trim()) errors.propertyCode = 'يرجى إدخال رقم العقار.'
  if (!contractForm.value.client.trim()) errors.client = 'يرجى إدخال اسم العميل.'
  if (!Number.isFinite(total) || total <= 0) errors.total = 'قيمة العقد يجب أن تكون أكبر من صفر.'
  if (Number.isFinite(paid) && paid > total) errors.paid = 'المدفوع لا يمكن أن يتجاوز قيمة العقد.'
  if (contractForm.value.kind === 'تقسيط' && Number(contractForm.value.installmentsCount) <= 0) {
    errors.installmentsCount = 'عدد الأقساط يجب أن يكون أكبر من صفر.'
  }

  contractErrors.value = errors
  return Object.keys(errors).length === 0
}

const addContract = () => {
  if (!validateContract()) return

  const payload = {
    ...contractForm.value,
    total: Number(String(contractForm.value.total).replaceAll(',', '')),
    paid: Number(String(contractForm.value.paid || 0).replaceAll(',', '')),
    commissionRate: Number(contractForm.value.commissionRate || 0),
    installmentsCount: Number(contractForm.value.installmentsCount || 1),
  }

  apiRequest('/contracts', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
    .then((createdContract) => {
      contracts.value.unshift(createdContract)
      return apiRequest('/installments')
    })
    .then((serverInstallments) => {
      installments.value = serverInstallments
      apiOnline.value = true
      showSuccess('تم إنشاء العقد وتحديث جدول الأقساط.')
    })
    .catch((error) => {
      apiOnline.value = false
      if (Object.keys(error.errors || {}).length > 0) {
        contractErrors.value = error.errors
        return
      }
      contracts.value.unshift({ ...payload, code: `LOCAL-${Date.now()}`, due: payload.total - payload.paid, commission: Math.round(payload.total * (payload.commissionRate / 100)) })
      showSuccess('تمت إضافة العقد محلياً. شغّل API لحفظه في السيرفر.')
    })

  contractForm.value.propertyCode = ''
  contractForm.value.client = ''
  contractForm.value.total = ''
  contractForm.value.paid = ''
  contractForm.value.installmentsCount = 12
}

const validateVoucher = () => {
  const errors = {}
  const amount = Number(String(voucherForm.value.amount).replaceAll(',', ''))

  if (!voucherForm.value.client.trim()) errors.client = 'يرجى إدخال اسم الطرف.'
  if (!Number.isFinite(amount) || amount <= 0) errors.amount = 'مبلغ السند يجب أن يكون أكبر من صفر.'
  if (!voucherForm.value.reason.trim()) errors.reason = 'يرجى إدخال سبب السند.'

  voucherErrors.value = errors
  return Object.keys(errors).length === 0
}

const addVoucher = () => {
  if (!validateVoucher()) return

  const payload = {
    ...voucherForm.value,
    amount: Number(String(voucherForm.value.amount).replaceAll(',', '')),
  }

  apiRequest('/vouchers', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
    .then((createdVoucher) => {
      vouchers.value.unshift(createdVoucher)
      return apiRequest('/ledger')
    })
    .then((serverLedger) => {
      ledger.value = serverLedger
      apiOnline.value = true
      showSuccess('تم حفظ السند وتحديث دفتر الأستاذ.')
    })
    .catch((error) => {
      apiOnline.value = false
      if (Object.keys(error.errors || {}).length > 0) {
        voucherErrors.value = error.errors
        return
      }
      vouchers.value.unshift({ ...payload, code: `LOCAL-${Date.now()}`, issuedAt: new Date().toISOString().slice(0, 10) })
      showSuccess('تمت إضافة السند محلياً. شغّل API لحفظه في السيرفر.')
    })

  voucherForm.value.client = ''
  voucherForm.value.amount = ''
  voucherForm.value.reason = ''
  voucherForm.value.propertyCode = ''
  voucherForm.value.contractCode = ''
}

const fallbackNotifications = computed(() => [
  ...installments.value
    .filter((installment) => installment.status === 'مستحق')
    .slice(0, 3)
    .map((installment) => ({
      id: `local-installment-${installment.contractCode}-${installment.number}`,
      severity: 'warning',
      title: 'قسط مستحق',
      message: `القسط ${installment.number} للعقد ${installment.contractCode} يستحق في ${installment.dueDate}.`,
    })),
  ...properties.value
    .filter((property) => property.status === 'قيد المراجعة')
    .slice(0, 2)
    .map((property) => ({
      id: `local-property-${property.code}`,
      severity: 'info',
      title: 'عقار بانتظار المراجعة',
      message: `العقار ${property.code} يحتاج مراجعة قبل الاعتماد.`,
    })),
])

const visibleNotifications = computed(() => (
  notifications.value.length > 0 ? notifications.value : fallbackNotifications.value
))

const roleLabel = (role) =>
  ({
    system_admin: 'مدير النظام',
    office_manager: 'مدير المكتب',
    sales: 'موظف مبيعات',
    accountant: 'محاسب',
    data_entry: 'مدخل بيانات',
    client: 'عميل',
    property_supervisor: 'مشرف عقارات',
  })[role] || role

const chartBars = computed(() => {
  const groups = propertiesReport.value?.byStatus?.length
    ? propertiesReport.value.byStatus
    : [
      { label: 'متاح', count: properties.value.filter((property) => property.status === 'متاح').length },
      { label: 'محجوز', count: properties.value.filter((property) => property.status === 'محجوز').length },
      { label: 'مراجعة', count: properties.value.filter((property) => property.status !== 'متاح' && property.status !== 'محجوز').length },
    ]
  const max = Math.max(...groups.map((item) => item.count), 1)
  const total = Math.max(propertiesReport.value?.total || properties.value.length || 1, 1)

  return groups.map((item) => ({
    label: item.label,
    sales: Math.max(18, Math.round((item.count / max) * 100)),
    rent: Math.max(18, Math.round((item.count / total) * 100)),
  }))
})

const stats = computed(() => {
  const available = properties.value.filter((property) => property.status === 'متاح').length
  const reserved = properties.value.filter((property) => property.status === 'محجوز').length
  const officeProfit = contracts.value.reduce((sum, contract) => sum + Number(contract.commission || 0), 0)
  const dueInstallments = installments.value.filter((installment) => installment.status === 'مستحق').length

  return [
    { label: 'إجمالي العقارات', value: String(properties.value.length), trend: `${available} متاح`, icon: Building2 },
    { label: 'العقارات المتاحة', value: String(available), trend: `${reserved} محجوز`, icon: Home },
    { label: 'عمولات المكتب', value: `${(officeProfit / 1000000).toFixed(1)}م`, trend: `${contracts.value.length} عقود`, icon: CircleDollarSign },
    { label: 'أقساط مستحقة', value: String(dueInstallments), trend: `${installments.value.length} قسط مجدول`, icon: CalendarClock },
  ]
})

const financialSummary = computed(() => {
  const income = ledger.value.filter((entry) => entry.direction === 'credit').reduce((sum, entry) => sum + Number(entry.amount || 0), 0)
  const expenses = ledger.value.filter((entry) => entry.direction === 'debit').reduce((sum, entry) => sum + Number(entry.amount || 0), 0)
  return {
    income,
    expenses,
    balance: income - expenses,
  }
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

const printContract = (contract) => {
  const printWindow = window.open('', '_blank', 'width=900,height=700')
  if (!printWindow) return

  printWindow.document.write(`
    <html lang="ar" dir="rtl">
      <head>
        <title>${contract.code}</title>
        <style>
          body { font-family: Tahoma, Arial, sans-serif; padding: 32px; color: #111827; }
          header { border-bottom: 2px solid #147d73; margin-bottom: 24px; padding-bottom: 12px; }
          h1 { margin: 0 0 8px; }
          table { width: 100%; border-collapse: collapse; margin-top: 18px; }
          td { border: 1px solid #d1d5db; padding: 10px; }
          .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 80px; text-align: center; }
          .signatures div { border-top: 1px solid #111827; padding-top: 8px; }
        </style>
      </head>
      <body>
        <header>
          <h1>Propify</h1>
          <strong>عقد ${contract.kind} رقم ${contract.code}</strong>
        </header>
        <table>
          <tr><td>رقم العقار</td><td>${contract.propertyCode || '-'}</td></tr>
          <tr><td>العميل</td><td>${contract.client}</td></tr>
          <tr><td>قيمة العقد</td><td>${formatMoney(contract.total)} دينار</td></tr>
          <tr><td>المدفوع</td><td>${formatMoney(contract.paid)} دينار</td></tr>
          <tr><td>المتبقي</td><td>${formatMoney(contract.due)} دينار</td></tr>
          <tr><td>عمولة المكتب</td><td>${formatMoney(contract.commission)} دينار</td></tr>
          <tr><td>الحالة</td><td>${contract.status}</td></tr>
        </table>
        <div class="signatures">
          <div>توقيع الطرف الأول</div>
          <div>توقيع الطرف الثاني</div>
          <div>ختم المكتب</div>
        </div>
      </body>
    </html>
  `)
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
}

const formatMoney = (value) => Number(value || 0).toLocaleString('en-US')

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

onMounted(loadCurrentUser)
</script>

<template>
  <div v-if="!currentUser" class="login-shell" dir="rtl">
    <section class="login-panel">
      <div class="login-brand">
        <div class="avatar" aria-hidden="true">P</div>
        <div>
          <p class="eyebrow">Propify</p>
          <h1>تسجيل الدخول</h1>
        </div>
      </div>
      <form class="smart-form login-form" @submit.prevent="login">
        <label>
          <span>البريد الإلكتروني</span>
          <input v-model="loginForm.email" type="email" autocomplete="username" />
          <small v-if="authErrors.email" class="field-error"><AlertCircle :size="14" />{{ authErrors.email[0] }}</small>
        </label>
        <label>
          <span>كلمة المرور</span>
          <input v-model="loginForm.password" type="password" autocomplete="current-password" />
          <small v-if="authErrors.password" class="field-error"><AlertCircle :size="14" />{{ authErrors.password[0] }}</small>
        </label>
        <button class="submit-button" type="submit">دخول النظام</button>
      </form>
      <p class="login-hint">الحساب الافتراضي: admin@propify.local / password</p>
    </section>
  </div>

  <div v-else class="app-shell" :class="shellClasses" dir="rtl">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar" aria-hidden="true">ع</div>
        <div class="profile-text">
          <strong>{{ currentUser?.name }}</strong>
          <span>{{ currentUser?.role }}</span>
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
          <h1>{{ activeSectionLabel }}</h1>
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
          <button class="text-button ghost-button" type="button" @click="logout">خروج</button>
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

      <section v-if="showSection('dashboard')" class="stats-grid" aria-label="ملخص المؤشرات">
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

      <section v-if="showSection('properties', 'clients', 'contracts')" class="operations-grid">
        <article v-if="showSection('properties')" class="panel form-panel">
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
            <button class="submit-button" type="submit"><Plus :size="18" /> {{ editingPropertyCode ? 'حفظ التعديل' : 'إضافة العقار' }}</button>
            <button v-if="editingPropertyCode" class="text-button ghost-button" type="button" @click="resetPropertyForm">إلغاء التعديل</button>
          </form>
        </article>

        <article v-if="showSection('clients')" class="panel form-panel">
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
            <button class="submit-button" type="submit"><Plus :size="18" /> {{ editingClientId ? 'حفظ التعديل' : 'إضافة العميل' }}</button>
            <button v-if="editingClientId" class="text-button ghost-button" type="button" @click="resetClientForm">إلغاء التعديل</button>
          </form>
        </article>

        <article v-if="showSection('properties')" class="panel form-panel wide-operation">
          <div class="panel-header">
            <div>
              <p class="eyebrow">ملفات العقار</p>
              <h2>رفع صور ومستندات</h2>
            </div>
            <Building2 :size="22" />
          </div>
          <form class="smart-form media-form" @submit.prevent="uploadPropertyMedia">
            <label>
              <span>العقار</span>
              <select v-model="mediaForm.propertyCode" @change="loadPropertyMedia()">
                <option value="">اختر العقار</option>
                <option v-for="property in properties" :key="property.code" :value="property.code">{{ property.code }} · {{ property.area }}</option>
              </select>
              <small v-if="mediaErrors.propertyCode" class="field-error"><AlertCircle :size="14" />{{ mediaErrors.propertyCode[0] }}</small>
            </label>
            <label>
              <span>الملفات</span>
              <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" @change="onMediaFilesChange" />
              <small v-if="mediaErrors.files" class="field-error"><AlertCircle :size="14" />{{ mediaErrors.files[0] }}</small>
            </label>
            <button class="submit-button" type="submit"><Plus :size="18" /> رفع الملفات</button>
          </form>
          <div class="media-list">
            <a v-for="media in propertyMedia" :key="media.id" :href="media.url" target="_blank" rel="noreferrer">
              <FileText :size="17" />
              <span>{{ media.name }}</span>
              <small>{{ media.kind === 'image' ? 'صورة' : 'مستند' }}</small>
            </a>
            <p v-if="mediaForm.propertyCode && propertyMedia.length === 0" class="empty-note">لا توجد ملفات مرفوعة لهذا العقار بعد.</p>
          </div>
        </article>

        <article v-if="showSection('contracts')" class="panel form-panel wide-operation">
          <div class="panel-header">
            <div>
              <p class="eyebrow">العقود والتقسيط</p>
              <h2>إنشاء عقد جديد</h2>
            </div>
            <FileCheck2 :size="22" />
          </div>
          <form class="smart-form contract-form" @submit.prevent="addContract">
            <label>
              <span>رقم العقار</span>
              <select v-model="contractForm.propertyCode">
                <option value="">اختر العقار</option>
                <option v-for="property in properties" :key="property.code" :value="property.code">{{ property.code }} · {{ property.area }}</option>
              </select>
              <small v-if="contractErrors.propertyCode" class="field-error"><AlertCircle :size="14" />{{ contractErrors.propertyCode }}</small>
            </label>
            <label>
              <span>العميل</span>
              <select v-model="contractForm.client">
                <option value="">اختر العميل</option>
                <option v-for="client in clients" :key="client.phone" :value="client.name">{{ client.name }} · {{ client.role }}</option>
              </select>
              <small v-if="contractErrors.client" class="field-error"><AlertCircle :size="14" />{{ contractErrors.client }}</small>
            </label>
            <label>
              <span>نوع العقد</span>
              <select v-model="contractForm.kind">
                <option>بيع نقدي</option>
                <option>تقسيط</option>
                <option>إيجار</option>
              </select>
            </label>
            <label>
              <span>قيمة العقد</span>
              <input v-model="contractForm.total" inputmode="numeric" placeholder="150000000" />
              <small v-if="contractErrors.total" class="field-error"><AlertCircle :size="14" />{{ contractErrors.total }}</small>
            </label>
            <label>
              <span>المدفوع</span>
              <input v-model="contractForm.paid" inputmode="numeric" placeholder="30000000" />
              <small v-if="contractErrors.paid" class="field-error"><AlertCircle :size="14" />{{ contractErrors.paid }}</small>
            </label>
            <label>
              <span>عمولة المكتب %</span>
              <input v-model="contractForm.commissionRate" inputmode="decimal" />
            </label>
            <label v-if="contractForm.kind === 'تقسيط'">
              <span>عدد الأقساط</span>
              <input v-model="contractForm.installmentsCount" inputmode="numeric" />
              <small v-if="contractErrors.installmentsCount" class="field-error"><AlertCircle :size="14" />{{ contractErrors.installmentsCount }}</small>
            </label>
            <label>
              <span>الحالة</span>
              <select v-model="contractForm.status">
                <option>نشط</option>
                <option>مكتمل</option>
                <option>شهري</option>
              </select>
            </label>
            <button class="submit-button" type="submit"><Plus :size="18" /> إنشاء العقد</button>
          </form>
        </article>
      </section>

      <section class="content-grid">
        <article v-if="showSection('dashboard', 'properties')" class="panel wide">
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
                  <th>الملفات</th>
                  <th>المالك</th>
                  <th>الإجراءات</th>
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
                  <td>{{ property.mediaCount || 0 }}</td>
                  <td>{{ property.owner }}</td>
                  <td>
                    <div class="row-actions">
                      <button class="mini-button" type="button" title="تعديل" @click="startEditProperty(property)">ت</button>
                      <button class="mini-button" type="button" title="اعتماد" @click="approveProperty(property)">✓</button>
                      <button class="mini-button danger-action" type="button" title="حذف" @click="deleteProperty(property)">×</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="filteredProperties.length === 0">
                  <td colspan="9" class="empty-cell">لا توجد عقارات مطابقة للبحث أو الفلتر الحالي.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        <article v-if="showSection('dashboard', 'clients')" class="panel">
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
              <div class="row-actions">
                <small>{{ client.stage }}</small>
                <button class="mini-button" type="button" title="تعديل" @click="startEditClient(client)">ت</button>
                <button class="mini-button danger-action" type="button" title="حذف" @click="deleteClient(client)">×</button>
              </div>
            </div>
          </div>
        </article>

        <article v-if="showSection('dashboard')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">الإشعارات</p>
              <h2>تنبيهات اليوم</h2>
            </div>
            <Bell :size="22" />
          </div>
          <div class="notification-list">
            <p v-for="notification in visibleNotifications" :key="notification.id" :class="notification.severity">
              <strong>{{ notification.title }}</strong>
              <span>{{ notification.message }}</span>
            </p>
            <p v-if="visibleNotifications.length === 0" class="info">
              <strong>لا توجد تنبيهات</strong>
              <span>كل شيء مستقر حاليا.</span>
            </p>
          </div>
        </article>

        <article v-if="showSection('dashboard', 'contracts')" class="panel wide">
          <div class="panel-header">
            <div>
              <p class="eyebrow">العقود</p>
              <h2>البيع، الإيجار، التقسيط</h2>
            </div>
            <FileText :size="22" />
          </div>
          <div class="contracts">
            <div v-for="contract in contracts" :key="contract.code" class="contract-card">
              <FileText :size="22" />
              <div>
                <strong>{{ contract.code }}</strong>
                <span>{{ contract.client }} · {{ contract.kind }}</span>
              </div>
              <div class="money">
                <span>مدفوع {{ formatMoney(contract.paid) }}</span>
                <span>متبقي {{ formatMoney(contract.due) }}</span>
              </div>
              <span class="badge" :class="statusClass(contract.status)">{{ contract.status }}</span>
              <button class="mini-button" type="button" title="طباعة العقد" @click="printContract(contract)">
                <Printer :size="17" />
              </button>
            </div>
          </div>
        </article>

        <article v-if="showSection('finance')" class="panel wide">
          <div class="panel-header">
            <div>
              <p class="eyebrow">الحسابات</p>
              <h2>سند قبض / سند دفع</h2>
            </div>
            <WalletCards :size="22" />
          </div>
          <form class="smart-form finance-form" @submit.prevent="addVoucher">
            <label>
              <span>نوع السند</span>
              <select v-model="voucherForm.type">
                <option>قبض</option>
                <option>دفع</option>
              </select>
            </label>
            <label>
              <span>الطرف</span>
              <input v-model="voucherForm.client" placeholder="اسم العميل أو المستلم" />
              <small v-if="voucherErrors.client" class="field-error"><AlertCircle :size="14" />{{ voucherErrors.client }}</small>
            </label>
            <label>
              <span>المبلغ / دينار</span>
              <input v-model="voucherForm.amount" inputmode="numeric" placeholder="2000000" />
              <small v-if="voucherErrors.amount" class="field-error"><AlertCircle :size="14" />{{ voucherErrors.amount }}</small>
            </label>
            <label>
              <span>السبب</span>
              <input v-model="voucherForm.reason" placeholder="مقدم شراء عقار، مصروف إعلان..." />
              <small v-if="voucherErrors.reason" class="field-error"><AlertCircle :size="14" />{{ voucherErrors.reason }}</small>
            </label>
            <label>
              <span>العقار</span>
              <input v-model="voucherForm.propertyCode" placeholder="PR-2026-000145" />
            </label>
            <label>
              <span>العقد</span>
              <input v-model="voucherForm.contractCode" placeholder="CT-2026-000044" />
            </label>
            <button class="submit-button" type="submit"><Plus :size="18" /> حفظ السند</button>
          </form>
        </article>

        <article v-if="showSection('dashboard', 'finance')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">Ledger</p>
              <h2>رصيد المكتب</h2>
            </div>
            <CircleDollarSign :size="22" />
          </div>
          <div class="finance-summary">
            <div><span>الإيرادات</span><strong>{{ formatMoney(financialSummary.income) }}</strong></div>
            <div><span>المصروفات</span><strong>{{ formatMoney(financialSummary.expenses) }}</strong></div>
            <div><span>الرصيد</span><strong>{{ formatMoney(financialSummary.balance) }}</strong></div>
          </div>
        </article>

        <article v-if="showSection('dashboard', 'contracts')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">الأقساط</p>
              <h2>أقرب الاستحقاقات</h2>
            </div>
            <CalendarClock :size="22" />
          </div>
          <div class="stack-list">
            <div v-for="installment in installments.slice(0, 4)" :key="`${installment.contractCode}-${installment.number}`" class="list-row">
              <div>
                <strong>{{ installment.contractCode }}</strong>
                <span>القسط {{ installment.number }} · {{ installment.dueDate }}</span>
              </div>
              <small>{{ formatMoney(installment.amount) }} · {{ installment.status }}</small>
            </div>
          </div>
        </article>

        <article v-if="showSection('dashboard', 'reports')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">التقارير</p>
              <h2>ملخص مالي وتشغيلي</h2>
            </div>
            <ChevronLeft :size="22" />
          </div>
          <div class="report-grid">
            <div>
              <span>الإيرادات</span>
              <strong>{{ formatMoney(financialReport?.income || financialSummary.income) }}</strong>
            </div>
            <div>
              <span>عمولات المكتب</span>
              <strong>{{ formatMoney(financialReport?.officeCommission || 0) }}</strong>
            </div>
            <div>
              <span>قيمة العقارات</span>
              <strong>{{ formatMoney(propertiesReport?.totalValue || 0) }}</strong>
            </div>
            <div>
              <span>متبقي الأقساط</span>
              <strong>{{ formatMoney(installmentsReport?.remainingTotal || 0) }}</strong>
            </div>
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

        <article v-if="showSection('permissions')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">الصلاحيات</p>
              <h2>المستخدمون والأدوار</h2>
            </div>
            <ShieldCheck :size="22" />
          </div>
          <form class="smart-form user-form" @submit.prevent="addUser">
            <label>
              <span>الاسم</span>
              <input v-model="userForm.name" placeholder="اسم المستخدم" />
              <small v-if="userErrors.name" class="field-error"><AlertCircle :size="14" />{{ userErrors.name[0] }}</small>
            </label>
            <label>
              <span>البريد</span>
              <input v-model="userForm.email" type="email" placeholder="user@propify.local" />
              <small v-if="userErrors.email" class="field-error"><AlertCircle :size="14" />{{ userErrors.email[0] }}</small>
            </label>
            <label>
              <span>كلمة المرور</span>
              <input v-model="userForm.password" type="password" placeholder="6 أحرف أو أكثر" />
              <small v-if="userErrors.password" class="field-error"><AlertCircle :size="14" />{{ userErrors.password[0] }}</small>
            </label>
            <label>
              <span>الدور</span>
              <select v-model="userForm.role">
                <option value="office_manager">مدير المكتب</option>
                <option value="sales">موظف مبيعات</option>
                <option value="accountant">محاسب</option>
                <option value="data_entry">مدخل بيانات</option>
                <option value="property_supervisor">مشرف عقارات</option>
              </select>
            </label>
            <div class="permission-picker">
              <label v-for="permission in availablePermissions" :key="permission.key" class="check-line">
                <input v-model="userForm.permissions" type="checkbox" :value="permission.key" />
                <span>{{ permission.label }}</span>
              </label>
            </div>
            <button class="submit-button" type="submit"><Plus :size="18" /> إضافة مستخدم</button>
          </form>
          <div class="stack-list users-list">
            <div v-for="user in users" :key="user.email" class="list-row">
              <div>
                <strong>{{ user.name }}</strong>
                <span>{{ user.email }} · {{ roleLabel(user.role) }}</span>
              </div>
              <small>{{ user.permissions.length }} صلاحيات</small>
            </div>
          </div>
        </article>

        <article v-if="showSection('settings')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">الإعدادات</p>
              <h2>حالة النظام</h2>
            </div>
            <Settings :size="22" />
          </div>
          <div class="stack-list">
            <div class="list-row">
              <div>
                <strong>واجهة API</strong>
                <span>{{ API_BASE_URL }}</span>
              </div>
              <small>{{ apiOnline ? 'متصل' : 'غير متصل' }}</small>
            </div>
            <div class="list-row">
              <div>
                <strong>المستخدم الحالي</strong>
                <span>{{ currentUser?.email }} · {{ roleLabel(currentUser?.role) }}</span>
              </div>
              <small>{{ currentUser?.permissions?.length || 0 }} صلاحيات</small>
            </div>
            <div class="list-row">
              <div>
                <strong>المظهر</strong>
                <span>{{ darkMode ? 'داكن' : 'فاتح' }}</span>
              </div>
              <button class="mini-button" type="button" :title="darkMode ? 'الثيم الفاتح' : 'الثيم الداكن'" @click="darkMode = !darkMode">
                <Sun v-if="darkMode" :size="17" />
                <Moon v-else :size="17" />
              </button>
            </div>
          </div>
        </article>
      </section>
    </main>
  </div>
</template>
