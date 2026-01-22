<script setup lang="ts">
import {
  Disclosure,
  DisclosureButton,
  DisclosurePanel,
  Menu,
  MenuButton,
  MenuItem,
  MenuItems,
} from '@headlessui/vue'
import { Bars3Icon, BellIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { RouterLink } from 'vue-router'
import { useRouter } from 'vue-router'

const router = useRouter()

const navigation = [
  { name: 'Dashboard', to: '/dashboard' },
  { name: 'Asset', to: '/asset' },
  { name: 'Asset Tag', to: '/tagging' },
  { name: 'Category List', to: '/category' },
  {name: 'Server Accounts', to: '/server_account_list'}
]

const logout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  router.replace('/')
}

</script>

<template>
  <Disclosure as="nav" class="relative bg-emerald-600" v-slot="{ open }">
    <div class="mx-auto max-w-7xl px-2 sm:px-6 lg:px-8">
      <div class="relative flex h-16 items-center justify-between">
        <!-- Mobile menu button -->
        <div class="absolute inset-y-0 left-0 flex items-center sm:hidden">
          <DisclosureButton
            class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-200 hover:bg-white/10 hover:text-white focus:outline-none"
          >
            <span class="sr-only">Open main menu</span>
            <Bars3Icon v-if="!open" class="size-6" />
            <XMarkIcon v-else class="size-6" />
          </DisclosureButton>
        </div>

        <!-- Logo + Desktop nav -->
        <div class="flex flex-1 items-center justify-center sm:items-stretch sm:justify-start">
          <div class="flex shrink-0 items-center">
            <img
              class="h-8 w-auto"
              src="@/assets/logo/greenstone-logo.png"
              alt="Your Company"
            />
          </div>

          <div class="hidden sm:ml-6 sm:block">
            <div class="flex space-x-4">
              <RouterLink
                v-for="item in navigation"
                :key="item.name"
                :to="item.to"
                v-slot="{ isActive }"
              >
                <span
                  :class="[
                    isActive
                      ? 'bg-emerald-900 text-white'
                      : 'text-emerald-100 hover:bg-white/10 hover:text-white',
                    'rounded-md px-3 py-2 text-sm font-medium cursor-pointer'
                  ]"
                >
                  {{ item.name }}
                </span>
              </RouterLink>
            </div>
          </div>
        </div>

        <!-- Right side -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-2 sm:static sm:inset-auto sm:ml-6 sm:pr-0">

          <!-- Profile dropdown -->
          <Menu as="div" class="relative ml-3">
            <MenuButton
              class="relative flex rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            >
              <span class="sr-only">Open user menu</span>
              <img
                class="size-8 rounded-full bg-gray-800"
                src="../assets/profile.jpg"
                alt=""
              />
            </MenuButton>

            <transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="transform opacity-0 scale-95"
              enter-to-class="transform scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="transform scale-100"
              leave-to-class="transform opacity-0 scale-95"
            >
              <MenuItems
                class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg"
              >
                <MenuItem v-slot="{ active }">
                  <button
                    @click="logout"
                    type="button"
                    :class="[
                      active ? 'bg-gray-100' : '',
                      'block w-full px-4 py-2 text-left text-sm text-gray-700'
                    ]"
                  >
                    Sign out
                  </button>
                </MenuItem>
              </MenuItems>
            </transition>
          </Menu>
        </div>
      </div>
    </div>

    <!-- Mobile menu -->
    <DisclosurePanel class="sm:hidden">
      <div class="space-y-1 px-2 pt-2 pb-3">
        <DisclosureButton
          v-for="item in navigation"
          :key="item.name"
          as="RouterLink"
          :to="item.to"
          v-slot="{ isActive }"
        >
          <span
            :class="[
              isActive
                ? 'bg-emerald-900 text-white'
                : 'text-emerald-100 hover:bg-white/10 hover:text-white',
              'block rounded-md px-3 py-2 text-base font-medium'
            ]"
          >
            {{ item.name }}
          </span>
        </DisclosureButton>
      </div>
    </DisclosurePanel>
  </Disclosure>
</template>
