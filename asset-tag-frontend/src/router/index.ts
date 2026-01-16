import { createRouter, createWebHistory } from 'vue-router'
import LoginPage from '@/views/LoginPage.vue'
import SignupPage from '@/views/SignupPage.vue'
import DashBoard from '@/views/DashBoard.vue'
import NavBar from '@/components/NavBar.vue'
import AssetTag from '@/views/AssetTag.vue'

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
    path: '/asset',
    name: 'Asset',
    meta: { requiresAuth: true },
    component: AssetTag
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
