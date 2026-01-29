import { jwtDecode } from 'jwt-decode'
import axios, { AxiosError } from 'axios'

const API_URL = 'http://localhost:8000/api' // change this in prod 10.20.20.10

interface JwtPayload {
  exp: number
  [key: string]: any
}

const logout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  window.location.href = '/'
}

const isTokenExpired = (token: string): boolean => {
  try {
    const decoded = jwtDecode<JwtPayload>(token)  // Named import
    const now = Date.now() / 1000
    return decoded.exp < now
  } catch (e) {
    return true
  }
}

const api = axios.create({
  baseURL: API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    if (isTokenExpired(token)) {
      logout()
      throw new AxiosError('Token expired')
    }
    config.headers = config.headers ?? {}
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      logout()
    }
    return Promise.reject(error)
  }
)

export default api
