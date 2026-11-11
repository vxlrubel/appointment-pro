<script setup>
import { computed, onMounted, ref } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'
import DeleteConfirmation from '../components/DeleteConfirmation.vue'
import { useDeleteConfirmation } from '../composables/useDeleteConfirmation'
const { confirmation, confirmDelete, resolveDelete } = useDeleteConfirmation()
const rows = ref([]),
  loading = ref(true),
  saving = ref(false),
  error = ref(''),
  message = ref(''),
  form = ref(null)
const range = ref({ start: new Date().getFullYear(), end: new Date().getFullYear() + 1 })
function blank(date) {
  const today = new Date()
  const localDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
  form.value = {
    name: '',
    description: '',
    holiday_date: date || localDate,
    repeats_yearly: 1,
    enabled: 1,
  }
  error.value = ''
  message.value = ''
}
const events = computed(() => {
  const out = []
  for (const holiday of rows.value) {
    if (!Number(holiday.repeats_yearly)) {
      out.push({
        id: String(holiday.id),
        title: holiday.name,
        start: holiday.holiday_date,
        allDay: true,
        backgroundColor: Number(holiday.enabled) ? '#0d6efd' : '#9ca3af',
      })
      continue
    }
    for (let year = range.value.start; year <= range.value.end; year++) {
      const date = year + holiday.holiday_date.slice(4),
        parsed = new Date(date + 'T12:00:00')
      if (parsed.getMonth() + 1 !== Number(date.slice(5, 7))) continue
      out.push({
        id: String(holiday.id),
        title: holiday.name,
        start: date,
        allDay: true,
        backgroundColor: Number(holiday.enabled) ? '#0d6efd' : '#9ca3af',
      })
    }
  }
  return out
})
const options = computed(() => ({
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  height: 'auto',
  fixedWeekCount: false,
  headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
  events: events.value,
  dayMaxEvents: 3,
  dateClick: (info) => {
    if (!saving.value) blank(info.dateStr)
  },
  eventClick: (info) => {
    const row = rows.value.find((row) => String(row.id) === info.event.id)
    if (row && !saving.value) form.value = { ...row }
  },
  datesSet: (info) => {
    const start = info.start.getFullYear(),
      end = info.end.getFullYear()
    if (range.value.start !== start || range.value.end !== end) range.value = { start, end }
  },
}))
async function load() {
  try {
    rows.value = await api('admin/holidays')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
async function save() {
  saving.value = true
  error.value = ''
  try {
    await api('admin/holidays' + (form.value.id ? '/' + form.value.id : ''), {
      method: form.value.id ? 'PUT' : 'POST',
      body: form.value,
    })
    form.value = null
    message.value = 'Holiday saved. Booking availability has been updated.'
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
async function remove(row) {
  if (
    saving.value ||
    !(await confirmDelete({
      heading: 'Delete holiday?',
      bodyText: `Delete ${row.name}? This date will become available according to staff schedules.`,
    }))
  )
    return
  saving.value = true
  try {
    await api('admin/holidays/' + row.id, { method: 'DELETE' })
    if (form.value?.id === row.id) form.value = null
    await load()
    message.value = 'Holiday deleted.'
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
onMounted(load)
</script>
<template>
  <DeleteConfirmation
    v-bind="confirmation"
    @confirm="resolveDelete(true)"
    @cancel="resolveDelete(false)"
  />
  <div class="px-toolbar">
    <p class="px-muted">
      Select a date to add a holiday. Select an event to edit it. Enabled holidays close all booking
      locations.
    </p>
    <button class="px-button" :disabled="loading || saving" @click="blank()">Add holiday</button>
  </div>
  <p v-if="error" class="px-error" role="alert">{{ error }}</p>
  <p v-if="message" class="px-success" role="status">{{ message }}</p>
  <LoadingSkeleton v-if="loading" variant="calendar" label="Loading holidays" />
  <template v-else>
    <form v-if="form" class="px-card px-form" @submit.prevent="save">
      <h3 class="mt-0">{{ form.id ? 'Edit holiday' : 'New holiday' }}</h3>
      <div class="px-form-row">
        <div class="px-field">
          <label for="holiday-name">Name</label
          ><input id="holiday-name" v-model="form.name" required maxlength="191" />
        </div>
        <div class="px-field">
          <label for="holiday-date">Date</label
          ><input id="holiday-date" v-model="form.holiday_date" type="date" required />
        </div>
        <div class="px-field">
          <label for="holiday-repeat">Repeat every year</label
          ><select id="holiday-repeat" v-model.number="form.repeats_yearly">
            <option :value="1">Yes</option>
            <option :value="0">No, this year only</option>
          </select>
        </div>
        <div class="px-field">
          <label for="holiday-enabled">Block bookings</label
          ><select id="holiday-enabled" v-model.number="form.enabled">
            <option :value="1">Enabled</option>
            <option :value="0">Disabled</option>
          </select>
        </div>
      </div>
      <div class="px-field">
        <label for="holiday-description">Description</label
        ><textarea id="holiday-description" v-model="form.description" rows="3" required></textarea>
      </div>
      <div class="px-actions">
        <button class="px-button" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save holiday' }}</button
        ><button
          class="px-button px-button-ghost"
          type="button"
          :disabled="saving"
          @click="form = null"
        >
          Cancel
        </button>
      </div>
    </form>
    <div class="px-card px-holiday-calendar"><FullCalendar :options="options" /></div>
    <div class="px-card">
      <h3 class="mt-0">Holiday schedule</h3>
      <table class="px-table">
        <thead>
          <tr class="px-table-row" style="--px-table-row-index: 0">
            <th>Name</th>
            <th>Date</th>
            <th>Repeats yearly</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in rows"
            :key="row.id"
            class="px-table-row"
            :style="{ '--px-table-row-index': index + 1 }"
          >
            <td>{{ row.name }}</td>
            <td>{{ row.holiday_date }}</td>
            <td>{{ Number(row.repeats_yearly) ? 'Yes' : 'No' }}</td>
            <td>{{ Number(row.enabled) ? 'Closed' : 'Disabled' }}</td>
            <td>
              <button
                class="px-button-outline approve"
                :disabled="saving"
                @click="form = { ...row }"
              >
                Edit</button
              ><button class="px-button-outline cancel" :disabled="saving" @click="remove(row)">
                Delete
              </button>
            </td>
          </tr>
          <tr v-if="!rows.length" class="px-table-row" style="--px-table-row-index: 1">
            <td colspan="5" class="px-muted">
              No holidays yet. Select a date above to get started.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </template>
</template>
<style scoped>
.px-holiday-calendar {
  --fc-border-color: var(--px-line);
  --fc-button-bg-color: var(--px-primary);
  --fc-button-border-color: var(--px-primary);
  --fc-button-hover-bg-color: #0b5ed7;
  --fc-button-hover-border-color: #0b5ed7;
  --fc-button-active-bg-color: #0a58ca;
  --fc-today-bg-color: #f0f6ff;
}
.px-holiday-calendar :deep(.fc-toolbar-title) {
  font-size: 22px;
}
.px-holiday-calendar :deep(.fc-daygrid-day) {
  cursor: pointer;
}
.px-holiday-calendar :deep(.fc-daygrid-day-number),
.px-holiday-calendar :deep(.fc-col-header-cell-cushion) {
  color: var(--px-color);
  text-decoration: none;
}
.px-holiday-calendar :deep(.fc-event) {
  cursor: pointer;
  border: 0;
  border-radius: 4px;
  padding: 2px 5px;
}
@media (max-width: 640px) {
  .px-holiday-calendar :deep(.fc-toolbar) {
    flex-direction: column;
    gap: 12px;
  }
}
</style>
