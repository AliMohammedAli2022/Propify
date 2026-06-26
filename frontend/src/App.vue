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
const propertySort = ref({ key: 'code', direction: 'desc' })
const propertyPage = ref(1)
const propertyPageSize = 8
const clientSort = ref({ key: 'name', direction: 'asc' })
const clientPage = ref(1)
const clientPageSize = 6
const contractSort = ref({ key: 'code', direction: 'desc' })
const contractPage = ref(1)
const contractPageSize = 5
const voucherSort = ref({ key: 'code', direction: 'desc' })
const voucherPage = ref(1)
const voucherPageSize = 6
const installmentSort = ref({ key: 'dueDate', direction: 'asc' })
const installmentPage = ref(1)
const installmentPageSize = 5
const activitySort = ref({ key: 'createdAt', direction: 'desc' })
const activityPage = ref(1)
const activityPageSize = 6
const userSort = ref({ key: 'name', direction: 'asc' })
const userPage = ref(1)
const userPageSize = 6
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

const sectionPermissions = {
  properties: ['properties.create', 'properties.update', 'properties.approve'],
  clients: ['clients.manage'],
  contracts: ['contracts.create', 'contracts.print'],
  finance: ['vouchers.manage'],
  reports: ['reports.view'],
  permissions: ['users.manage'],
  settings: ['settings.update'],
}

const hasPermission = (permission) => (
  !permission
  || currentUser.value?.role === 'system_admin'
  || currentUser.value?.permissions?.includes(permission)
)

const hasAnyPermission = (permissions = []) => permissions.length === 0 || permissions.some((permission) => hasPermission(permission))
const canSeeSection = (section) => section === 'dashboard' || hasAnyPermission(sectionPermissions[section] || [])
const visibleNavItems = computed(() => navItems.filter((item) => canSeeSection(item.id)))
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
const employeePerformanceReport = ref(null)
const notifications = ref([])
const activityLogs = ref([])
const propertyMedia = ref([])
const mediaErrors = ref({})
const mediaForm = ref({
  propertyCode: '',
  files: [],
})
const settingsErrors = ref({})
const settingsForm = ref({
  companyName: 'Propify',
  companyPhone: '07700000000',
  companyEmail: 'office@propify.local',
  companyAddress: 'بغداد - العراق',
  defaultCurrency: 'دينار',
  defaultCommissionRate: 2,
})
const reportFilters = ref({
  from: '',
  to: '',
  status: 'الكل',
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
const editingUserId = ref('')

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
    error.status = response.status
    throw error
  }
  return data
}

const optionalApiRequest = async (path, fallback) => {
  try {
    return await apiRequest(path)
  } catch (error) {
    if (error.status === 403) return fallback
    throw error
  }
}

