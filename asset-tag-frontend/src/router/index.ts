import { createRouter, createWebHistory } from 'vue-router'
import LoginPage from '@/views/LoginPage.vue'
import SignupPage from '@/views/SignupPage.vue'
import DashBoard from '@/views/DashBoard.vue'
import NavBar from '@/components/NavBar.vue'
import AssetTag from '@/views/AssetTag.vue'
import Tag from '@/views/Tag.vue'
import CategoryList from '@/views/CategoryList.vue'
import AssetList from '@/views/AssetList.vue'
import ServerAccount from '@/views/ServerAccount.vue'
import UserPermission from '@/views/UserPermission.vue'
import AssetTagPrint from '@/views/AssetTagPrint.vue'
import EmployeePage from '@/views/EmployeePage.vue'

const routes = [
  {
    path: '/',
    name: 'Login',
    component: LoginPage,
    meta: { guestOnly: true }
  },
  {
    path: '/signup',
    name: 'SignUp',
    component: SignupPage,
    meta: { guestOnly: true }
  },
  {
    path: '/dashboard',
    name: 'DashBoard',
    component: DashBoard,
    meta: { requiresAuth: true }
  },
  {
    path: '/navbar',
    name: 'NavBar',
    meta: { requiresAuth: true },
    component: NavBar
  },

  {
    path: '/tagging',
    name: 'Tag',
    meta: { requiresAuth: true },
    component: Tag
  },

  {
    path: '/asset',
    name: 'Asset',
    meta: { requiresAuth: true },
    component: AssetTag
  },
  {
    path: '/category',
    name: 'Category',
    meta: { requiresAuth: true },
    component: CategoryList
  },
  {
    path: '/asset_list',
    name: 'AssetList',
    meta: { requiresAuth: true },
    component: AssetList
  },
  {
    path: '/server_account_list',
    name: 'ServerAccount',
    meta: { requiresAuth: true },
    component: ServerAccount
  },
  {
    path: '/user_permission',
    name: 'UserPermission',
    meta: { requiresAuth: true },
    component: UserPermission
  },
  {
    path: '/asset_tag_print',
    name: 'AssetTagPrint',
    meta: { requiresAuth: true },
    component: AssetTagPrint
  },
  {
    path: '/employee',
    name: 'Employee',
    meta: { requiresAuth: true },
    component: EmployeePage
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

const isAuthenticated = () => {
  const token = localStorage.getItem('token')
  return !!token && token !== 'undefined' && token !== 'null'
}

router.beforeEach((to, from, next) => {
  const auth = isAuthenticated()

  if (to.meta.guestOnly && auth) {
    next('/dashboard')
    return
  }

  if (to.meta.requiresAuth && !auth) {
    next('/')
    return
  }

  next()
})

export default router
