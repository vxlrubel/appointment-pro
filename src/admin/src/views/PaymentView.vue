<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'

const loading = ref(true)

const rows = ref([])
const error = ref('')

onMounted(async () => {
  try {
    rows.value = await api('payments')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <p v-if="error" class="px-error">{{ error }}</p>

  <LoadingSkeleton v-if="loading" variant="table" :columns="5" label="Loading payments" />
  <div v-else class="px-card">
    <table class="px-table">
      <thead>
        <tr class="px-table-row" style="--px-table-row-index: 0">
          <th>ID</th>
          <th>Type</th>
          <th>Status</th>
          <th>Amount</th>
          <th>When</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, index) in rows" :key="row.id" class="px-table-row" :style="{ '--px-table-row-index': index + 1 }">
          <td>{{ row.id }}</td>
          <td>{{ row.type }}</td>
          <td>{{ row.status }}</td>
          <td>{{ row.amount }} {{ row.currency }}</td>
          <td>{{ row.created_at }}</td>
        </tr>
        <tr v-if="!rows.length" class="px-table-row" style="--px-table-row-index: 1">
          <td colspan="5" class="px-muted">No payments yet.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