const reportPath = (report, extra = {}) => {
  const params = new URLSearchParams()
  const filters = { ...reportFilters.value, ...extra }

  Object.entries(filters).forEach(([key, value]) => {
    if (value && value !== 'الكل') params.set(key, value)
  })

  const query = params.toString()
  return `/reports/${report}${query ? `?${query}` : ''}`
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
      serverEmployeePerformanceReport,
      serverNotifications,
      serverActivityLogs,
      serverSettings,
    ] = await Promise.all([
      apiRequest('/properties'),
      optionalApiRequest('/clients', []),
      apiRequest('/contracts'),
      apiRequest('/installments'),
      optionalApiRequest('/vouchers', []),
      optionalApiRequest('/ledger', []),
      optionalApiRequest('/users', []),
      optionalApiRequest(reportPath('financial'), null),
      optionalApiRequest(reportPath('properties'), null),
      optionalApiRequest(reportPath('installments'), null),
      optionalApiRequest(reportPath('employee-performance'), null),
      apiRequest('/notifications'),
      optionalApiRequest('/activity-logs', []),
      optionalApiRequest('/settings', settingsForm.value),
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
    employeePerformanceReport.value = serverEmployeePerformanceReport
    notifications.value = serverNotifications
    activityLogs.value = serverActivityLogs
    settingsForm.value = { ...settingsForm.value, ...serverSettings }
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

const saveSettings = () => {
  settingsErrors.value = {}

  apiRequest('/settings', {
    method: 'PUT',
    body: JSON.stringify(settingsForm.value),
  })
    .then((savedSettings) => {
      settingsForm.value = { ...settingsForm.value, ...savedSettings }
      apiOnline.value = true
      showSuccess('تم تحديث إعدادات المكتب بنجاح.')
    })
    .catch((error) => {
      apiOnline.value = false
      settingsErrors.value = error.errors || {}
    })
}

const validateUser = () => {
  const errors = {}

  if (!userForm.value.name.trim()) errors.name = ['يرجى إدخال اسم المستخدم.']
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(userForm.value.email)) errors.email = ['يرجى إدخال بريد إلكتروني صحيح.']
  if (!editingUserId.value && userForm.value.password.length < 6) errors.password = ['كلمة المرور يجب ألا تقل عن 6 أحرف.']
  if (editingUserId.value && userForm.value.password && userForm.value.password.length < 6) errors.password = ['كلمة المرور يجب ألا تقل عن 6 أحرف.']

  userErrors.value = errors
  return Object.keys(errors).length === 0
}

const addUser = () => {
  if (!validateUser()) return
  const endpoint = editingUserId.value ? `/users/${editingUserId.value}` : '/users'
  const method = editingUserId.value ? 'PUT' : 'POST'

  apiRequest(endpoint, {
    method,
    body: JSON.stringify(userForm.value),
  })
    .then((createdUser) => {
      if (editingUserId.value) {
        users.value = users.value.map((user) => (user.id === createdUser.id ? createdUser : user))
      } else {
        users.value.unshift(createdUser)
      }
      userErrors.value = {}
      apiOnline.value = true
      showSuccess(editingUserId.value ? 'تم تحديث المستخدم وصلاحياته.' : 'تم إنشاء المستخدم وتحديد صلاحياته.')
      resetUserForm()
    })
    .catch((error) => {
      apiOnline.value = false
      userErrors.value = error.errors || {}
    })
}

const resetUserForm = () => {
  userForm.value.name = ''
  userForm.value.email = ''
  userForm.value.password = ''
  userForm.value.role = 'sales'
  userForm.value.permissions = ['properties.create', 'clients.manage']
  editingUserId.value = ''
}

const startEditUser = (user) => {
  editingUserId.value = user.id
  userForm.value.name = user.name
  userForm.value.email = user.email
  userForm.value.password = ''
  userForm.value.role = user.role
  userForm.value.permissions = [...(user.permissions || [])]
  activeSection.value = 'permissions'
  showSuccess(`تم تحميل المستخدم ${user.name} للتعديل.`)
}

const deleteUser = (user) => {
  if (!window.confirm(`هل تريد حذف المستخدم ${user.name}؟`)) return

  apiRequest(`/users/${user.id}`, { method: 'DELETE' })
    .then(() => {
      users.value = users.value.filter((item) => item.id !== user.id)
      apiOnline.value = true
      showSuccess(`تم حذف المستخدم ${user.name}.`)
      return loadApiData()
    })
    .catch((error) => {
      apiOnline.value = false
      const message = error.errors?.user?.[0] || error.errors?.role?.[0] || 'تعذر حذف المستخدم.'
      showSuccess(message)
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
const editingVoucherCode = ref('')

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
const editingContractCode = ref('')

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

  const endpoint = editingContractCode.value ? `/contracts/${editingContractCode.value}` : '/contracts'
  const method = editingContractCode.value ? 'PUT' : 'POST'

  apiRequest(endpoint, {
    method,
    body: JSON.stringify(payload),
  })
    .then((createdContract) => {
      if (editingContractCode.value) {
        contracts.value = contracts.value.map((contract) => (contract.code === createdContract.code ? createdContract : contract))
      } else {
        contracts.value.unshift(createdContract)
      }
      return apiRequest('/installments')
    })
    .then((serverInstallments) => {
      installments.value = serverInstallments
      apiOnline.value = true
      showSuccess(editingContractCode.value ? 'تم تحديث بيانات العقد.' : 'تم إنشاء العقد وتحديث جدول الأقساط.')
      resetContractForm()
    })
    .catch((error) => {
      apiOnline.value = false
      if (Object.keys(error.errors || {}).length > 0) {
        contractErrors.value = error.errors
        return
      }
      if (!editingContractCode.value) {
        contracts.value.unshift({ ...payload, code: `LOCAL-${Date.now()}`, due: payload.total - payload.paid, commission: Math.round(payload.total * (payload.commissionRate / 100)) })
        showSuccess('تمت إضافة العقد محلياً. شغّل API لحفظه في السيرفر.')
      }
    })
}

const resetContractForm = () => {
  contractForm.value.propertyCode = ''
  contractForm.value.client = ''
  contractForm.value.kind = 'بيع نقدي'
  contractForm.value.total = ''
  contractForm.value.paid = ''
  contractForm.value.commissionRate = settingsForm.value.defaultCommissionRate || 2
  contractForm.value.installmentsCount = 12
  contractForm.value.status = 'نشط'
  editingContractCode.value = ''
}

const startEditContract = (contract) => {
  if (!contract.code?.startsWith('CT-')) {
    showSuccess('هذا العقد محلي فقط، أعد تحميل API قبل تعديله.')
    return
  }

  editingContractCode.value = contract.code
  contractForm.value.propertyCode = contract.propertyCode
  contractForm.value.client = contract.client
  contractForm.value.kind = contract.kind
  contractForm.value.total = String(contract.total || '')
  contractForm.value.paid = String(contract.paid || '')
  contractForm.value.commissionRate = contract.total ? Number(((Number(contract.commission || 0) / Number(contract.total || 1)) * 100).toFixed(2)) : 2
  contractForm.value.installmentsCount = 12
  contractForm.value.status = contract.status
  activeSection.value = 'contracts'
  showSuccess(`تم تحميل العقد ${contract.code} للتعديل.`)
}

const deleteContract = (contract) => {
  if (!contract.code?.startsWith('CT-')) {
    contracts.value = contracts.value.filter((item) => item.code !== contract.code)
    showSuccess(`تم حذف العقد المحلي ${contract.code}.`)
    return
  }

  if (!window.confirm(`هل تريد حذف العقد ${contract.code}؟`)) return

  apiRequest(`/contracts/${contract.code}`, { method: 'DELETE' })
    .then(() => {
      contracts.value = contracts.value.filter((item) => item.code !== contract.code)
      installments.value = installments.value.filter((item) => item.contractCode !== contract.code)
      apiOnline.value = true
      showSuccess(`تم حذف العقد ${contract.code}.`)
      return loadApiData()
    })
    .catch((error) => {
      apiOnline.value = false
      const message = error.errors?.contract?.[0] || 'تعذر حذف العقد.'
      showSuccess(message)
    })
}

const payInstallment = (installment) => {
  if (installment.status === 'مدفوع') return

  apiRequest(`/installments/${installment.id}/pay`, { method: 'POST' })
    .then(() => Promise.all([
      apiRequest('/contracts'),
      apiRequest('/installments'),
      apiRequest('/vouchers'),
      apiRequest('/ledger'),
      apiRequest(reportPath('financial')),
      apiRequest(reportPath('installments')),
      apiRequest('/notifications'),
    ]))
    .then(([serverContracts, serverInstallments, serverVouchers, serverLedger, serverFinancialReport, serverInstallmentsReport, serverNotifications]) => {
      contracts.value = serverContracts
      installments.value = serverInstallments
      vouchers.value = serverVouchers
      ledger.value = serverLedger
      financialReport.value = serverFinancialReport
      installmentsReport.value = serverInstallmentsReport
      notifications.value = serverNotifications
      apiOnline.value = true
      showSuccess(`تم تسديد القسط ${installment.number} للعقد ${installment.contractCode}.`)
    })
    .catch((error) => {
      apiOnline.value = false
      const message = error.errors?.installment?.[0] || error.errors?.amount?.[0] || 'تعذر تسديد القسط.'
      showSuccess(message)
    })
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
  const endpoint = editingVoucherCode.value ? `/vouchers/${editingVoucherCode.value}` : '/vouchers'
  const method = editingVoucherCode.value ? 'PUT' : 'POST'

  apiRequest(endpoint, {
    method,
    body: JSON.stringify(payload),
  })
    .then((createdVoucher) => {
      if (editingVoucherCode.value) {
        vouchers.value = vouchers.value.map((voucher) => (voucher.code === createdVoucher.code ? createdVoucher : voucher))
      } else {
        vouchers.value.unshift(createdVoucher)
      }
      return Promise.all([
        apiRequest('/ledger'),
        apiRequest(reportPath('financial')),
        apiRequest('/notifications'),
      ])
    })
    .then(([serverLedger, serverFinancialReport, serverNotifications]) => {
      ledger.value = serverLedger
      financialReport.value = serverFinancialReport
      notifications.value = serverNotifications
      apiOnline.value = true
      showSuccess(editingVoucherCode.value ? 'تم تحديث السند ودفتر الأستاذ.' : 'تم حفظ السند وتحديث دفتر الأستاذ.')
      resetVoucherForm()
    })
    .catch((error) => {
      apiOnline.value = false
      if (Object.keys(error.errors || {}).length > 0) {
        voucherErrors.value = error.errors
        return
      }
      if (!editingVoucherCode.value) {
        vouchers.value.unshift({ ...payload, code: `LOCAL-${Date.now()}`, issuedAt: new Date().toISOString().slice(0, 10) })
        showSuccess('تمت إضافة السند محلياً. شغّل API لحفظه في السيرفر.')
      }
    })
}

const resetVoucherForm = () => {
  voucherForm.value.type = 'قبض'
  voucherForm.value.client = ''
  voucherForm.value.amount = ''
  voucherForm.value.reason = ''
  voucherForm.value.propertyCode = ''
  voucherForm.value.contractCode = ''
  editingVoucherCode.value = ''
}

const startEditVoucher = (voucher) => {
  if (!voucher.code?.startsWith('RV-') && !voucher.code?.startsWith('PV-')) {
    showSuccess('هذا السند محلي فقط، أعد تحميل API قبل تعديله.')
    return
  }

  voucherForm.value.type = voucher.type
  voucherForm.value.client = voucher.client
  voucherForm.value.amount = String(voucher.amount || '')
  voucherForm.value.reason = voucher.reason
  voucherForm.value.propertyCode = voucher.propertyCode || ''
  voucherForm.value.contractCode = voucher.contractCode || ''
  editingVoucherCode.value = voucher.code
  activeSection.value = 'finance'
  showSuccess(`تم تحميل السند ${voucher.code} للتعديل.`)
}

const deleteVoucher = (voucher) => {
  if (!voucher.code?.startsWith('RV-') && !voucher.code?.startsWith('PV-')) {
    vouchers.value = vouchers.value.filter((item) => item.code !== voucher.code)
    showSuccess(`تم حذف السند المحلي ${voucher.code}.`)
    return
  }

  if (!window.confirm(`هل تريد حذف السند ${voucher.code}؟`)) return

  apiRequest(`/vouchers/${voucher.code}`, { method: 'DELETE' })
    .then(() => Promise.all([
      apiRequest('/vouchers'),
      apiRequest('/ledger'),
      apiRequest(reportPath('financial')),
      apiRequest('/notifications'),
    ]))
    .then(([serverVouchers, serverLedger, serverFinancialReport, serverNotifications]) => {
      vouchers.value = serverVouchers
      ledger.value = serverLedger
      financialReport.value = serverFinancialReport
      notifications.value = serverNotifications
      apiOnline.value = true
      showSuccess(`تم حذف السند ${voucher.code}.`)
    })
    .catch((error) => {
      apiOnline.value = false
      const message = error.errors?.voucher?.[0] || 'تعذر حذف السند.'
      showSuccess(message)
    })
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
const reportStatusOptions = computed(() => [
  'الكل',
  ...new Set([
    ...properties.value.map((property) => property.status),
    ...installments.value.map((installment) => installment.status),
  ]),
])

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

const compareValues = (first, second, direction = 'asc', locale = 'ar') => {
  const multiplier = direction === 'asc' ? 1 : -1

  if (typeof first === 'number' && typeof second === 'number') {
    return (first - second) * multiplier
  }

  return String(first ?? '').localeCompare(String(second ?? ''), locale) * multiplier
}

const sortedProperties = computed(() => {
  const sorted = [...filteredProperties.value]
  const { key, direction } = propertySort.value

  return sorted.sort((a, b) => {
    const first = key === 'price' ? Number(String(a[key] || 0).replaceAll(',', '')) : String(a[key] ?? '')
    const second = key === 'price' ? Number(String(b[key] || 0).replaceAll(',', '')) : String(b[key] ?? '')

    return compareValues(first, second, direction)
  })
})

const propertyPageCount = computed(() => Math.max(1, Math.ceil(sortedProperties.value.length / propertyPageSize)))
const paginatedProperties = computed(() => {
  const start = (propertyPage.value - 1) * propertyPageSize
  return sortedProperties.value.slice(start, start + propertyPageSize)
})

const sortProperties = (key) => {
  propertySort.value = {
    key,
    direction: propertySort.value.key === key && propertySort.value.direction === 'asc' ? 'desc' : 'asc',
  }
  propertyPage.value = 1
}

const propertySortLabel = (key) => {
  if (propertySort.value.key !== key) return ''
  return propertySort.value.direction === 'asc' ? '↑' : '↓'
}

const nextPropertyPage = () => {
  propertyPage.value = Math.min(propertyPage.value + 1, propertyPageCount.value)
}

const previousPropertyPage = () => {
  propertyPage.value = Math.max(propertyPage.value - 1, 1)
}

const filteredClients = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return clients.value.filter((client) => {
    const searchable = [client.name, client.role, client.phone, client.stage, client.source]
      .join(' ')
      .toLowerCase()

    return !query || searchable.includes(query)
  })
})

const sortedClients = computed(() => {
  const sorted = [...filteredClients.value]
  const { key, direction } = clientSort.value

  return sorted.sort((a, b) => compareValues(a[key], b[key], direction))
})

const clientPageCount = computed(() => Math.max(1, Math.ceil(sortedClients.value.length / clientPageSize)))
const paginatedClients = computed(() => {
  const start = (clientPage.value - 1) * clientPageSize
  return sortedClients.value.slice(start, start + clientPageSize)
})

const sortClients = (key) => {
  clientSort.value = {
    key,
    direction: clientSort.value.key === key && clientSort.value.direction === 'asc' ? 'desc' : 'asc',
  }
  clientPage.value = 1
}

const clientSortLabel = (key) => {
  if (clientSort.value.key !== key) return ''
  return clientSort.value.direction === 'asc' ? '↑' : '↓'
}

const nextClientPage = () => {
  clientPage.value = Math.min(clientPage.value + 1, clientPageCount.value)
}

const previousClientPage = () => {
  clientPage.value = Math.max(clientPage.value - 1, 1)
}

const filteredContracts = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return contracts.value.filter((contract) => {
    const searchable = [
      contract.code,
      contract.client,
      contract.kind,
      contract.status,
      contract.propertyCode,
      contract.total,
      contract.paid,
      contract.due,
    ]
      .join(' ')
      .toLowerCase()

    return !query || searchable.includes(query)
  })
})

