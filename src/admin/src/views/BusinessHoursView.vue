<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'
import WeeklyHoursEditor from '../components/WeeklyHoursEditor.vue'
const rows = ref([]),
  locations = ref([]),
  loading = ref(true),
  saving = ref(false),
  error = ref(''),
  message = ref('')
onMounted(async () => {
  try {
    ;[rows.value, locations.value] = await Promise.all([
      api('admin/business-hours'),
      api('admin/locations'),
    ])
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
async function save() {
  saving.value = true
  error.value = ''
  message.value = ''
  try {
    rows.value = await api('admin/business-hours', { method: 'POST', body: rows.value })
    message.value = 'Business hours saved. Booking availability has been updated.'
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>
<template>
  <p class="px-muted">
    Default weekly hours and breaks for staff with no personal weekly schedule. Dates without hours
    are closed. Staff schedules override the whole business week; holidays and time off still apply.
  </p>
  <p v-if="error" class="px-error" role="alert">{{ error }}</p>
  <p v-if="message" class="px-success" role="status">{{ message }}</p>
  <LoadingSkeleton v-if="loading" variant="form" label="Loading business hours" />
  <div v-else class="px-card">
    <WeeklyHoursEditor v-model="rows" :locations="locations" :disabled="saving" />
    <div class="px-actions">
      <button class="px-button" :disabled="saving" @click="save">
        {{ saving ? 'Saving...' : 'Save business hours' }}
      </button>
    </div>
  </div>
</template>
