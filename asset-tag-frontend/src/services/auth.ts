import axios from 'axios';

const API_URL = 'http://localhost:8000/api';

/** Login user */
export const login = async (email: string, password: string) => {
  try {
    const response = await axios.post(`${API_URL}/login`, {
      email,
      password,
    });

    return response.data; // { user, token }
  } catch (error: any) {
    const message = error.response?.data?.message || 'Login failed. Try again.';
    throw new Error(message);
  }
};

/** Register new user */
export const register = async (
  name: string,
  email: string,
  password: string,
  password_confirmation: string
) => {
  try {
    const response = await axios.post(`${API_URL}/register`, {
      name,
      email,
      password,
      password_confirmation,
    });

    return response.data; // { user, token }
  } catch (error: any) {
    const message = error.response?.data?.message || 'Registration failed. Try again.';
    throw new Error(message);
  }
};