const sortedContracts = computed(() => {
  const sorted = [...filteredContracts.value]
  const { key, direction } = contractSort.value

  return sorted.sort((a, b) => {
    const numericKeys = ['total', 'paid', 'due', 'commission']
    const first = numericKeys.includes(key) ? Number(a[key] || 0) : a[key]
    const second = numericKeys.includes(key) ? Number(b[key] || 0) : b[key]

    return compareValues(first, second, direction)
  })
})

const contractPageCount = computed(() => Math.max(1, Math.ceil(sortedContracts.value.length / contractPageSize)))
const paginatedContracts = computed(() => {
  const start = (contractPage.value - 1) * contractPageSize
  return sortedContracts.value.slice(start, start + contractPageSize)
})

const sortContracts = (key) => {
  contractSort.value = {
    key,
    direction: contractSort.value.key === key && contractSort.value.direction === 'asc' ? 'desc' : 'asc',
  }
  contractPage.value = 1
}

const contractSortLabel = (key) => {
  if (contractSort.value.key !== key) return ''
  return contractSort.value.direction === 'asc' ? '↑' : '↓'
}

const nextContractPage = () => {
  contractPage.value = Math.min(contractPage.value + 1, contractPageCount.value)
}

const previousContractPage = () => {
  contractPage.value = Math.max(contractPage.value - 1, 1)
}

