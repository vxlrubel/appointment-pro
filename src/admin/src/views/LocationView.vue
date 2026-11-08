<script setup>
import CrudView from '../components/CrudView.vue'

const fields = [
	{ key: 'name', label: 'Name' },
	{ key: 'address', label: 'Address', type: 'textarea' },
	{ key: 'phone', label: 'Phone' },
	{ key: 'enabled', label: 'Enabled', type: 'bool' },
]
</script>

<template>
	<CrudView path="locations" :fields="fields" />
</template>
