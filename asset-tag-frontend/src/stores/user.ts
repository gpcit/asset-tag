import { defineStore } from 'pinia'
import api from '@/services/api'
import { ref } from 'vue'

interface Category { id: number; name: string }
interface Company { id: number; name: string }

interface Asset {
  id: number
  person_in_charge: string
  department: string
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
}

export const useUserStore = defineStore('user', () => {
  const user = ref<any>(null)
  const token = ref<string | null>(localStorage.getItem('token'))
  
  const assets = ref<Asset[]>([])
  const companies = ref<Company[]>([])
  const categories = ref<Category[]>([])

  const totalAssets = ref(0)
  const totalCost = ref(0)
  const byCompany = ref<any[]>([])

  const setUser = (data: any) => {
    user.value = data.user
    token.value = data.token
    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(data.user))
  }

  const fetchAssets = async () => {
    const { data } = await api.get('/assets')
    assets.value = data
  }

  const fetchCompanies = async () => {
    const { data } = await api.get('/companies')
    companies.value = data
  }

  const fetchCategories = async () => {
    const { data } = await api.get('/categories')
    categories.value = data
  }

  const fetchDashboard = async () => {
    const { data } = await api.get('/dashboard/summary')
    totalAssets.value = data.totalAssets
    totalCost.value = data.totalCost
    byCompany.value = data.byCompany
  }

  const initializeData = async () => {
    await Promise.all([fetchAssets(), fetchCompanies(), fetchCategories(), fetchDashboard()])
  }

  return {
    user,
    token,
    assets,
    companies,
    categories,
    totalAssets,
    totalCost,
    byCompany,
    setUser,
    fetchAssets,
    fetchCompanies,
    fetchCategories,
    fetchDashboard,
    initializeData,
  }
})
