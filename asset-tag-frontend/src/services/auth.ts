import api from './api'

export const login = async (username: string, password: string) => {
  const response = await api.post('/login', { username, password })
  return response.data // { user, token }
}

export const register = async (
  name: string,
  username: string,
  password: string,
  password_confirmation: string
) => {
  const response = await api.post('/register', {
    name,
    username,
    password,
    password_confirmation,
  })
  return response.data // { user, token }
}
