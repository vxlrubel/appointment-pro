import { createRouter, createWebHashHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import CalendarView from '../views/CalendarView.vue'
import AppointmentView from '../views/AppointmentView.vue'
import ServiceView from '../views/ServiceView.vue'
import CategoriesView from '../views/CategoriesView.vue'
import StaffView from '../views/StaffView.vue'
import CustomerView from '../views/CustomerView.vue'
import LocationView from '../views/LocationView.vue'
import ExtrasView from '../views/ExtrasView.vue'
import CouponView from '../views/CouponView.vue'
import PaymentView from '../views/PaymentView.vue'
import NotificationView from '../views/NotificationView.vue'
import CustomFieldView from '../views/CustomFieldView.vue'
import SettingsView from '../views/SettingsView.vue'

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/business-hours', component: () => import('../views/BusinessHoursView.vue'), meta: { title: 'Business Hours' } },
    { path: '/affiliates', component: () => import('../views/AffiliateView.vue'), meta: { title: 'Affiliate Programs' } },
    {
      path: '/holidays',
      component: () => import('../views/HolidayView.vue'),
      meta: { title: 'Holiday' },
    },
    {
      path: '/database',
      component: () => import('../views/DatabaseView.vue'),
      meta: { title: 'Database' },
    },
    {
      path: '/document',
      component: () => import('../views/DocumentView.vue'),
      meta: { title: 'Documentation' },
    },
    {
      path: '/',
      name: 'Dashboard',
      component: DashboardView,
      meta: { title: 'Dashboard' },
    },
    {
      path: '/calendar',
      name: 'Calendar',
      component: CalendarView,
      meta: { title: 'Calendar' },
    },
    {
      path: '/appointments',
      name: 'Appointments',
      component: AppointmentView,
      meta: { title: 'Appointments' },
    },
    {
      path: '/services',
      name: 'Services',
      component: ServiceView,
      meta: { title: 'Services' },
    },
    {
      path: '/categories',
      name: 'Categories',
      component: CategoriesView,
      meta: { title: 'Categories' },
    },
    {
      path: '/staff',
      name: 'Staff',
      component: StaffView,
      meta: { title: 'Staff' },
    },
    {
      path: '/customers',
      name: 'Customers',
      component: CustomerView,
      meta: { title: 'Customers' },
    },
    {
      path: '/locations',
      name: 'Locations',
      component: LocationView,
      meta: { title: 'Locations' },
    },
    {
      path: '/extras',
      name: 'Extras',
      component: ExtrasView,
      meta: { title: 'Extras' },
    },
    {
      path: '/coupons',
      name: 'Coupons',
      component: CouponView,
      meta: { title: 'Coupons' },
    },
    {
      path: '/payments',
      name: 'Payments',
      component: PaymentView,
      meta: { title: 'Payments' },
    },
    {
      path: '/notifications',
      name: 'Notifications',
      component: NotificationView,
      meta: { title: 'Notifications' },
    },
    {
      path: '/custom-fields',
      name: 'Custom fields',
      component: CustomFieldView,
      meta: { title: 'Custom fields' },
    },
    {
      path: '/settings',
      name: 'Settings',
      component: SettingsView,
      meta: { title: 'Settings' },
    },
    { path: '/appointment', redirect: '/appointments' },
    { path: '/service', redirect: '/services' },
    { path: '/categpries', redirect: '/categories' },
  ],
})

export default router
