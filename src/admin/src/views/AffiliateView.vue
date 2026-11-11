<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import { copyText } from '../utils/clipboard'
import PxModal from '../components/PxModal.vue'
import DeleteConfirmation from '../components/DeleteConfirmation.vue'
import { useDeleteConfirmation } from '../composables/useDeleteConfirmation'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'
const { confirmation, confirmDelete, resolveDelete } = useDeleteConfirmation()
const rows = ref([]),
  selected = ref([]),
  loading = ref(true),
  saving = ref(false),
  error = ref(''),
  message = ref(''),
  form = ref(null),
  expanded = ref(null),
  bookings = ref([]),
  detailsLoading = ref(false)
let detailRequest = 0
async function load() {
  loading.value = true
  try {
    rows.value = await api('admin/affiliates')
    selected.value = selected.value.filter((id) => rows.value.some((row) => row.id === id))
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
function start(row) {
  error.value = ''
  form.value = row ? { ...row } : { name: '', booking_url: '', enabled: 1 }
}
async function save() {
  saving.value = true
  error.value = ''
  try {
    await api('admin/affiliates' + (form.value.id ? '/' + form.value.id : ''), {
      method: form.value.id ? 'PUT' : 'POST',
      body: form.value,
    })
    form.value = null
    message.value = 'Affiliate link saved.'
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
async function remove(ids) {
  if (
    !(await confirmDelete({
      heading: 'Delete affiliate links?',
      bodyText:
        'Remove selected affiliate links and their attribution? Appointments and payments will be retained.',
    }))
  )
    return
  saving.value = true
  error.value = ''
  try {
    await api('admin/affiliates/bulk-delete', { method: 'POST', body: { ids } })
    expanded.value = null
    detailRequest++
    selected.value = []
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
async function toggle(row) {
  if (expanded.value === row.id) {
    expanded.value = null
    detailRequest++
    return
  }
  expanded.value = row.id
  bookings.value = []
  detailsLoading.value = true
  const request = ++detailRequest
  try {
    const result = await api('admin/affiliates/' + row.id + '/bookings')
    if (request === detailRequest) bookings.value = result
  } catch (e) {
    if (request === detailRequest) error.value = e.message
  } finally {
    if (request === detailRequest) detailsLoading.value = false
  }
}
async function copy(row) {
  error.value = ''
  message.value = ''
  try {
    await copyText(row.link)
    message.value = 'Affiliate link copied.'
  } catch {
    error.value = 'Copy is unavailable. Select the link text and copy it manually.'
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
    <button
      class="px-button px-button-danger"
      :disabled="loading || saving || !selected.length"
      @click="remove([...selected])"
    >
      Delete selected</button
    ><button class="px-button" :disabled="loading || saving" @click="start()">Add affiliate</button>
  </div>
  <p class="px-muted">
    Create a link to your published booking page. Expand an affiliate to see every attributed
    appointment, including pending and cancelled bookings.
  </p>
  <p v-if="error" class="px-error" role="alert">{{ error }}</p>
  <p v-if="message" class="px-success" role="status">{{ message }}</p>
  <PxModal
    :open="!!form"
    :heading="form?.id ? 'Edit affiliate' : 'Create affiliate'"
    :busy="saving"
    form-id="affiliate-form"
    primary-text="Save affiliate"
    @close="form = null"
    ><form v-if="form" id="affiliate-form" class="px-form" @submit.prevent="save">
      <p v-if="error" class="px-error" role="alert">{{ error }}</p>
      <div class="px-field">
        <label for="affiliate-name">Name</label
        ><input id="affiliate-name" v-model="form.name" required maxlength="191" />
      </div>
      <div class="px-field">
        <label for="affiliate-url">Booking page URL</label
        ><input
          id="affiliate-url"
          v-model="form.booking_url"
          type="url"
          required
          maxlength="2000"
          placeholder="https://your-site.example/booking/"
        /><small class="px-muted"
          >Use a page on this site containing the booking form. Existing query parameters are
          preserved.</small
        >
      </div>
      <div class="px-field">
        <label for="affiliate-enabled">Tracking</label
        ><select id="affiliate-enabled" v-model.number="form.enabled">
          <option :value="1">Enabled</option>
          <option :value="0">Disabled</option>
        </select>
      </div>
    </form></PxModal
  >
  <LoadingSkeleton v-if="loading" label="Loading affiliates" />
  <div v-else class="px-card px-affiliate-table">
    <table class="px-table">
      <thead>
        <tr class="px-table-row" style="--px-table-row-index: 0">
          <th>
            <input
              type="checkbox"
              aria-label="Select all affiliates"
              :checked="!!rows.length && selected.length === rows.length"
              :indeterminate="!!selected.length && selected.length < rows.length"
              :disabled="saving"
              @change="selected = $event.target.checked ? rows.map((row) => row.id) : []"
            />
          </th>
          <th>ID</th>
          <th>Name</th>
          <th>Count</th>
          <th>Affiliate link</th>
          <th>Created at</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="(row, index) in rows" :key="row.id"
          ><tr class="px-table-row" :style="{ '--px-table-row-index': index + 1 }">
            <td>
              <input
                v-model="selected"
                type="checkbox"
                :value="row.id"
                :disabled="saving"
                :aria-label="'Select ' + row.name"
              />
            </td>
            <td>{{ row.id }}</td>
            <td>
              <button
                type="button"
                class="px-button px-button-ghost"
                :aria-expanded="expanded === row.id"
                :aria-controls="'affiliate-bookings-' + row.id"
                @click="toggle(row)"
              >
                {{ row.name }}</button
              ><small v-if="!Number(row.enabled)" class="px-muted">Disabled</small>
            </td>
            <td>{{ row.booking_count }}</td>
            <td>
              <a :href="row.link" target="_blank" rel="noopener noreferrer">{{ row.link }}</a
              ><button type="button" class="px-button px-button-ghost" @click="copy(row)">
                Copy link
              </button>
            </td>
            <td>{{ row.created_at }}</td>
            <td>
              <button class="px-button-outline approve" :disabled="saving" @click="start(row)">
                Edit</button
              ><button
                class="px-button-outline cancel"
                :disabled="saving"
                @click="remove([row.id])"
              >
                Delete
              </button>
            </td>
          </tr>
          <tr
            class="px-table-row"
            style="--px-table-row-index: 1"
            v-if="expanded === row.id"
            :id="'affiliate-bookings-' + row.id"
          >
            <td colspan="7">
              <LoadingSkeleton v-if="detailsLoading" label="Loading affiliate appointments" />
              <table v-else class="px-table">
                <thead>
                  <tr class="px-table-row" style="--px-table-row-index: 0">
                    <th>Appointment</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Staff</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    class="px-table-row"
                    style="--px-table-row-index: 1"
                    v-for="booking in bookings"
                    :key="booking.appointment.id"
                  >
                    <td>#{{ booking.appointment.id }}</td>
                    <td>{{ booking.customer?.full_name }}</td>
                    <td>{{ booking.service?.name }}</td>
                    <td>{{ booking.staff?.full_name }}</td>
                    <td>{{ booking.appointment.start_datetime }}</td>
                    <td>{{ booking.appointment.status }}</td>
                    <td>
                      <PxCurrency
                        :amount="booking.amount"
                        :currency="booking.payments?.[0]?.currency"
                      />
                    </td>
                  </tr>
                  <tr class="px-table-row" style="--px-table-row-index: 1" v-if="!bookings.length">
                    <td colspan="7">No attributed appointments yet.</td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr></template
        >
        <tr class="px-table-row" style="--px-table-row-index: 1" v-if="!rows.length">
          <td colspan="7" class="px-muted">No affiliate links yet.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
<style scoped>
.px-affiliate-table {
  overflow-x: auto;
}
.px-affiliate-table a {
  display: block;
  max-width: 320px;
  overflow-wrap: anywhere;
}
</style>
