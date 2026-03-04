// src/api.ts
import axios, { AxiosError } from 'axios'
import type { InternalAxiosRequestConfig } from 'axios'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

const API_URL = 'http://localhost:8000/api'

// -------------------------
// Logout function
// -------------------------
export const logout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  window.location.href = '/'
}

// -------------------------
// Show logout alert
// -------------------------
const showLogoutAlert = () => {
  Swal.fire({
    title: 'Session Expired',
    text: 'Your session has expired. You will be logged out.',
    icon: 'warning',
    confirmButtonText: 'OK',
    allowOutsideClick: false,
    allowEscapeKey: false,
  }).then(() => logout())
}

// -------------------------
// Axios instance
// -------------------------
const api = axios.create({
  baseURL: API_URL,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
})

// -------------------------
// Request interceptor
// -------------------------
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem('token')

  if (token) {
    if (config.headers) {
      (config.headers as any).Authorization = `Bearer ${token}`
    } else {
      config.headers = new axios.AxiosHeaders({ Authorization: `Bearer ${token}` })
    }
  }

  return config
})

// -------------------------
// Response interceptor
// -------------------------
api.interceptors.response.use(
  response => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) showLogoutAlert()
    return Promise.reject(error)
  }
)

export default api