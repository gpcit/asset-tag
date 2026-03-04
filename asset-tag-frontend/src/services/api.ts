// src/api.ts
import { jwtDecode } from 'jwt-decode'
import axios, { AxiosError } from 'axios'
import type { InternalAxiosRequestConfig } from 'axios'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

const API_URL = 'http://localhost:8000/api'

interface JwtPayload { exp: number; [key: string]: any }

// -------------------------
// Idle logout timer
// -------------------------
let idleTimer: number | null = null
const idleTimeoutDays = 1
const idleTimeoutMs = idleTimeoutDays * 24 * 60 * 60 * 1000
let listenersAttached = false

// -------------------------
// Logout function
// -------------------------
export const logout = () => {
  clearIdleLogout()
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
    text: 'Your session has expired due to being idle. You will be logged out.',
    icon: 'warning',
    confirmButtonText: 'OK',
    allowOutsideClick: false,
    allowEscapeKey: false,
  }).then(() => logout())
}

// -------------------------
// Token utilities
// -------------------------
const isTokenExpired = (token: string) => {
  try {
    const decoded = jwtDecode<JwtPayload>(token)
    return decoded.exp < Date.now() / 1000
  } catch {
    return true
  }
}

const isTokenExpiringSoon = (token: string, thresholdSeconds = 300) => {
  try {
    const decoded = jwtDecode<JwtPayload>(token)
    return decoded.exp - Date.now() / 1000 < thresholdSeconds
  } catch {
    return true
  }
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
api.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  // 👇 TEMPORARY DEBUG
  try {
    JSON.stringify(config)
  } catch (e) {
    console.error('🔴 CIRCULAR IN CONFIG:', new Error().stack)
    console.log('config keys:', Object.keys(config))
  }

  const token = localStorage.getItem('token')
  if (!token) return config

  let newToken = token

  if (isTokenExpired(token)) {
    showLogoutAlert()
    throw new AxiosError('Token expired')
  }

  if (isTokenExpiringSoon(token)) {
    try {
      const res = await axios.post(`${API_URL}/refresh`, {}, {
        headers: { Authorization: `Bearer ${token}` }
      })
      newToken = res.data.token
      localStorage.setItem('token', newToken)
      resetIdleLogout()
    } catch {
      showLogoutAlert()
      throw new AxiosError('Cannot refresh token')
    }
  }

  if (config.headers) {
    (config.headers as any).Authorization = `Bearer ${newToken}`
  } else {
    config.headers = new axios.AxiosHeaders({ Authorization: `Bearer ${newToken}` })
  }

  return config
})

// -------------------------
// Response interceptor
// -------------------------
api.interceptors.response.use(
  response => {
    // 👇 TEMPORARY DEBUG
    try {
      JSON.stringify(response)
    } catch (e) {
      console.error('🔴 CIRCULAR IN RESPONSE:', new Error().stack)
      console.log('response keys:', Object.keys(response))
    }
    return response
  },
  (error: AxiosError) => {
    if (error.response?.status === 401) showLogoutAlert()
    return Promise.reject(error)
  }
)

// -------------------------
// Idle logout (sliding session)
// -------------------------
export const initIdleLogout = () => {
  if (listenersAttached) return
  listenersAttached = true
  const resetTimer = () => {
    if (idleTimer) clearTimeout(idleTimer)
    idleTimer = window.setTimeout(() => showLogoutAlert(), idleTimeoutMs)
  }
  ['mousemove', 'keypress', 'click', 'scroll'].forEach(event =>
    window.addEventListener(event, resetTimer)
  )
  resetTimer()
}

export const resetIdleLogout = () => {
  if (idleTimer) clearTimeout(idleTimer)
  idleTimer = window.setTimeout(() => showLogoutAlert(), idleTimeoutMs)
}

export const clearIdleLogout = () => {
  if (idleTimer) clearTimeout(idleTimer)
  idleTimer = null
}

export default api