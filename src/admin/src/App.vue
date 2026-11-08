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
				<RouterLink to="/appointment">Appointment</RouterLink>
				<RouterLink to="/service">Services</RouterLink>
				<RouterLink to="/categpries">Categories</RouterLink>
			</aside>
			<div class="px-main-content">

				<h2 class="px-page-title">{{ pageTitle }}</h2>

				<RouterView />
			</div>
		</div>

	</div>

</template>
