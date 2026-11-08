<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'

const loading = ref(true)

const rows = ref([])
const error = ref('')

const start = new Date()
start.setDate(start.getDate() - start.getDay())

const days = Array.from({ length: 7 }, (_, index) => {
  const day = new Date(start)
  day.setDate(start.getDate() + index)
  return day
})

function isoDate(day) {
  const year = day.getFullYear()
  const month = String(day.getMonth() + 1).padStart(2, '0')
  const date = String(day.getDate()).padStart(2, '0')

  return `${year}-${month}-${date}`
}

function onDay(day) {
  const key = isoDate(day)

  return rows.value.filter((row) => (row.appointment.start_datetime || '').startsWith(key))
}

onMounted(async () => {
  try {
    const from = isoDate(days[0])
    const to = isoDate(days[6]) + ' 23:59:59'
    rows.value = await api(`appointments?from=${from}&to=${to}`)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <p v-if="error" class="px-error">{{ error }}</p>

  <LoadingSkeleton v-if="loading" variant="calendar" :columns="7" label="Loading calendar" />
  <div v-else class="px-calendar">
    <div class="px-cal-head"></div>
    <div v-for="day in days" :key="day.toISOString()" class="px-cal-head">
      {{ day.toDateString() }}
    </div>

    <div class="px-cal-head">All day</div>
    <div v-for="day in days" :key="'col-' + day.toISOString()">
      <span v-for="row in onDay(day)" :key="row.appointment.id" class="px-chip">
        {{ row.appointment.start_datetime.slice(11, 16) }}
        {{ row.customer?.full_name }} — {{ row.service?.name }}
      </span>
      <span v-if="!onDay(day).length" class="px-muted">—</span>
    </div>
  </div>
</template>
