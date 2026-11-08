import { createRouter, createWebHashHistory, createWebHistory } from 'vue-router'
import HomeView from '../views/DashboardView.vue'
import AppointmentView from '../views/AppointmentView.vue'

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'Dashboard',
      component: HomeView,
    },
    {
      path: '/appointment',
      name: 'Appointment',
      component: AppointmentView,
    },
  ],
})

export default router
