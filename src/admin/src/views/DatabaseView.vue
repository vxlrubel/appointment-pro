<script setup>
import { useToastFeedback } from '../../../shared/useToastFeedback'
import { onMounted, ref } from 'vue'
import { api, refreshAdminCacheSettings } from '../api'
import { adminCacheState, configureAdminCache } from '../cache'
import DeleteConfirmation from '../components/DeleteConfirmation.vue'
import { useDeleteConfirmation } from '../composables/useDeleteConfirmation'
const { confirmation, confirmDelete, resolveDelete } = useDeleteConfirmation()

const busy = ref('')
const message = ref('')
const error = ref('')
const importFile = ref(null)
const cacheLoading = ref(true)

onMounted(async () => {
  try {
    await refreshAdminCacheSettings()
  } catch (e) {
    error.value = e.message
  } finally {
    cacheLoading.value = false
  }
})

async function changeCache(event) {
  const enabled = event.target.checked
  event.target.checked = adminCacheState.enabled
  busy.value = 'cache'
  clearStatus()
  try {
    const settings = await api('admin/cache', {
      method: 'POST',
      body: { enabled },
      toast: {
        working: 'Updating cache settings…',
        success: enabled ? 'Admin cache enabled.' : 'Admin cache disabled.',
      },
    })
    configureAdminCache(settings)
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = ''
  }
}

async function purgeCache() {
  busy.value = 'purge'
  clearStatus()
  try {
    const settings = await api('admin/cache', {
      method: 'DELETE',
      toast: {
        working: 'Purging cached data…',
        success: 'Admin cache purged. Pages will load fresh data.',
      },
    })
    configureAdminCache(settings)
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = ''
  }
}

function clearStatus() {
  message.value = ''
  error.value = ''
}

async function resetDb() {
  if (
    !(await confirmDelete({
      heading: 'Reset booking database?',
      bodyText:
        'Reset the database? All locations, services, staff, customers, appointments, extras, coupons, and custom fields will be emptied. Notification templates are kept.',
    }))
  ) {
    return
  }
  busy.value = 'reset'
  clearStatus()
  try {
    await api('admin/database/reset', {
      method: 'POST',
      body: {},
      toast: {
        working: 'Resetting the database…',
        success: 'Database reset. Notification templates were kept.',
      },
    })
    message.value = 'Database reset. Booking data is empty. Notification templates were kept.'
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = ''
  }
}

async function insertDemo() {
  if (
    !(await confirmDelete({
      heading: 'Replace bookings with demo content?',
      bodyText:
        'Replace current booking data with demo content? Existing locations, services, staff, customers, and appointments will be cleared first. Notification templates are kept.',
    }))
  ) {
    return
  }
  busy.value = 'demo'
  clearStatus()
  try {
    const res = await api('admin/database/demo', {
      method: 'POST',
      body: {},
      toast: { working: 'Inserting demo content…', success: 'Demo content inserted successfully.' },
    })
    message.value =
      'Demo content inserted: ' +
      (res.appointments || 0) +
      ' appointments, ' +
      (res.services || 0) +
      ' services, ' +
      (res.categories || 0) +
      ' categories, ' +
      (res.staff || 0) +
      ' staff, ' +
      (res.locations || 0) +
      ' locations, ' +
      (res.extras || 0) +
      ' extras, ' +
      (res.coupons || 0) +
      ' coupons, ' +
      (res.custom_fields || 0) +
      ' custom fields.'
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = ''
  }
}

