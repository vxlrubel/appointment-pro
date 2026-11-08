import { createRouter, createWebHashHistory, createWebHistory } from 'vue-router'
import HomeView from '../views/DashboardView.vue'
import AppointmentView from '../views/AppointmentView.vue'
import ServiceView from '../views/ServiceView.vue'

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
    {
      path: '/service',
      name: 'Services',
      component: ServiceView,
    },
  ],
})

export default router