const filteredVouchers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return vouchers.value.filter((voucher) => {
    const searchable = [
      voucher.code,
      voucher.type,
      voucher.client,
      voucher.reason,
      voucher.amount,
      voucher.propertyCode,
      voucher.contractCode,
      voucher.issuedAt,
    ]
      .join(' ')
      .toLowerCase()

    return !query || searchable.includes(query)
  })
})

const sortedVouchers = computed(() => {
  const sorted = [...filteredVouchers.value]
  const { key, direction } = voucherSort.value

  return sorted.sort((a, b) => {
    const first = key === 'amount' ? Number(a[key] || 0) : a[key]
    const second = key === 'amount' ? Number(b[key] || 0) : b[key]

    return compareValues(first, second, direction)
  })
})

const voucherPageCount = computed(() => Math.max(1, Math.ceil(sortedVouchers.value.length / voucherPageSize)))
const paginatedVouchers = computed(() => {
  const start = (voucherPage.value - 1) * voucherPageSize
  return sortedVouchers.value.slice(start, start + voucherPageSize)
})

const sortVouchers = (key) => {
  voucherSort.value = {
    key,
    direction: voucherSort.value.key === key && voucherSort.value.direction === 'asc' ? 'desc' : 'asc',
  }
  voucherPage.value = 1
}

const voucherSortLabel = (key) => {
  if (voucherSort.value.key !== key) return ''
  return voucherSort.value.direction === 'asc' ? '↑' : '↓'
}

const nextVoucherPage = () => {
  voucherPage.value = Math.min(voucherPage.value + 1, voucherPageCount.value)
}

const previousVoucherPage = () => {
  voucherPage.value = Math.max(voucherPage.value - 1, 1)
}

const filteredInstallments = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return installments.value.filter((installment) => {
    const searchable = [
      installment.contractCode,
      installment.number,
      installment.dueDate,
      installment.status,
      installment.amount,
      installment.paidAmount,
    ]
      .join(' ')
      .toLowerCase()

    return !query || searchable.includes(query)
  })
})

const sortedInstallments = computed(() => {
  const sorted = [...filteredInstallments.value]
  const { key, direction } = installmentSort.value

  return sorted.sort((a, b) => {
    const numericKeys = ['amount', 'paidAmount', 'number']
    const first = numericKeys.includes(key) ? Number(a[key] || 0) : a[key]
    const second = numericKeys.includes(key) ? Number(b[key] || 0) : b[key]

    return compareValues(first, second, direction)
  })
})

const installmentPageCount = computed(() => Math.max(1, Math.ceil(sortedInstallments.value.length / installmentPageSize)))
const paginatedInstallments = computed(() => {
  const start = (installmentPage.value - 1) * installmentPageSize
  return sortedInstallments.value.slice(start, start + installmentPageSize)
})

const sortInstallments = (key) => {
  installmentSort.value = {
    key,
    direction: installmentSort.value.key === key && installmentSort.value.direction === 'asc' ? 'desc' : 'asc',
  }
  installmentPage.value = 1
}

const installmentSortLabel = (key) => {
  if (installmentSort.value.key !== key) return ''
  return installmentSort.value.direction === 'asc' ? '↑' : '↓'
}

const nextInstallmentPage = () => {
  installmentPage.value = Math.min(installmentPage.value + 1, installmentPageCount.value)
}

const previousInstallmentPage = () => {
  installmentPage.value = Math.max(installmentPage.value - 1, 1)
}

const filteredActivityLogs = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return activityLogs.value.filter((activity) => {
    const searchable = [
      activity.summary,
      activity.userName,
      activity.action,
      activity.createdAt,
    ]
      .join(' ')
      .toLowerCase()

    return !query || searchable.includes(query)
  })
})

const sortedActivityLogs = computed(() => {
  const sorted = [...filteredActivityLogs.value]
  const { key, direction } = activitySort.value

  return sorted.sort((a, b) => compareValues(a[key], b[key], direction))
})

const activityPageCount = computed(() => Math.max(1, Math.ceil(sortedActivityLogs.value.length / activityPageSize)))
const paginatedActivityLogs = computed(() => {
  const start = (activityPage.value - 1) * activityPageSize
  return sortedActivityLogs.value.slice(start, start + activityPageSize)
})

