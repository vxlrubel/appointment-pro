import { createRouter, createWebHashHistory, createWebHistory } from 'vue-router'
import HomeView from '../views/DashboardView.vue'
import AppointmentView from '../views/AppointmentView.vue'
import ServiceView from '../views/ServiceView.vue'
import CategoryView from '../views/CategoryView.vue'

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'Dashboard',
      component: HomeView,
      meta: { title: 'Dashboard' },
    },
    {
      path: '/appointment',
      name: 'Appointment',
      component: AppointmentView,
      meta: { title: 'Appointments' },
    },
    {
      path: '/service',
      name: 'Services',
      component: ServiceView,
      meta: { title: 'Services' },
    },
    {
      path: '/categpries',
      name: 'Categories',
      component: CategoryView,
      meta: { title: 'Categories' },
    },
  ],
})

export default router
