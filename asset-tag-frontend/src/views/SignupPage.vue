<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { register } from '@/services/auth';
import Swal from 'sweetalert2';

const router = useRouter();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);

const signup = async () => {
  loading.value = true;

  try {
    const data = await register(name.value, email.value, password.value, passwordConfirmation.value);

    // Save token & user
    localStorage.setItem('token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));

    // Success alert
    await Swal.fire({
      icon: 'success',
      title: 'Registered!',
      text: 'Your account has been created successfully!',
      confirmButtonText: 'Go to Dashboard'
    });

    // Redirect to dashboard
    router.push('/dashboard');
  } catch (err: any) {
    // Error alert
    Swal.fire({
      icon: 'error',
      title: 'Oops...',
      text: err.message || 'Something went wrong!',
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
        Create your account
      </h2>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-sm">
      <form class="space-y-6" @submit.prevent="signup">

        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">Name</label>
          <input
            v-model="name"
            type="text"
            required
            class="mt-2 block w-full rounded-md bg-white px-3 py-2 text-gray-900 outline-gray-300 focus:outline-green-600 dark:bg-white/5 dark:text-white"
          />
        </div>

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">Email</label>
          <input
            v-model="email"
            type="email"
            required
            class="mt-2 block w-full rounded-md bg-white px-3 py-2 text-gray-900 outline-gray-300 focus:outline-green-600 dark:bg-white/5 dark:text-white"
          />
        </div>

        <!-- Password -->
        <div>
          <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">Password</label>
          <input
            v-model="password"
            type="password"
            required
            class="mt-2 block w-full rounded-md bg-white px-3 py-2 text-gray-900 outline-gray-300 focus:outline-green-600 dark:bg-white/5 dark:text-white"
          />
        </div>

        <!-- Password Confirmation -->
        <div>
          <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">Confirm Password</label>
          <input
            v-model="passwordConfirmation"
            type="password"
            required
            class="mt-2 block w-full rounded-md bg-white px-3 py-2 text-gray-900 outline-gray-300 focus:outline-green-600 dark:bg-white/5 dark:text-white"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="flex w-full justify-center rounded-md bg-green-600 px-4 py-2 font-semibold text-white hover:bg-green-500 disabled:opacity-50"
        >
          {{ loading ? 'Signing up…' : 'Sign up' }}
        </button>
      </form>

      <p class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
        Already have an account?
        <RouterLink
          to="/"
          class="font-semibold text-green-600 hover:text-green-500"
        >
          Sign in here
        </RouterLink>
      </p>
    </div>
  </div>
</template>
