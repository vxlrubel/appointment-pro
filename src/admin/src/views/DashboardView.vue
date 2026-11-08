<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'

const loading = ref(true)

const data = ref({ counts: {}, upcoming: [], mail_error: '' })
const error = ref('')

onMounted(async () => {
  try {
    data.value = await api('dashboard')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <p v-if="error" class="px-error">{{ error }}</p>
  <p v-if="data.mail_error" class="px-error">Last mail error: {{ data.mail_error }}</p>

  <LoadingSkeleton v-if="loading" variant="dashboard" :columns="4" label="Loading dashboard" />
  <template v-else>
    <div class="px-dynamic-grid">
      <div class="p-3 bg-white border">
        <p class="mt-0">Today</p>
        <div class="fs-4">{{ data.counts.today || 0 }}</div>
      </div>
      <div class="p-3 bg-white border">
        <p class="mt-0">Pending</p>
        <div class="fs-4">{{ data.counts.pending || 0 }}</div>
      </div>
      <div class="p-3 bg-white border">
        <p class="mt-0">Approved</p>
        <div class="fs-4">{{ data.counts.approved || 0 }}</div>
      </div>
      <div class="p-3 bg-white border">
        <p class="mt-0">Revenue</p>
        <div class="fs-4">{{ data.counts.revenue || 0 }}</div>
      </div>
    </div>

    <div class="px-card mt-3">
      <h3 class="mt-0">Upcoming</h3>
      <table class="px-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Staff</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in data.upcoming" :key="row.appointment.id">
            <td>{{ row.appointment.start_datetime }}</td>
            <td>{{ row.customer?.full_name }}</td>
            <td>{{ row.service?.name }}</td>
            <td>{{ row.staff?.full_name }}</td>
          </tr>
          <tr v-if="!data.upcoming.length">
            <td colspan="4" class="px-muted">No upcoming appointments.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </template>
</template>
