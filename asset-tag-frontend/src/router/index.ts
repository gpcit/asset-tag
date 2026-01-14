import { createRouter, createWebHistory } from 'vue-router'
import LoginPage from '@/views/LoginPage.vue'
import SignupPage from '@/views/SignupPage.vue'
import DashBoard from '@/views/DashBoard.vue';
import NavBar from '@/components/NavBar.vue';
const routes = [
  {
    path: '/',           // default path
    name: 'Login',
    component: LoginPage
  },
  {
    path: '/signup',
    name: 'SignUp',
    component: SignupPage
  },
  {
    path: '/dashboard',
    name: 'DashBoard',
    component: DashBoard
  },
  
  {
    path: '/navbar',
    name: 'NavBar',
    component: NavBar
  }
]


const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

export default router