const sortActivityLogs = (key) => {
  activitySort.value = {
    key,
    direction: activitySort.value.key === key && activitySort.value.direction === 'asc' ? 'desc' : 'asc',
  }
  activityPage.value = 1
}

const activitySortLabel = (key) => {
  if (activitySort.value.key !== key) return ''
  return activitySort.value.direction === 'asc' ? '↑' : '↓'
}

const nextActivityPage = () => {
  activityPage.value = Math.min(activityPage.value + 1, activityPageCount.value)
}

const previousActivityPage = () => {
  activityPage.value = Math.max(activityPage.value - 1, 1)
}

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return users.value.filter((user) => {
    const searchable = [
      user.name,
      user.email,
      roleLabel(user.role),
      user.role,
      ...(user.permissions || []),
    ]
      .join(' ')
      .toLowerCase()

    return !query || searchable.includes(query)
  })
})

const sortedUsers = computed(() => {
  const sorted = [...filteredUsers.value]
  const { key, direction } = userSort.value

  return sorted.sort((a, b) => {
    const first = key === 'permissions' ? (a.permissions || []).length : a[key]
    const second = key === 'permissions' ? (b.permissions || []).length : b[key]

    return compareValues(first, second, direction)
  })
})

const userPageCount = computed(() => Math.max(1, Math.ceil(sortedUsers.value.length / userPageSize)))
const paginatedUsers = computed(() => {
  const start = (userPage.value - 1) * userPageSize
  return sortedUsers.value.slice(start, start + userPageSize)
})

const sortUsers = (key) => {
  userSort.value = {
    key,
    direction: userSort.value.key === key && userSort.value.direction === 'asc' ? 'desc' : 'asc',
  }
  userPage.value = 1
}

const userSortLabel = (key) => {
  if (userSort.value.key !== key) return ''
  return userSort.value.direction === 'asc' ? '↑' : '↓'
}

const nextUserPage = () => {
  userPage.value = Math.min(userPage.value + 1, userPageCount.value)
}

const previousUserPage = () => {
  userPage.value = Math.max(userPage.value - 1, 1)
}

