import axios, { AxiosError } from 'axios'

const API_URL = 'http://localhost:8000/api' // change this in prod to 10.20.20.10

const api = axios.create({
  baseURL: API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

// Attach token automatically to every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token && token !== 'undefined' && token !== 'null') {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle 401 Unauthorized globally
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = '/' // force redirect to login
    }
    return Promise.reject(error)
  }
)

export default api
