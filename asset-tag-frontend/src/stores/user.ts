import { defineStore } from 'pinia'
import api from '@/services/api'
import { ref } from 'vue'

interface Category { id: number; name: string }
interface Company { id: number; name: string }
interface Department { id: number; name: string }   // ✅ added
interface Employee { id: number; name: string; department_id?: number }  // ✅ updated

interface Asset {
  id: number
  person_in_charge_id?: number
  person_in_charge?: string
  department?: string
  department_id?: number   // ✅ added
  invoice_number?: string
  invoice_date?: string
  cost?: number
  supplier?: string
  model_number?: string
  specifications?: string
  remarks?: string
  date_deployed?: string
  category_id?: number
  company_id?: number
  company?: Company
  category?: Category
  employee?: Employee | null
}

export const useUserStore = defineStore('user', () => {
  const user = ref<any>(null)
  const token = ref<string | null>(localStorage.getItem('token'))

  const assets = ref<Asset[]>([])
  const companies = ref<Company[]>([])
  const categories = ref<Category[]>([])
  const departments = ref<Department[]>([])   // ✅ added
  const employees = ref<Employee[]>([])
  const totalAssets = ref(0)
  const totalCost = ref(0)
  const byCompany = ref<any[]>([])

  const setUser = (data: any) => {
    user.value = data.user
    token.value = data.token
    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(data.user))
  }

  // Fetch employees
  const fetchEmployees = async () => {
    try {
      const { data } = await api.get('/employees')
      const rawEmployees = data.data || data
      employees.value = (Array.isArray(rawEmployees) ? rawEmployees : [])
        .filter((emp: any) => emp && emp.id && emp.name)
    } catch (err) {
      console.error('Failed to fetch employees:', err)
      employees.value = []
    }
  }

  // Map assets to employee objects
  const mapAssetsToEmployees = (assetList: Asset[]) => {
    return assetList.map(asset => {
      const emp = employees.value.find(e => e.id === asset.person_in_charge_id) || null
      return { ...asset, employee: emp }
    })
  }

  // Fetch assets and map to employees
  const fetchAssets = async () => {
    try {
      const { data } = await api.get('/assets')
      const rawAssets = Array.isArray(data) ? data : []
      assets.value = mapAssetsToEmployees(rawAssets)
    } catch (err) {
      console.error('Failed to fetch assets:', err)
      assets.value = []
    }
  }

  const fetchCompanies = async () => {
    try {
      const { data } = await api.get('/companies')
      companies.value = Array.isArray(data) ? data : []
    } catch (err) {
      console.error('Failed to fetch companies:', err)
      companies.value = []
    }
  }

  const fetchCategories = async () => {
    try {
      const { data } = await api.get('/categories')
      categories.value = Array.isArray(data) ? data : []
    } catch (err) {
      console.error('Failed to fetch categories:', err)
      categories.value = []
    }
  }

  // ✅ added
  const fetchDepartments = async () => {
    try {
      const { data } = await api.get('/departments')
      departments.value = Array.isArray(data) ? data : []
    } catch (err) {
      console.error('Failed to fetch departments:', err)
      departments.value = []
    }
  }

  const fetchDashboard = async () => {
    try {
      const { data } = await api.get('/dashboard/summary')
      totalAssets.value = data.totalAssets || 0
      totalCost.value = data.totalCost || 0
      byCompany.value = Array.isArray(data.byCompany) ? data.byCompany : []
    } catch (err) {
      console.error('Failed to fetch dashboard:', err)
      totalAssets.value = 0
      totalCost.value = 0
      byCompany.value = []
    }
  }

  // Initialize all data
  const initializeData = async () => {
    await fetchEmployees() // fetch employees first so assets can be mapped to them
    await Promise.all([
      fetchAssets(),
      fetchCompanies(),
      fetchCategories(),
      fetchDepartments(),  // ✅ added
      fetchDashboard(),
    ])
  }

  return {
    user,
    token,
    assets,
    companies,
    categories,
    departments,    // ✅ exposed
    employees,
    totalAssets,
    totalCost,
    byCompany,
    setUser,
    fetchAssets,
    fetchCompanies,
    fetchCategories,
    fetchDepartments,  // ✅ exposed
    fetchEmployees,
    fetchDashboard,
    initializeData,
  }
})