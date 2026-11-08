<script setup>
import CrudView from '../components/CrudView.vue'

const fields = [
	{ key: 'full_name', label: 'Name' },
	{ key: 'email', label: 'Email', type: 'email' },
	{ key: 'phone', label: 'Phone' },
]
</script>

<template>
	<CrudView path="customers" :fields="fields" />
</template>
