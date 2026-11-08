<script setup>
import { onMounted, ref, watch } from 'vue'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'

const loading = ref(true)

const selected = ref([])
async function bulkDelete() {
  if (!selected.value.length || !window.confirm('Delete selected records?')) return
  try {
    await api('appointments/bulk-delete', { method: 'POST', body: { ids: selected.value } })
    selected.value = []
    await load()
  } catch (e) {
    error.value = e.message
  }
}
const rows = ref([])
const showForm = ref(false)
const error = ref('')
const saving = ref(false)
const locations = ref([])
const services = ref([])
const staff = ref([])
const slots = ref([])
const form = ref(blankForm())

function blankForm() {
  return {
    location_id: '',
    service_id: '',
    staff_id: '',
    date: new Date().toISOString().slice(0, 10),
    start: '',
    customer: { full_name: '', email: '', phone: '' },
    notes: '',
    status: 'approved',
  }
}

async function load() {
  loading.value = true
  try {
    rows.value = await api('appointments')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function openForm() {
  error.value = ''
  form.value = blankForm()
  locations.value = await api('admin/locations')
  services.value = await api('admin/services')

  if (locations.value[0]) {
    form.value.location_id = locations.value[0].id
  }

  if (services.value[0]) {
    form.value.service_id = services.value[0].id
  }

  showForm.value = true
  await loadStaffAndSlots()
}

async function loadStaffAndSlots() {
  staff.value = []
  slots.value = []
  form.value.start = ''

  if (!form.value.location_id || !form.value.service_id) {
    return
  }

  try {
    staff.value = await api(
      `staff?location_id=${form.value.location_id}&service_id=${form.value.service_id}`,
    )
    await loadSlots()
  } catch (e) {
    error.value = e.message
  }
}

async function loadSlots() {
  slots.value = []

  if (!form.value.location_id || !form.value.service_id || !form.value.date) {
    return
  }

  try {
    const staffId = form.value.staff_id || 0
    slots.value = await api(
      `availability?location_id=${form.value.location_id}&service_id=${form.value.service_id}&staff_id=${staffId}&date=${form.value.date}`,
    )
  } catch (e) {
    error.value = e.message
  }
}

watch(
  () => [form.value.location_id, form.value.service_id],
  () => {
    if (showForm.value) {
      loadStaffAndSlots()
    }
  },
)

watch(
  () => [form.value.staff_id, form.value.date],
  () => {
    if (showForm.value) {
      loadSlots()
    }
  },
)

async function save() {
  error.value = ''

  if (!form.value.customer.full_name || !form.value.customer.email) {
    error.value = 'Customer name and email are required.'
    return
  }

  if (!form.value.start) {
    error.value = 'Select a time slot.'
    return
  }

  const slot = slots.value.find((item) => item.start === form.value.start)
  saving.value = true

  try {
    const created = await api('appointments', {
      method: 'POST',
      body: {
        location_id: form.value.location_id,
        service_id: form.value.service_id,
        staff_id: slot?.staff_id || form.value.staff_id,
        start: form.value.start,
        customer: form.value.customer,
        notes: form.value.notes,
        extras: [],
        payment_method: 'local',
      },
    })

    const appointmentId = created.appointment.id
    const alreadyPaid =
      (created.payments || []).some((payment) => payment.status === 'completed') ||
      created.appointment?.status === 'approved'

    if (!alreadyPaid) {
      await api('payments/local', {
        method: 'POST',
        body: { appointment_id: appointmentId, amount: created.amount || 0 },
      })
    }

    if (form.value.status && form.value.status !== 'approved') {
      await api('appointments/' + appointmentId, {
        method: 'PATCH',
        body: { status: form.value.status },
      })
    }

    showForm.value = false
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function setStatus(id, status) {
  try {
    await api('appointments/' + id, { method: 'PATCH', body: { status } })
    await load()
  } catch (e) {
    error.value = e.message
  }
}

onMounted(load)
</script>

<template>
  <div class="px-toolbar">
    <button
      class="px-button px-button-danger"
      :disabled="loading || !selected.length"
      @click="bulkDelete"
    >
      Delete selected
    </button>
    <button class="px-button" type="button" :disabled="loading" @click="openForm">
      Add appointment
    </button>
  </div>

  <p v-if="error" class="px-error">{{ error }}</p>

  <div v-if="showForm" class="px-card">
    <h3 class="mt-0">New appointment</h3>

    <form class="px-form" @submit.prevent="save">
      <div class="px-form-row">
        <div class="px-field">
          <label>Location</label>
          <select v-model.number="form.location_id">
            <option v-for="location in locations" :key="location.id" :value="location.id">
              {{ location.name }}
            </option>
          </select>
        </div>

        <div class="px-field">
          <label>Service</label>
          <select v-model.number="form.service_id">
            <option v-for="service in services" :key="service.id" :value="service.id">
              {{ service.name }}
            </option>
          </select>
        </div>

        <div class="px-field">
          <label>Staff</label>
          <select v-model.number="form.staff_id">
            <option value="">Any staff</option>
            <option v-for="member in staff" :key="member.id" :value="member.id">
              {{ member.full_name }}
            </option>
          </select>
        </div>

        <div class="px-field">
          <label>Date</label>
          <input v-model="form.date" type="date" />
        </div>

        <div class="px-field">
          <label>Time</label>
          <select v-model="form.start">
            <option value="">Select a slot</option>
            <option v-for="slot in slots" :key="slot.start + slot.staff_id" :value="slot.start">
              {{ slot.display
              }}{{ !form.staff_id && slot.staff_id ? ' (staff #' + slot.staff_id + ')' : '' }}
            </option>
          </select>
        </div>

        <div class="px-field">
          <label>Status</label>
          <select v-model="form.status">
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
          </select>
        </div>
      </div>

      <p v-if="!slots.length" class="px-muted">No available times for this date.</p>

      <div class="px-form-row">
        <div class="px-field">
          <label>Customer name</label>
          <input v-model="form.customer.full_name" required />
        </div>

        <div class="px-field">
          <label>Email</label>
          <input v-model="form.customer.email" type="email" required />
        </div>

        <div class="px-field">
          <label>Phone</label>
          <input v-model="form.customer.phone" />
        </div>
      </div>

      <div class="px-field">
        <label>Notes</label>
        <textarea v-model="form.notes" rows="3"></textarea>
      </div>

      <div class="px-actions">
        <button class="px-button" type="submit" :disabled="saving">
          {{ saving ? 'Saving...' : 'Create appointment' }}
        </button>
        <button class="px-button px-button-ghost" type="button" @click="showForm = false">
          Cancel
        </button>
      </div>
    </form>
  </div>

  <LoadingSkeleton v-if="loading" :columns="8" label="Loading appointments" />
  <div v-else class="px-card">
    <table class="px-table">
      <thead>
        <tr>
          <th>
            <input
              type="checkbox"
              aria-label="Select all"
              @change="selected = $event.target.checked ? rows.map((r) => r.appointment.id) : []"
            />
          </th>
          <th>When</th>
          <th>Customer</th>
          <th>Service</th>
          <th>Staff</th>
          <th>Status</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.appointment.id">
          <td>
            <input
              v-model="selected"
              type="checkbox"
              :value="row.appointment.id"
              aria-label="Select record"
            />
          </td>
          <td>{{ row.appointment.start_datetime }}</td>
          <td>{{ row.customer?.full_name }}</td>
          <td>{{ row.service?.name }}</td>
          <td>{{ row.staff?.full_name }}</td>
          <td>{{ row.appointment.status }}</td>
          <td>{{ row.appointment.created_at }}</td>
          <td>
            <button
              class="px-button-outline approve"
              type="button"
              @click="setStatus(row.appointment.id, 'approved')"
            >
              Approve
            </button>
            <button
              class="px-button-outline cancel"
              type="button"
              @click="setStatus(row.appointment.id, 'cancelled')"
            >
              Cancel
            </button>
            <button
              class="px-button-outline done"
              type="button"
              @click="setStatus(row.appointment.id, 'done')"
            >
              Done
            </button>
          </td>
        </tr>
        <tr v-if="!rows.length">
          <td colspan="7" class="px-muted">No appointments yet.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
