// src/api.ts
import { jwtDecode } from 'jwt-decode'
import axios, { AxiosError } from 'axios'
import type { InternalAxiosRequestConfig } from 'axios'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

const API_URL = 'http://localhost:8000/api' // change this in prod 10.20.20.10

interface JwtPayload { exp: number; [key: string]: any }

// -------------------------
// Idle logout timer
// -------------------------
let idleTimer: number | null = null
const idleTimeoutMinutes = 5 // 1 minute for testing
let listenersAttached = false // <-- prevent multiple listeners

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
  const token = localStorage.getItem('token')
  if (!token) return config

  let newToken = token

  if (isTokenExpired(token)) {
    showLogoutAlert()
    throw new AxiosError('Token expired')
  }

  // Refresh token if expiring soon
  if (isTokenExpiringSoon(token)) {
    try {
      const res = await api.post('/refresh', {}, { headers: { Authorization: `Bearer ${token}` } })
      newToken = res.data.token
      localStorage.setItem('token', newToken)
      resetIdleLogout() // ✅ reset timer, do not add listeners again
    } catch {
      showLogoutAlert()
      throw new AxiosError('Cannot refresh token')
    }
  }

  // Safe headers mutation
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
  response => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) showLogoutAlert()
    return Promise.reject(error)
  }
)

// -------------------------
// Idle logout (sliding session)
// -------------------------
export const initIdleLogout = () => {
  if (listenersAttached) return // ✅ only attach once
  listenersAttached = true

  const resetTimer = () => {
    if (idleTimer) clearTimeout(idleTimer)
    idleTimer = window.setTimeout(() => showLogoutAlert(), idleTimeoutMinutes * 60 * 1000)
  }

  ['mousemove', 'keypress', 'click', 'scroll'].forEach(event =>
    window.addEventListener(event, resetTimer)
  )

  resetTimer()
}

export const resetIdleLogout = () => {
  if (idleTimer) clearTimeout(idleTimer)
  idleTimer = window.setTimeout(() => showLogoutAlert(), idleTimeoutMinutes * 60 * 1000)
}

export const clearIdleLogout = () => {
  if (idleTimer) clearTimeout(idleTimer)
  idleTimer = null
}

export default api