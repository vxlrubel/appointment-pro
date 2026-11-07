<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
	<div>
		<header class="px-header">
			<h1>Appointment Pro</h1>
			<div>Here are all the features you need to manage your appointments.</div>
		</header>

		<div class="px-main">
			<aside class="px-sidebar">
				<RouterLink to="/">Home</RouterLink>
				<RouterLink to="/about">About</RouterLink>
			</aside>
			<div class="px-main-content">
				<RouterView />
			</div>
		</div>

	</div>

</template>
