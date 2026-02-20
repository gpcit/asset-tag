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
import { Bars3Icon, XMarkIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'
import { RouterLink, useRouter } from 'vue-router'
import { ref, computed, watch } from 'vue'

// -------------------------
// Router
// -------------------------
const router = useRouter()

// -------------------------
// User (from localStorage)
// -------------------------
interface User {
  id?: number
  name?: string
  username?: string
  role?: 'admin' | 'staff'
}

const user = ref<User>(
  JSON.parse(localStorage.getItem('user') || '{}')
)

// Watch localStorage in case user changes
watch(
  () => localStorage.getItem('user'),
  (val) => {
    if (val) user.value = JSON.parse(val)
  }
)

// -------------------------
// Navigation structure
// -------------------------
interface NavItem {
  name: string
  to: string
  roles: ('admin' | 'staff')[]
}

interface NavGroup {
  name: string
  items: NavItem[]
  roles: ('admin' | 'staff')[]
}

const navigationGroups: NavGroup[] = [
  {
    name: 'Dashboard',
    roles: ['admin', 'staff'],
    items: [
      { name: 'Overview', to: '/dashboard', roles: ['admin', 'staff'] },
    ]
  },
  {
    name: 'Asset Management',
    roles: ['admin', 'staff'],
    items: [
      { name: 'Assets', to: '/asset', roles: ['admin', 'staff'] },
      { name: 'Asset Tagging', to: '/tagging', roles: ['admin', 'staff'] },
      { name: 'Print Asset Tags', to: '/asset_tag_print', roles: ['admin', 'staff'] },
      { name: 'Categories', to: '/category_list', roles: ['admin'] },
    ]
  },
  {
    name: 'Organization',
    roles: ['admin'],
    items: [
      { name: 'Companies', to: '/company_list', roles: ['admin'] },
      { name: 'Employees', to: '/employee', roles: ['admin'] },
      { name: 'Department', to: '/department_list', roles: ['admin'] },
    ]
  },
  {
    name: 'System',
    roles: ['admin'],
    items: [
      { name: 'Server Accounts', to: '/server_account_list', roles: ['admin'] },
      { name: 'User Permissions', to: '/user_permission', roles: ['admin'] },
      { name: 'Activity Log', to: '/activity_log', roles: ['admin'] },
    ]
  },
]

// -------------------------
// Filter navigation based on role
// -------------------------
const filteredGroups = computed(() => {
  if (!user.value.role) return []
  
  return navigationGroups
    .filter(group => group.roles.includes(user.value.role!))
    .map(group => ({
      ...group,
      items: group.items.filter(item => item.roles.includes(user.value.role!))
    }))
    .filter(group => group.items.length > 0)
})

// -------------------------
// Logout function
// -------------------------
const logout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  router.replace('/')
}
</script>

