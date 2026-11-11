<script setup>
import WeeklyHoursEditor from '../components/WeeklyHoursEditor.vue'
import PxModal from '../components/PxModal.vue'
import { onMounted, ref } from 'vue'
import { api } from '../api'
import DeleteConfirmation from '../components/DeleteConfirmation.vue'
import { useDeleteConfirmation } from '../composables/useDeleteConfirmation'

const { confirmation, confirmDelete, resolveDelete } = useDeleteConfirmation()
const deleting = ref(false)
import LoadingSkeleton from '../components/LoadingSkeleton.vue'

const loading = ref(true)

const selected = ref([])
async function bulkDelete() {
  if (loading.value || deleting.value || !selected.value.length) return
  const ids = [...selected.value]
  if (
    !(await confirmDelete({
      heading: 'Delete selected staff records?',
      bodyText: `Permanently delete ${ids.length} selected staff records? This cannot be undone.`,
    }))
  )
    return
  deleting.value = true
  error.value = ''
  try {
    await api('admin/staff/bulk-delete', { method: 'POST', body: { ids } })
    selected.value = []
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    deleting.value = false
  }
}
const rows = ref([])
const form = ref({})
const editing = ref(null)
const daysOff = ref([])
const showLeave = ref(false)
const loadingExtras = ref(false)
const leave = ref({ start_datetime: '', end_datetime: '', all_day: 1, reason: '' })
async function addDayOff() {
  if (saving.value) return
  saving.value = true
  error.value = ''
  try {
    await api('staff/' + editing.value + '/days-off', { method: 'POST', body: leave.value })
    daysOff.value = await api('staff/' + editing.value + '/days-off')
    showLeave.value = false
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
async function removeDayOff(id) {
  if (
    !(await confirmDelete({
      heading: 'Delete time off?',
      bodyText: 'Remove this staff time off record?',
    }))
  )
    return
  try {
    await api('staff-days-off/' + id, { method: 'DELETE' })
    daysOff.value = await api('staff/' + editing.value + '/days-off')
  } catch (e) {
    error.value = e.message
  }
}
const schedule = ref([])
const services = ref([])
const locations = ref([])
const allServices = ref([])
const error = ref('')
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    rows.value = await api('admin/staff')
    locations.value = await api('admin/locations')
    allServices.value = await api('admin/services')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function start() {
  form.value = { full_name: '', email: '', phone: '', enabled: 1 }
  editing.value = 'new'
  loadingExtras.value = false
  daysOff.value = []
  showLeave.value = false
  schedule.value = []
  services.value = []
  error.value = ''
}

function edit(row) {
  form.value = { ...row }
  editing.value = row.id
  error.value = ''
  loadExtras(row.id)
}

async function loadExtras(id) {
  loadingExtras.value = true
  daysOff.value = []
  schedule.value = []
  services.value = []
  try {
    const [off, hours, links] = await Promise.all([
      api('staff/' + id + '/days-off'),
      api('staff/' + id + '/schedule'),
      api('staff/' + id + '/services'),
    ])
    if (editing.value === id) {
      daysOff.value = off
      schedule.value = hours
      services.value = links
    }
  } catch (e) {
    if (editing.value === id) error.value = e.message
  } finally {
    if (editing.value === id) loadingExtras.value = false
  }
}

async function save() {
  saving.value = true
  error.value = ''

  try {
    if (editing.value === 'new') {
      const created = await api('admin/staff', { method: 'POST', body: form.value })
      editing.value = created.id
      schedule.value = []
      services.value = []
    } else {
      await api('admin/staff/' + editing.value, { method: 'PUT', body: form.value })
    }

    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function saveSchedule() {
  if (saving.value || loadingExtras.value) return
  saving.value = true
  error.value = ''
  try {
    schedule.value = await api('staff/' + editing.value + '/schedule', {
      method: 'POST',
      body: schedule.value,
    })
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function saveServices() {
  if (saving.value || loadingExtras.value) return
  saving.value = true
  error.value = ''
  try {
    services.value = await api('staff/' + editing.value + '/services', {
      method: 'POST',
      body: services.value,
    })
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

function addServiceLink() {
  services.value.push({
    service_id: allServices.value[0]?.id,
    location_id: locations.value[0]?.id,
    price: null,
  })
}

function removeServiceLink(index) {
  services.value.splice(index, 1)
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
    <button
      class="px-button px-button-danger"
      :disabled="loading || deleting || !selected.length"
      @click="bulkDelete"
    >
      Delete selected
    </button>
    <button class="px-button" type="button" :disabled="loading" @click="start">Add</button>
  </div>

  <p v-if="error" class="px-error">{{ error }}</p>

  <PxModal
    :open="!!editing"
    :heading="editing === 'new' ? 'Create staff' : 'Edit staff'"
    :busy="saving"
    form-id="staff-form"
    primary-text="Save staff"
    @close="editing = null"
  >
    <p v-if="error" class="px-error" role="alert">{{ error }}</p>
    <form id="staff-form" class="px-form" @submit.prevent="save">
      <div class="px-form-row">
        <div class="px-field">
          <label>Name</label>
          <input v-model="form.full_name" required />
        </div>
        <div class="px-field">
          <label>Email</label>
          <input v-model="form.email" type="email" />
        </div>
        <div class="px-field">
          <label>Phone</label>
          <input v-model="form.phone" />
        </div>
        <div class="px-field">
          <label>Enabled</label>
          <select v-model.number="form.enabled">
            <option :value="1">Yes</option>
            <option :value="0">No</option>
          </select>
        </div>
      </div>
    </form>

    <LoadingSkeleton
      v-if="editing !== 'new' && loadingExtras"
      variant="form"
      label="Loading staff schedule"
    />
    <div v-else-if="editing !== 'new'">
      <h3>Weekly hours</h3>
      <p class="px-muted">
        When no weekly hours are saved, Business Hours apply. A staff schedule overrides the entire
        business week.
      </p>

      <WeeklyHoursEditor v-model="schedule" :locations="locations" :disabled="saving" />
      <div class="px-actions">
        <button class="px-button" type="button" :disabled="saving" @click="saveSchedule">
          Save hours
        </button>
      </div>

      <h3>Days off (UTC)</h3>
      <div v-for="day in daysOff" :key="day.id" class="px-row">
        <span>{{ day.start_datetime }} to {{ day.end_datetime }} {{ day.reason }}</span
        ><button class="px-button px-button-danger" @click="removeDayOff(day.id)">Remove</button>
      </div>
      <button class="px-button px-button-ghost" type="button" @click="showLeave = true">
        Add time off
      </button>
      <PxModal
        :open="showLeave"
        heading="Staff time off"
        :busy="saving"
        form-id="time-off-form"
        primary-text="Add time off"
        @close="showLeave = false"
      >
        <form id="time-off-form" class="px-form" @submit.prevent="addDayOff">
          <p v-if="error" class="px-error" role="alert">{{ error }}</p>
          <div class="px-form-row">
            <div class="px-field">
              <label>From</label
              ><input v-model="leave.start_datetime" type="datetime-local" required />
            </div>
            <div class="px-field">
              <label>Until</label
              ><input v-model="leave.end_datetime" type="datetime-local" required />
            </div>
            <div class="px-field"><label>Reason</label><input v-model="leave.reason" /></div>
            <label
              ><input v-model="leave.all_day" type="checkbox" :true-value="1" :false-value="0" />
              All day</label
            >
          </div>
        </form>
      </PxModal>
      <h3>Services at locations</h3>

      <div v-for="(link, index) in services" :key="'svc-' + index" class="px-row">
        <select v-model.number="link.service_id">
          <option v-for="service in allServices" :key="service.id" :value="service.id">
            {{ service.name }}
          </option>
        </select>
        <select v-model.number="link.location_id">
          <option v-for="location in locations" :key="location.id" :value="location.id">
            {{ location.name }}
          </option>
        </select>
        <input
          v-model.number="link.price"
          type="number"
          min="0"
          step="0.01"
          placeholder="Price override"
          aria-label="Price override"
        /><button
          class="px-button px-button-danger"
          type="button"
          @click="removeServiceLink(index)"
        >
          Remove
        </button>
      </div>

      <div class="px-actions">
        <button class="px-button px-button-ghost" type="button" @click="addServiceLink">
          Add service
        </button>
        <button class="px-button" type="button" :disabled="saving" @click="saveServices">
          Save services
        </button>
      </div>
    </div>
  </PxModal>

  <LoadingSkeleton v-if="loading" :columns="6" label="Loading staff" />
  <div v-else class="px-card">
    <table class="px-table">
      <thead>
        <tr class="px-table-row" style="--px-table-row-index: 0">
          <th>
            <input
              type="checkbox"
              aria-label="Select all"
              @change="selected = $event.target.checked ? rows.map((r) => r.id) : []"
            />
          </th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Enabled</th>
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
          <td>
            <input v-model="selected" type="checkbox" :value="row.id" aria-label="Select record" />
          </td>
          <td>{{ row.full_name }}</td>
          <td>{{ row.email }}</td>
          <td>{{ row.phone }}</td>
          <td>{{ row.enabled ? 'Yes' : 'No' }}</td>
          <td>
            <button class="px-button-outline approve" type="button" @click="edit(row)">Edit</button>
          </td>
        </tr>
        <tr v-if="!rows.length" class="px-table-row" style="--px-table-row-index: 1">
          <td colspan="5" class="px-muted">No staff yet.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
