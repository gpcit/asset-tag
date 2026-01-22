<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import { login as loginService } from '@/services/auth'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const userStore = useUserStore()

const username = ref('')
const password = ref('')
const loading = ref(false)

const login = async () => {
  loading.value = true

  try {
    const data = await loginService(username.value, password.value)

    // Save token & user in localStorage
    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(data.user))

    // Update Pinia store
    userStore.setUser(data) // <-- send both user and token
    await userStore.initializeData() // <-- fetch assets, companies, categories, dashboard

    await Swal.fire({
      icon: 'success',
      title: 'Login Successful!',
      text: `Welcome back, ${data.user.name || 'User'}!`,
      confirmButtonText: 'Go to Dashboard'
    })

    router.replace('/dashboard')

  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Login Failed',
      text: err.message || 'Incorrect username or password.',
    })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div
    class="mx-auto mt-16 w-96 rounded-2xl
           bg-emerald-500/10 p-8 backdrop-blur-md
           shadow-lg ring-1 ring-emerald-500/20
           dark:bg-black/30 dark:ring-white/10"
  >
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
      <img
        class="mx-auto h-24 w-auto dark:hidden"
        src="@/assets/logo/greenstone-logo.png"
        alt="Your Company"
      />
      <h2 class="mt-5 text-center text-2xl font-bold text-gray-900 dark:text-white">
        Sign in to your account
      </h2>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-sm">
      <form class="space-y-6" @submit.prevent="login">

        <!-- Username -->
        <div>
          <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">
            Username
          </label>
          <input
            v-model="username"
            type="text"
            required
            class="mt-2 block w-full rounded-md bg-white px-3 py-2
                   text-gray-900 outline-gray-300
                   focus:outline-green-600
                   dark:bg-white/5 dark:text-white"
          />
        </div>

        <!-- Password -->
        <div>
          <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">
            Password
          </label>
          <input
            v-model="password"
            type="password"
            required
            class="mt-2 block w-full rounded-md bg-white px-3 py-2
                   text-gray-900 outline-gray-300
                   focus:outline-green-600
                   dark:bg-white/5 dark:text-white"
          />
        </div>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="loading"
          class="flex w-full justify-center rounded-md bg-green-600 px-4 py-2
                 font-semibold text-white hover:bg-green-500
                 disabled:opacity-50"
        >
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
        Don’t have an account?
        <RouterLink
          to="/signup"
          class="font-semibold text-green-600 hover:text-green-500"
        >
          Sign up here
        </RouterLink>
      </p>
    </div>
  </div>
</template>
