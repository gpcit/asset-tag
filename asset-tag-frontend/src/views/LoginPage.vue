<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login as loginService } from '@/services/auth';
import Swal from 'sweetalert2';

const router = useRouter();

const email = ref('');
const password = ref('');
const loading = ref(false);

const login = async () => {
  loading.value = true;

  try {
    const data = await loginService(email.value, password.value);

    // Save token & user info
    localStorage.setItem('token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));

    // Success popup
    await Swal.fire({
      icon: 'success',
      title: 'Login Successful!',
      text: `Welcome back, ${data.user.name || 'User'}!`,
      confirmButtonText: 'Go to Dashboard'
    });

    // Redirect to dashboard
    router.push('/dashboard');
  } catch (err: any) {
    // Error popup
    Swal.fire({
      icon: 'error',
      title: 'Login Failed',
      text: err.message || 'Incorrect email or password.',
    });
  } finally {
    loading.value = false;
  }
};
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

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">
            Email address
          </label>
          <input
            v-model="email"
            type="email"
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