<template>
  <Disclosure as="nav" class="bg-emerald-600 shadow-lg" v-slot="{ open }">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">
        <!-- Mobile menu button -->
        <div class="flex items-center sm:hidden">
          <DisclosureButton
            class="relative inline-flex items-center justify-center rounded-md p-2 text-emerald-100 hover:bg-emerald-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
          >
            <span class="sr-only">Open main menu</span>
            <Bars3Icon v-if="!open" class="size-6" />
            <XMarkIcon v-else class="size-6" />
          </DisclosureButton>
        </div>

        <!-- Logo -->
        <div class="flex items-center">
          <img
            class="h-8 w-auto"
            src="@/assets/logo/greenstone-logo.png"
            alt="Greenstone"
          />
        </div>

        <!-- Desktop navigation -->
        <div class="hidden sm:flex sm:items-center sm:space-x-1">
          <template v-for="group in filteredGroups" :key="group.name">
            <!-- Single item groups (like Dashboard) -->
            <RouterLink
              v-if="group.items.length === 1"
              :to="group.items[0].to"
              v-slot="{ isActive }"
            >
              <span
                :class="[
                  isActive
                    ? 'bg-emerald-700 text-white'
                    : 'text-emerald-50 hover:bg-emerald-700 hover:text-white',
                  'rounded-md px-3 py-2 text-sm font-medium cursor-pointer transition-colors'
                ]"
              >
                {{ group.name }}
              </span>
            </RouterLink>

            <!-- Multi-item groups (dropdowns) -->
            <Menu v-else as="div" class="relative">
              <MenuButton
                class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-emerald-50 hover:bg-emerald-700 hover:text-white transition-colors"
              >
                {{ group.name }}
                <ChevronDownIcon class="size-4" />
              </MenuButton>

              <transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="transform opacity-0 scale-95"
                enter-to-class="transform opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform opacity-0 scale-95"
              >
                <MenuItems
                  class="absolute left-0 z-10 mt-2 w-56 origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-none"
                >
                  <div class="py-1">
                    <MenuItem
                      v-for="item in group.items"
                      :key="item.name"
                      v-slot="{ active }"
                    >
                      <RouterLink
                        :to="item.to"
                        :class="[
                          active ? 'bg-emerald-50 text-emerald-700' : 'text-gray-700',
                          'block px-4 py-2 text-sm transition-colors'
                        ]"
                      >
                        {{ item.name }}
                      </RouterLink>
                    </MenuItem>
                  </div>
                </MenuItems>
              </transition>
            </Menu>
          </template>
        </div>

        <!-- Profile dropdown -->
        <div class="flex items-center">
          <Menu as="div" class="relative">
            <MenuButton
              class="relative flex rounded-full bg-emerald-700 text-sm focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-emerald-600"
            >
              <span class="sr-only">Open user menu</span>
              <img
                class="size-8 rounded-full"
                src="../assets/profile.jpg"
                alt="Profile"
              />
            </MenuButton>

            <transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="transform opacity-0 scale-95"
              enter-to-class="transform opacity-100 scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="transform opacity-100 scale-100"
              leave-to-class="transform opacity-0 scale-95"
            >
              <MenuItems
                class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-none"
              >
                <div class="py-1">
                  <div class="px-4 py-2 text-sm text-gray-500 border-b border-gray-100">
                    <div class="font-medium text-gray-900">{{ user.name || user.username }}</div>
                    <div class="text-xs capitalize">{{ user.role }}</div>
                  </div>
                  <MenuItem v-slot="{ active }">
                    <button
                      @click="logout"
                      type="button"
                      :class="[
                        active ? 'bg-gray-50 text-gray-900' : 'text-gray-700',
                        'block w-full px-4 py-2 text-left text-sm transition-colors'
                      ]"
                    >
                      Sign out
                    </button>
                  </MenuItem>
                </div>
              </MenuItems>
            </transition>
          </Menu>
        </div>
      </div>
    </div>

    <!-- Mobile menu -->
    <DisclosurePanel class="sm:hidden border-t border-emerald-500">
      <div class="space-y-1 px-2 pb-3 pt-2">
        <template v-for="group in filteredGroups" :key="group.name">
          <!-- Mobile: Single item groups -->
          <DisclosureButton
            v-if="group.items.length === 1"
            as="div"
          >
            <RouterLink
              :to="group.items[0].to"
              v-slot="{ isActive }"
            >
              <span
                :class="[
                  isActive
                    ? 'bg-emerald-700 text-white'
                    : 'text-emerald-50 hover:bg-emerald-700 hover:text-white',
                  'block rounded-md px-3 py-2 text-base font-medium transition-colors'
                ]"
              >
                {{ group.name }}
              </span>
            </RouterLink>
          </DisclosureButton>

          <!-- Mobile: Multi-item groups -->
          <div v-else class="space-y-1">
            <div class="px-3 py-2 text-xs font-semibold text-emerald-200 uppercase tracking-wider">
              {{ group.name }}
            </div>
            <DisclosureButton
              v-for="item in group.items"
              :key="item.name"
              as="div"
            >
              <RouterLink
                :to="item.to"
                v-slot="{ isActive }"
              >
                <span
                  :class="[
                    isActive
                      ? 'bg-emerald-700 text-white'
                      : 'text-emerald-100 hover:bg-emerald-700 hover:text-white',
                    'block rounded-md px-3 py-2 pl-6 text-sm font-medium transition-colors'
                  ]"
                >
                  {{ item.name }}
                </span>
              </RouterLink>
            </DisclosureButton>
          </div>
        </template>
      </div>
    </DisclosurePanel>
  </Disclosure>
</template>