const downloadFile = (filename, content, type = 'text/csv;charset=utf-8', withBom = true) => {
  const blob = new Blob([withBom ? '\ufeff' : '', content], { type })
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

const loadReports = async () => {
  try {
    const [
      serverFinancialReport,
      serverPropertiesReport,
      serverInstallmentsReport,
      serverEmployeePerformanceReport,
    ] = await Promise.all([
      apiRequest(reportPath('financial')),
      apiRequest(reportPath('properties')),
      apiRequest(reportPath('installments')),
      apiRequest(reportPath('employee-performance')),
    ])

    financialReport.value = serverFinancialReport
    propertiesReport.value = serverPropertiesReport
    installmentsReport.value = serverInstallmentsReport
    employeePerformanceReport.value = serverEmployeePerformanceReport
    apiOnline.value = true
    showSuccess('تم تحديث التقارير حسب الفلاتر.')
  } catch {
    apiOnline.value = false
    showSuccess('تعذر تحديث التقارير من API.')
  }
}

const downloadReport = async (report, filename) => {
  try {
    const response = await fetch(`${API_BASE_URL}${reportPath(report, { export: 'csv' })}`, {
      headers: {
        ...(authToken.value ? { Authorization: `Bearer ${authToken.value}` } : {}),
      },
    })
    if (!response.ok) throw new Error('Report export failed')

    const content = await response.text()
    downloadFile(filename, content)
    apiOnline.value = true
  } catch {
    apiOnline.value = false
    showSuccess('تعذر تنزيل التقرير من API.')
  }
}

const downloadBackup = async () => {
  try {
    const response = await fetch(`${API_BASE_URL}/backup/export`, {
      headers: {
        ...(authToken.value ? { Authorization: `Bearer ${authToken.value}` } : {}),
      },
    })
    if (!response.ok) throw new Error('Backup export failed')

    const content = await response.text()
    const filename = `propify-backup-${new Date().toISOString().slice(0, 19).replaceAll(':', '-')}.json`
    downloadFile(filename, content, 'application/json;charset=utf-8', false)
    apiOnline.value = true
    showSuccess('تم تنزيل النسخة الاحتياطية.')
  } catch {
    apiOnline.value = false
    showSuccess('تعذر تنزيل النسخة الاحتياطية من API.')
  }
}

const printDocument = async (path) => {
  const printWindow = window.open('', '_blank', 'width=900,height=700')
  if (!printWindow) return

  printWindow.document.write('<p style="font-family: Tahoma, Arial; direction: rtl; padding: 24px;">جاري تجهيز المستند...</p>')

  try {
    const response = await fetch(`${API_BASE_URL}${path}`, {
      headers: {
        ...(authToken.value ? { Authorization: `Bearer ${authToken.value}` } : {}),
      },
    })
    if (!response.ok) throw new Error('Print document failed')

    printWindow.document.open()
    printWindow.document.write(await response.text())
    printWindow.document.close()
    printWindow.focus()
    apiOnline.value = true
  } catch {
    printWindow.close()
    apiOnline.value = false
    showSuccess('تعذر تجهيز مستند الطباعة من API.')
  }
}

const printContract = (contract) => printDocument(`/contracts/${contract.code}/print`)

const printVoucher = (voucher) => printDocument(`/vouchers/${voucher.code}/print`)

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

watch([searchQuery, statusFilter], () => {
  propertyPage.value = 1
  clientPage.value = 1
  contractPage.value = 1
  voucherPage.value = 1
  installmentPage.value = 1
  activityPage.value = 1
  userPage.value = 1
})

watch(propertyPageCount, (pageCount) => {
  propertyPage.value = Math.min(propertyPage.value, pageCount)
})

watch(clientPageCount, (pageCount) => {
  clientPage.value = Math.min(clientPage.value, pageCount)
})

watch(contractPageCount, (pageCount) => {
  contractPage.value = Math.min(contractPage.value, pageCount)
})

watch(voucherPageCount, (pageCount) => {
  voucherPage.value = Math.min(voucherPage.value, pageCount)
})

watch(installmentPageCount, (pageCount) => {
  installmentPage.value = Math.min(installmentPage.value, pageCount)
})

watch(activityPageCount, (pageCount) => {
  activityPage.value = Math.min(activityPage.value, pageCount)
})

watch(userPageCount, (pageCount) => {
  userPage.value = Math.min(userPage.value, pageCount)
})

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
          v-for="item in visibleNavItems"
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
          <p class="eyebrow">{{ settingsForm.companyName }} Real Estate OS</p>
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
        <article v-if="showSection('properties') && hasPermission('properties.create')" class="panel form-panel">
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

        <article v-if="showSection('properties') && hasPermission('properties.update')" class="panel form-panel wide-operation">
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

        <article v-if="showSection('contracts') && hasPermission('contracts.create')" class="panel form-panel wide-operation">
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
            <button class="submit-button" type="submit"><Plus :size="18" /> {{ editingContractCode ? 'حفظ التعديل' : 'إنشاء العقد' }}</button>
            <button v-if="editingContractCode" class="text-button ghost-button" type="button" @click="resetContractForm">إلغاء التعديل</button>
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
                  <th><button class="sort-button" type="button" @click="sortProperties('code')">رقم العقار {{ propertySortLabel('code') }}</button></th>
                  <th><button class="sort-button" type="button" @click="sortProperties('type')">النوع {{ propertySortLabel('type') }}</button></th>
                  <th><button class="sort-button" type="button" @click="sortProperties('mode')">الغرض {{ propertySortLabel('mode') }}</button></th>
                  <th><button class="sort-button" type="button" @click="sortProperties('area')">المنطقة {{ propertySortLabel('area') }}</button></th>
                  <th><button class="sort-button" type="button" @click="sortProperties('price')">السعر / دينار {{ propertySortLabel('price') }}</button></th>
                  <th><button class="sort-button" type="button" @click="sortProperties('status')">الحالة {{ propertySortLabel('status') }}</button></th>
                  <th>الملفات</th>
                  <th><button class="sort-button" type="button" @click="sortProperties('owner')">المالك {{ propertySortLabel('owner') }}</button></th>
                  <th>الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="property in paginatedProperties" :key="property.code">
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
                      <button v-if="hasPermission('properties.update')" class="mini-button" type="button" title="تعديل" @click="startEditProperty(property)">ت</button>
                      <button v-if="hasPermission('properties.approve')" class="mini-button" type="button" title="اعتماد" @click="approveProperty(property)">✓</button>
                      <button v-if="hasPermission('properties.update')" class="mini-button danger-action" type="button" title="حذف" @click="deleteProperty(property)">×</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="filteredProperties.length === 0">
                  <td colspan="9" class="empty-cell">لا توجد عقارات مطابقة للبحث أو الفلتر الحالي.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="pagination-bar">
            <span>عرض {{ paginatedProperties.length }} من {{ filteredProperties.length }} عقار</span>
            <div class="row-actions">
              <button class="mini-button" type="button" :disabled="propertyPage === 1" @click="previousPropertyPage">
                <ChevronRight :size="17" />
              </button>
              <small>صفحة {{ propertyPage }} / {{ propertyPageCount }}</small>
              <button class="mini-button" type="button" :disabled="propertyPage === propertyPageCount" @click="nextPropertyPage">
                <ChevronLeft :size="17" />
              </button>
            </div>
          </div>
        </article>

        <article v-if="showSection('dashboard', 'clients') && hasPermission('clients.manage')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">CRM</p>
              <h2>متابعة العملاء</h2>
            </div>
            <div class="panel-actions">
              <button class="text-button ghost-button" type="button" @click="sortClients('name')">الاسم {{ clientSortLabel('name') }}</button>
              <button class="text-button ghost-button" type="button" @click="sortClients('stage')">المرحلة {{ clientSortLabel('stage') }}</button>
            </div>
          </div>
          <div class="stack-list">
            <div v-for="client in paginatedClients" :key="client.phone" class="list-row">
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
            <p v-if="filteredClients.length === 0" class="empty-note">لا توجد نتائج عملاء مطابقة للبحث الحالي.</p>
          </div>
          <div class="pagination-bar">
            <span>عرض {{ paginatedClients.length }} من {{ filteredClients.length }} عميل</span>
            <div class="row-actions">
              <button class="mini-button" type="button" :disabled="clientPage === 1" @click="previousClientPage">
                <ChevronRight :size="17" />
              </button>
              <small>صفحة {{ clientPage }} / {{ clientPageCount }}</small>
              <button class="mini-button" type="button" :disabled="clientPage === clientPageCount" @click="nextClientPage">
                <ChevronLeft :size="17" />
              </button>
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

        <article v-if="showSection('dashboard') && hasPermission('reports.view')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">النشاطات</p>
              <h2>آخر عمليات النظام</h2>
            </div>
            <div class="panel-actions">
              <button class="ghost-button" type="button" @click="sortActivityLogs('createdAt')">التاريخ {{ activitySortLabel('createdAt') }}</button>
              <button class="ghost-button" type="button" @click="sortActivityLogs('action')">النوع {{ activitySortLabel('action') }}</button>
            </div>
          </div>
          <div class="stack-list activity-list">
            <div v-for="activity in paginatedActivityLogs" :key="activity.id" class="list-row">
              <div>
                <strong>{{ activity.summary }}</strong>
                <span>{{ activity.userName }} · {{ activity.createdAt }}</span>
              </div>
              <small>{{ activity.action }}</small>
            </div>
            <p v-if="sortedActivityLogs.length === 0" class="empty-note">لا توجد نشاطات مطابقة للبحث الحالي.</p>
          </div>
          <div class="pagination-bar">
            <span>عرض {{ paginatedActivityLogs.length }} من {{ sortedActivityLogs.length }} نشاط</span>
            <div class="row-actions">
              <button class="mini-button" type="button" :disabled="activityPage === 1" @click="previousActivityPage">
                <ChevronRight :size="17" />
              </button>
              <span>{{ activityPage }} / {{ activityPageCount }}</span>
              <button class="mini-button" type="button" :disabled="activityPage === activityPageCount" @click="nextActivityPage">
                <ChevronLeft :size="17" />
              </button>
            </div>
          </div>
        </article>

        <article v-if="showSection('dashboard', 'contracts')" class="panel wide">
          <div class="panel-header">
            <div>
              <p class="eyebrow">العقود</p>
              <h2>البيع، الإيجار، التقسيط</h2>
            </div>
            <div class="panel-actions">
              <button class="ghost-button" type="button" @click="sortContracts('code')">الرقم {{ contractSortLabel('code') }}</button>
              <button class="ghost-button" type="button" @click="sortContracts('due')">المتبقي {{ contractSortLabel('due') }}</button>
            </div>
          </div>
          <div class="contracts">
            <div v-for="contract in paginatedContracts" :key="contract.code" class="contract-card">
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
                <button v-if="hasPermission('contracts.print')" class="mini-button" type="button" title="طباعة العقد" @click="printContract(contract)">
                  <Printer :size="17" />
                </button>
              <button v-if="hasPermission('contracts.create')" class="mini-button" type="button" title="تعديل العقد" @click="startEditContract(contract)">ت</button>
              <button v-if="hasPermission('contracts.create')" class="mini-button danger-action" type="button" title="حذف العقد" @click="deleteContract(contract)">×</button>
            </div>
            <p v-if="sortedContracts.length === 0" class="empty-note">لا توجد عقود مطابقة للبحث الحالي.</p>
          </div>
          <div class="pagination-bar">
            <span>عرض {{ paginatedContracts.length }} من {{ sortedContracts.length }} عقد</span>
            <div class="row-actions">
              <button class="mini-button" type="button" :disabled="contractPage === 1" @click="previousContractPage">
                <ChevronRight :size="17" />
              </button>
              <span>{{ contractPage }} / {{ contractPageCount }}</span>
              <button class="mini-button" type="button" :disabled="contractPage === contractPageCount" @click="nextContractPage">
                <ChevronLeft :size="17" />
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
            <div class="panel-actions">
              <button class="ghost-button" type="button" @click="sortVouchers('code')">الرقم {{ voucherSortLabel('code') }}</button>
              <button class="ghost-button" type="button" @click="sortVouchers('amount')">المبلغ {{ voucherSortLabel('amount') }}</button>
            </div>
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
            <button class="submit-button" type="submit"><Plus :size="18" /> {{ editingVoucherCode ? 'حفظ التعديل' : 'حفظ السند' }}</button>
            <button v-if="editingVoucherCode" class="text-button ghost-button" type="button" @click="resetVoucherForm">إلغاء التعديل</button>
          </form>
          <div class="stack-list vouchers-list">
            <div v-for="voucher in paginatedVouchers" :key="voucher.code" class="list-row">
              <div>
                <strong>{{ voucher.code }}</strong>
                <span>{{ voucher.type }} · {{ voucher.client }} · {{ voucher.reason }}</span>
              </div>
              <div class="row-actions">
                <small>{{ formatMoney(voucher.amount) }}</small>
                <button class="mini-button" type="button" title="طباعة السند" @click="printVoucher(voucher)">
                  <Printer :size="17" />
                </button>
                <button class="mini-button" type="button" title="تعديل السند" @click="startEditVoucher(voucher)">ت</button>
                <button class="mini-button danger-action" type="button" title="حذف السند" @click="deleteVoucher(voucher)">×</button>
              </div>
            </div>
            <p v-if="sortedVouchers.length === 0" class="empty-note">لا توجد سندات مطابقة للبحث الحالي.</p>
          </div>
          <div class="pagination-bar">
            <span>عرض {{ paginatedVouchers.length }} من {{ sortedVouchers.length }} سند</span>
            <div class="row-actions">
              <button class="mini-button" type="button" :disabled="voucherPage === 1" @click="previousVoucherPage">
                <ChevronRight :size="17" />
              </button>
              <span>{{ voucherPage }} / {{ voucherPageCount }}</span>
              <button class="mini-button" type="button" :disabled="voucherPage === voucherPageCount" @click="nextVoucherPage">
                <ChevronLeft :size="17" />
              </button>
            </div>
          </div>
        </article>

        <article v-if="showSection('dashboard', 'finance') && hasPermission('vouchers.manage')" class="panel">
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
            <div class="panel-actions">
              <button class="ghost-button" type="button" @click="sortInstallments('dueDate')">التاريخ {{ installmentSortLabel('dueDate') }}</button>
              <button class="ghost-button" type="button" @click="sortInstallments('amount')">المبلغ {{ installmentSortLabel('amount') }}</button>
            </div>
          </div>
          <div class="stack-list">
            <div v-for="installment in paginatedInstallments" :key="`${installment.contractCode}-${installment.number}`" class="list-row">
              <div>
                <strong>{{ installment.contractCode }}</strong>
                <span>القسط {{ installment.number }} · {{ installment.dueDate }}</span>
              </div>
              <div class="row-actions">
                <small>{{ formatMoney(installment.amount) }} · {{ installment.status }}</small>
                <button
                  v-if="hasPermission('vouchers.manage')"
                  class="mini-button"
                  type="button"
                  title="تسديد القسط"
                  :disabled="installment.status === 'مدفوع'"
                  @click="payInstallment(installment)"
                >
                  ✓
                </button>
              </div>
            </div>
            <p v-if="sortedInstallments.length === 0" class="empty-note">لا توجد أقساط مطابقة للبحث الحالي.</p>
          </div>
          <div class="pagination-bar">
            <span>عرض {{ paginatedInstallments.length }} من {{ sortedInstallments.length }} قسط</span>
            <div class="row-actions">
              <button class="mini-button" type="button" :disabled="installmentPage === 1" @click="previousInstallmentPage">
                <ChevronRight :size="17" />
              </button>
              <span>{{ installmentPage }} / {{ installmentPageCount }}</span>
              <button class="mini-button" type="button" :disabled="installmentPage === installmentPageCount" @click="nextInstallmentPage">
                <ChevronLeft :size="17" />
              </button>
            </div>
          </div>
        </article>

        <article v-if="showSection('dashboard', 'reports') && hasPermission('reports.view')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">التقارير</p>
              <h2>ملخص مالي وتشغيلي</h2>
            </div>
            <ChevronLeft :size="22" />
          </div>
          <form class="smart-form report-filter-form" @submit.prevent="loadReports">
            <label>
              <span>من تاريخ</span>
              <input v-model="reportFilters.from" type="date" />
            </label>
            <label>
              <span>إلى تاريخ</span>
              <input v-model="reportFilters.to" type="date" />
            </label>
            <label>
              <span>الحالة</span>
              <select v-model="reportFilters.status">
                <option v-for="status in reportStatusOptions" :key="status">{{ status }}</option>
              </select>
            </label>
            <button class="submit-button" type="submit"><Search :size="18" /> تحديث التقارير</button>
          </form>
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
            <div>
              <span>عدد المستخدمين</span>
              <strong>{{ employeePerformanceReport?.usersTotal || 0 }}</strong>
            </div>
          </div>
          <div class="report-actions">
            <button class="text-button" type="button" @click="downloadReport('financial', 'propify-financial-report.csv')"><Download :size="18" /> المالي</button>
            <button class="text-button" type="button" @click="downloadReport('properties', 'propify-properties-report.csv')"><Download :size="18" /> العقارات</button>
            <button class="text-button" type="button" @click="downloadReport('installments', 'propify-installments-report.csv')"><Download :size="18" /> الأقساط</button>
            <button class="text-button" type="button" @click="downloadReport('employee-performance', 'propify-employee-performance-report.csv')"><Download :size="18" /> الموظفون</button>
          </div>
          <div class="stack-list report-users">
            <div v-for="user in employeePerformanceReport?.users?.slice(0, 5) || []" :key="user.id" class="list-row">
              <div>
                <strong>{{ user.name }}</strong>
                <span>{{ user.email }} · {{ roleLabel(user.role) }}</span>
              </div>
              <small>{{ user.permissionsCount }} صلاحيات</small>
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
            <div class="panel-actions">
              <button class="ghost-button" type="button" @click="sortUsers('name')">الاسم {{ userSortLabel('name') }}</button>
              <button class="ghost-button" type="button" @click="sortUsers('role')">الدور {{ userSortLabel('role') }}</button>
            </div>
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
            <button class="submit-button" type="submit"><Plus :size="18" /> {{ editingUserId ? 'حفظ التعديل' : 'إضافة مستخدم' }}</button>
            <button v-if="editingUserId" class="text-button ghost-button" type="button" @click="resetUserForm">إلغاء التعديل</button>
          </form>
          <div class="stack-list users-list">
            <div v-for="user in paginatedUsers" :key="user.email" class="list-row">
              <div>
                <strong>{{ user.name }}</strong>
                <span>{{ user.email }} · {{ roleLabel(user.role) }}</span>
              </div>
              <div class="row-actions">
                <small>{{ user.permissions.length }} صلاحيات</small>
                <button class="mini-button" type="button" title="تعديل المستخدم" @click="startEditUser(user)">ت</button>
                <button class="mini-button danger-action" type="button" title="حذف المستخدم" @click="deleteUser(user)">×</button>
              </div>
            </div>
            <p v-if="sortedUsers.length === 0" class="empty-note">لا يوجد مستخدمون مطابقون للبحث الحالي.</p>
          </div>
          <div class="pagination-bar">
            <span>عرض {{ paginatedUsers.length }} من {{ sortedUsers.length }} مستخدم</span>
            <div class="row-actions">
              <button class="mini-button" type="button" :disabled="userPage === 1" @click="previousUserPage">
                <ChevronRight :size="17" />
              </button>
              <span>{{ userPage }} / {{ userPageCount }}</span>
              <button class="mini-button" type="button" :disabled="userPage === userPageCount" @click="nextUserPage">
                <ChevronLeft :size="17" />
              </button>
            </div>
          </div>
        </article>

        <article v-if="showSection('settings')" class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">الإعدادات</p>
              <h2>بيانات المكتب وحالة النظام</h2>
            </div>
            <Settings :size="22" />
          </div>
          <form class="smart-form settings-form" @submit.prevent="saveSettings">
            <label>
              <span>اسم المكتب</span>
              <input v-model="settingsForm.companyName" placeholder="Propify" />
              <small v-if="settingsErrors.companyName" class="field-error"><AlertCircle :size="14" />{{ settingsErrors.companyName[0] }}</small>
            </label>
            <label>
              <span>الهاتف</span>
              <input v-model="settingsForm.companyPhone" placeholder="07700000000" />
              <small v-if="settingsErrors.companyPhone" class="field-error"><AlertCircle :size="14" />{{ settingsErrors.companyPhone[0] }}</small>
            </label>
            <label>
              <span>البريد</span>
              <input v-model="settingsForm.companyEmail" type="email" placeholder="office@propify.local" />
              <small v-if="settingsErrors.companyEmail" class="field-error"><AlertCircle :size="14" />{{ settingsErrors.companyEmail[0] }}</small>
            </label>
            <label>
              <span>العنوان</span>
              <input v-model="settingsForm.companyAddress" placeholder="بغداد - العراق" />
              <small v-if="settingsErrors.companyAddress" class="field-error"><AlertCircle :size="14" />{{ settingsErrors.companyAddress[0] }}</small>
            </label>
            <label>
              <span>العملة الافتراضية</span>
              <input v-model="settingsForm.defaultCurrency" placeholder="دينار" />
              <small v-if="settingsErrors.defaultCurrency" class="field-error"><AlertCircle :size="14" />{{ settingsErrors.defaultCurrency[0] }}</small>
            </label>
            <label>
              <span>نسبة العمولة الافتراضية</span>
              <input v-model="settingsForm.defaultCommissionRate" type="number" min="0" max="100" step="0.1" />
              <small v-if="settingsErrors.defaultCommissionRate" class="field-error"><AlertCircle :size="14" />{{ settingsErrors.defaultCommissionRate[0] }}</small>
            </label>
            <button class="submit-button" type="submit"><Save :size="18" /> حفظ الإعدادات</button>
          </form>
          <div class="stack-list">
            <div class="list-row">
              <div>
                <strong>بيانات الطباعة</strong>
                <span>{{ settingsForm.companyPhone || '-' }} · {{ settingsForm.companyAddress || '-' }}</span>
              </div>
              <small>{{ settingsForm.defaultCurrency }}</small>
            </div>
            <div class="list-row">
              <div>
                <strong>واجهة API</strong>
                <span>{{ API_BASE_URL }}</span>
              </div>
              <small>{{ apiOnline ? 'متصل' : 'غير متصل' }}</small>
            </div>
            <div class="list-row">
              <div>
                <strong>النسخ الاحتياطي</strong>
                <span>تصدير ملف JSON يحتوي بيانات النظام التشغيلية.</span>
              </div>
              <button class="mini-button" type="button" title="تنزيل نسخة احتياطية" @click="downloadBackup">
                <Download :size="17" />
              </button>
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
