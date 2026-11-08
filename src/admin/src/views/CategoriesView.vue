<script setup>
import CrudView from '../components/CrudView.vue'

const fields = [
	{ key: 'id', label: 'ID', hideInForm: true },
	{ key: 'name', label: 'Name' },
	{ key: 'position', label: 'Position', type: 'number', defaultValue: 0 },
]
</script>

<template>
	<CrudView path="categories" :fields="fields" />
</template>
