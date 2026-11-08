<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const rows = ref([])
const error = ref('')

onMounted(async () => {
	try {
		rows.value = await api('payments')
	} catch (e) {
		error.value = e.message
	}
})
</script>

<template>
	<p v-if="error" class="px-error">{{ error }}</p>

	<div class="px-card">
		<table class="px-table">
			<thead>
				<tr>
					<th>ID</th>
					<th>Type</th>
					<th>Status</th>
					<th>Amount</th>
					<th>When</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in rows" :key="row.id">
					<td>{{ row.id }}</td>
					<td>{{ row.type }}</td>
					<td>{{ row.status }}</td>
					<td>{{ row.amount }} {{ row.currency }}</td>
					<td>{{ row.created_at }}</td>
				</tr>
				<tr v-if="!rows.length">
					<td colspan="5" class="px-muted">No payments yet.</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>
