import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAssetStore = defineStore('asset', {
  state: () => ({
    assets: [] as any[],
    categories: [] as any[],
    companies: [] as any[],
  }),
  actions: {
    async fetchAssets() {
      if (this.assets.length === 0) {
        const { data } = await api.get('/assets')
        this.assets = data
      }
      return this.assets
    },
    async fetchCategories() {
      if (this.categories.length === 0) {
        const { data } = await api.get('/categories')
        this.categories = data
      }
      return this.categories
    },
    async fetchCompanies() {
      if (this.companies.length === 0) {
        const { data } = await api.get('/companies')
        this.companies = data
      }
      return this.companies
    },
    async refreshAssets() {
      const { data } = await api.get('/assets')
      this.assets = data
    },
    clearStore() {
      this.assets = []
      this.categories = []
      this.companies = []
    }
  },
})
