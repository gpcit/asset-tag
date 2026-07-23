<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import NavBar from '@/components/NavBar.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

interface Company {
  id: number
  name: string
}

interface User {
  id: number
  name: string
  username: string
  role: 'admin' | 'staff'
  company_ids: number[]
}

const users = ref<User[]>([])
const companies = ref<Company[]>([])
const openDropdown = ref<number | null>(null)
const dropdownPos = ref({ top: 0, left: 0 })

const fetchUsers = async () => {
  const res = await api.get('/users')
  users.value = res.data
}

const fetchCompanies = async () => {
  const res = await api.get('/companies')
  companies.value = res.data
}

const toggleRole = async (user: User) => {
  const newRole = user.role === 'admin' ? 'staff' : 'admin'

  try {
    await api.patch(`/users/${user.id}/role`, {
      role: newRole
    })

    user.role = newRole

    Swal.fire({
      icon: 'success',
      title: 'Role updated',
      text: `${user.name} is now ${newRole}`,
      timer: 1200,
      showConfirmButton: false
    })
  } catch (err) {
    Swal.fire('Error', 'Failed to update role', 'error')
  }
}

const toggleDropdown = (userId: number, event: MouseEvent) => {
  if (openDropdown.value === userId) {
    openDropdown.value = null
    return
  }

  const button = event.currentTarget as HTMLElement
  const rect = button.getBoundingClientRect()

  dropdownPos.value = {
    top: rect.bottom + window.scrollY + 4,
    left: rect.left + window.scrollX + rect.width / 2,
  }

  openDropdown.value = userId
}

const companyLabel = (u: User) => {
  if (!u.company_ids?.length) return 'No companies'
  if (u.company_ids.length === 1) {
    const c = companies.value.find(c => c.id === u.company_ids[0])
    return c?.name ?? '1 selected'
  }
  return `${u.company_ids.length} companies`
}

const toggleCompany = (u: User, companyId: number) => {
  const idx = u.company_ids.indexOf(companyId)
  if (idx === -1) {
    u.company_ids.push(companyId)
  } else {
    u.company_ids.splice(idx, 1)
  }
}

const saveCompanies = async (u: User) => {
  try {
    await api.post(`/users/${u.id}/companies`, {
      company_ids: u.company_ids
    })

    openDropdown.value = null

    Swal.fire({
      icon: 'success',
      title: 'Companies Updated',
      text: `${u.name}'s access has been updated`,
      timer: 1200,
      showConfirmButton: false
    })
  } catch (err) {
    Swal.fire('Error', 'Failed to update companies', 'error')
  }
}

const handleClickOutside = (e: MouseEvent) => {
  const target = e.target as HTMLElement
  if (!target.closest('.company-dropdown') && !target.closest('.company-dropdown-panel')) {
    openDropdown.value = null
  }
}

onMounted(() => {
  fetchUsers()
  fetchCompanies()
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <NavBar />

  <div class="max-w-5xl mx-auto mt-8 bg-white shadow-lg rounded-xl p-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">
      👥 User Role Management
    </h2>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-gray-100 text-left text-sm uppercase text-gray-600">
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Username</th>
            <th class="px-4 py-3 text-center">Role</th>
            <th class="px-4 py-3 text-center">Action</th>
            <th class="px-4 py-3 text-center">Companies</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="u in users"
            :key="u.id"
            class="border-b hover:bg-gray-50 transition"
          >
            <td class="px-4 py-3 font-medium text-gray-800">
              {{ u.name }}
            </td>

            <td class="px-4 py-3 text-gray-600">
              {{ u.username }}
            </td>

            <!-- Role badge -->
            <td class="px-4 py-3 text-center">
              <span
                class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="u.role === 'admin'
                  ? 'bg-emerald-100 text-emerald-700'
                  : 'bg-gray-200 text-gray-700'"
              >
                {{ u.role.toUpperCase() }}
              </span>
            </td>

            <!-- Toggle switch -->
            <td class="px-4 py-3 text-center">
              <label class="inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  class="sr-only"
                  :checked="u.role === 'admin'"
                  @change="toggleRole(u)"
                />
                <div
                  class="w-11 h-6 rounded-full transition"
                  :class="u.role === 'admin'
                    ? 'bg-emerald-500'
                    : 'bg-gray-400'"
                >
                  <div
                    class="w-5 h-5 bg-white rounded-full shadow transform transition"
                    :class="u.role === 'admin'
                      ? 'translate-x-5'
                      : 'translate-x-1'"
                  ></div>
                </div>
              </label>
            </td>

            <!-- Company Multi-select -->
            <td class="px-4 py-3 text-center relative company-dropdown">
              <span
                v-if="u.role === 'admin'"
                class="text-xs text-gray-500 italic"
              >
                All Companies
              </span>

              <button
                v-else
                type="button"
                @click="toggleDropdown(u.id, $event)"
                class="border rounded-lg px-3 py-1 text-sm text-gray-700 bg-white hover:bg-gray-50 w-40 mx-auto flex justify-between items-center gap-2"
              >
                <span class="truncate">{{ companyLabel(u) }}</span>
                <span class="text-gray-400">▾</span>
              </button>
            </td>

            <!-- Teleported dropdown panel, rendered outside the table -->
            <Teleport to="body">
              <div
                v-if="openDropdown === u.id"
                class="company-dropdown-panel fixed z-50 w-72 bg-white border rounded-lg shadow-lg p-2 text-left max-h-60 overflow-y-auto"
                :style="{ top: dropdownPos.top + 'px', left: dropdownPos.left + 'px', transform: 'translateX(-50%)' }"
              >
                <label
                  v-for="c in companies"
                  :key="c.id"
                  class="flex items-start gap-2 px-2 py-1 rounded hover:bg-gray-50 cursor-pointer text-sm"
                >
                  <input
                    type="checkbox"
                    :value="c.id"
                    :checked="u.company_ids.includes(c.id)"
                    @change="toggleCompany(u, c.id)"
                    class="mt-1"
                  />
                  <span>{{ c.name }}</span>
                </label>

                <div class="flex justify-end mt-2 pt-2 border-t sticky bottom-0 bg-white">
                  <button
                    @click="saveCompanies(u)"
                    class="text-xs bg-emerald-500 text-white px-3 py-1 rounded hover:bg-emerald-600"
                  >
                    Save
                  </button>
                </div>
              </div>
            </Teleport>
          </tr>

          <tr v-if="users.length === 0">
            <td colspan="5" class="text-center py-6 text-gray-500">
              No users found
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>