async function exportCsv() {
  busy.value = 'export'
  clearStatus()
  try {
    const res = await api('admin/database/export', {
      method: 'POST',
      body: {},
      toast: { working: 'Preparing your export…', success: 'Export ready to download.' },
    })
    const blob = new Blob([res.csv || ''], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = res.filename || 'praxleo-booking.csv'
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
    message.value = 'Export downloaded: ' + (res.filename || 'praxleo-booking.csv')
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = ''
  }
}

function onFile(ev) {
  importFile.value = ev.target.files && ev.target.files[0] ? ev.target.files[0] : null
}

function summarizeCounts(counts) {
  const keys = [
    'locations',
    'service_categories',
    'services',
    'staff',
    'customers',
    'appointments',
    'extras',
    'coupons',
    'custom_fields',
    'notifications',
  ]
  return keys
    .filter((k) => counts[k])
    .map((k) => counts[k] + ' ' + k.replace(/_/g, ' '))
    .join(', ')
}

async function importCsv() {
  if (!importFile.value) {
    error.value = 'Choose a CSV file first.'
    return
  }
  if (
    !(await confirmDelete({
      heading: 'Replace data from CSV?',
      bodyText:
        'Import this CSV? Tables present in the file will replace existing rows. Use an export from Appointment Pro.',
    }))
  ) {
    return
  }
  busy.value = 'import'
  clearStatus()
  try {
    const csv = await importFile.value.text()
    const res = await api('admin/database/import', {
      method: 'POST',
      body: { csv },
      toast: { working: 'Importing booking data…', success: 'Booking data imported successfully.' },
    })
    message.value = 'Import complete' + (res.counts ? ': ' + summarizeCounts(res.counts) : '.')
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = ''
  }
}
useToastFeedback(error)
</script>
<template>
  <DeleteConfirmation
    v-bind="confirmation"
    @confirm="resolveDelete(true)"
    @cancel="resolveDelete(false)"
  />
  <p v-if="error" class="px-error">{{ error }}</p>
  <p v-if="message" class="px-success">{{ message }}</p>

  <div class="px-dynamic-grid" style="--min-width: 300px">

    <div class="px-grid-item" style="--grid-item-index: 0">
      <h3 class="mt-0">01. Reset Database</h3>
      <div class="px-grid-body">
        <p class="px-muted mt-0">
          Removes all booking data (locations, services, categories, staff, customers, appointments,
          extras, coupons, custom fields). Notification templates are not deleted.
        </p>
      </div>
      <div class="px-grid-footer">
        <button class="px-button px-button-danger" :disabled="!!busy" @click="resetDb">
          {{ busy === 'reset' ? 'Resetting...' : 'Reset Database' }}
        </button>
      </div>
    </div>
    <div class="px-grid-item" style="--grid-item-index: 1">
      <h3 class="mt-0">02. Insert Demo Content</h3>
      <div class="px-grid-body">
        <p class="px-muted mt-0">
          Clears booking data, then inserts 3 locations, 5 categories, 10 services, 3 staff (with
          hours and service links), 3 extras, 2 coupons (SAVE10, WELCOME20), 3 custom fields, 5
          customers, and 5 appointments.
        </p>
      </div>
      <div class="px-grid-footer">
        <button class="px-button" :disabled="!!busy" @click="insertDemo">
          {{ busy === 'demo' ? 'Inserting...' : 'Insert Demo Content' }}
        </button>
      </div>
    </div>
    <div class="px-grid-item" style="--grid-item-index: 2">
      <h3 class="mt-0">03. Export CSV</h3>
      <div class="px-grid-body">
        <p class="px-muted mt-0">
          Download all booking tables as one CSV so you can move data to another WordPress site. IDs
          and links between staff, services, and appointments are kept.
        </p>
      </div>
      <div class="px-grid-footer">
        <button class="px-button" :disabled="!!busy" @click="exportCsv">
          {{ busy === 'export' ? 'Exporting...' : 'Export CSV' }}
        </button>
      </div>
    </div>
    <div class="px-grid-item" style="--grid-item-index: 3">
      <h3 class="mt-0">04. Import CSV</h3>
      <div class="px-grid-body">
        <p class="px-muted mt-0">
          Upload a Appointment Pro export. Tables in the file replace current rows. Notification
          templates are imported when present; missing events are seeded after import.
        </p>

        <PxInput type="file" accept=".csv,text/csv" :disabled="!!busy" @change="onFile" />
      </div>
      <div class="px-grid-footer">
        <button class="px-button" :disabled="!!busy || !importFile" @click="importCsv">
          {{ busy === 'import' ? 'Importing...' : 'Import CSV' }}
        </button>
      </div>
    </div>
    <div class="px-grid-item px-cache-card" style="--grid-item-index: 4">
      <h3 class="mt-0">05. Admin cache</h3>
      <div class="px-grid-body">
        <p class="px-muted mt-0">
          Speed up repeat visits to admin pages. Creating, editing or deleting records refreshes
          cached data automatically.
        </p>
        <label class="px-cache-toggle">
          <input
            type="checkbox"
            :checked="adminCacheState.enabled"
            :disabled="cacheLoading || !!busy"
            @change="changeCache"
          />
          Enable cache
        </label>
        <p class="px-muted">
          {{
            cacheLoading
              ? 'Loading cache settings…'
              : adminCacheState.enabled
                ? 'Admin cache is enabled.'
                : 'Admin cache is disabled.'
          }}
        </p>
      </div>
      <div class="px-grid-footer">
        <button
          type="button"
          class="px-button"
          :disabled="cacheLoading || !!busy"
          @click="purgeCache"
        >
          {{ busy === 'purge' ? 'Purging…' : 'Purge cache' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.px-cache-card .px-grid-body {
  max-height: none;
}
.px-cache-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}
.px-cache-toggle input {
  margin: 0;
}
</style>
