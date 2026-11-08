<script setup lang="ts">
import { computed, watchEffect } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'

const route = useRoute()

const pageTitle = computed(() => (route.meta.title as string | undefined) ?? 'Dashboard')

watchEffect(() => {
	document.title = `${pageTitle.value} - Appointment Pro`
})
</script>

<template>
	<div>
		<header class="px-header">
			<h1>Appointment Pro</h1>
			<div>Here are all the features you need to manage your appointments.</div>
		</header>

		<div class="px-main">
			<aside class="px-sidebar">
				<RouterLink to="/">Dashboard</RouterLink>
				<RouterLink to="/calendar">Calendar</RouterLink>
				<RouterLink to="/appointments">Appointments</RouterLink>
				<RouterLink to="/services">Services</RouterLink>
				<RouterLink to="/categories">Categories</RouterLink>
				<RouterLink to="/staff">Staff</RouterLink>
				<RouterLink to="/customers">Customers</RouterLink>
				<RouterLink to="/locations">Locations</RouterLink>
				<RouterLink to="/extras">Extras</RouterLink>
				<RouterLink to="/coupons">Coupons</RouterLink>
				<RouterLink to="/payments">Payments</RouterLink>
				<RouterLink to="/notifications">Notifications</RouterLink>
				<RouterLink to="/custom-fields">Custom fields</RouterLink>
				<RouterLink to="/settings">Settings</RouterLink>
			</aside>
			<div class="px-main-content">

				<h2 class="px-page-title">{{ pageTitle }}</h2>

				<RouterView />
			</div>
		</div>

	</div>

</template>
