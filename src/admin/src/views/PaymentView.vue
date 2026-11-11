<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'
import DeleteConfirmation from '../components/DeleteConfirmation.vue'
import { useDeleteConfirmation } from '../composables/useDeleteConfirmation'

const { confirmation, confirmDelete, resolveDelete } = useDeleteConfirmation()
const loading = ref(true)
const deleting = ref(false)
const rows = ref([])
const selected = ref([])
const error = ref('')
const allSelected = computed(
  () => rows.value.length > 0 && rows.value.every((row) => selected.value.includes(row.id)),
)
const someSelected = computed(() => selected.value.length > 0 && !allSelected.value)

async function load() {
  loading.value = true
  try {
    rows.value = await api('payments')
    selected.value = selected.value.filter((id) => rows.value.some((row) => row.id === id))
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function bulkDelete() {
  if (loading.value || deleting.value || !selected.value.length) return
  const ids = [...selected.value]
  if (
    !(await confirmDelete({
      heading: 'Delete selected payments?',
      bodyText: `Permanently delete ${ids.length} selected payment record${ids.length === 1 ? '' : 's'}? This cannot be undone.`,
    }))
  )
    return
  deleting.value = true
  error.value = ''
  try {
    await api('payments/bulk-delete', { method: 'POST', body: { ids } })
    selected.value = []
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    deleting.value = false
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
      type="button"
      :disabled="loading || deleting || !selected.length"
      @click="bulkDelete"
    >
      {{ deleting ? 'Deleting...' : 'Delete selected' }}
    </button>
    <span v-if="selected.length" class="px-muted">{{ selected.length }} selected</span>
  </div>
  <p v-if="error" class="px-error" role="alert">{{ error }}</p>
  <LoadingSkeleton v-if="loading" variant="table" :columns="6" label="Loading payments" />
  <div v-else class="px-card" :aria-busy="deleting">
    <table class="px-table">
      <thead>
        <tr class="px-table-row" style="--px-table-row-index: 0">
          <th>
            <input
              type="checkbox"
              aria-label="Select all payments"
              :checked="allSelected"
              :indeterminate="someSelected"
              :disabled="deleting || !rows.length"
              @change="selected = $event.target.checked ? rows.map((row) => row.id) : []"
            />
          </th>
          <th>ID</th>
          <th>Type</th>
          <th>Status</th>
          <th>Amount</th>
          <th>When</th>
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
            <input
              v-model="selected"
              type="checkbox"
              :value="row.id"
              :aria-label="'Select payment ' + row.id"
              :disabled="deleting"
            />
          </td>
          <td>{{ row.id }}</td>
          <td>{{ row.type }}</td>
          <td>{{ row.status }}</td>
          <td><PxCurrency :amount="row.amount" :currency="row.currency" /></td>
          <td>{{ row.created_at }}</td>
        </tr>
        <tr v-if="!rows.length" class="px-table-row" style="--px-table-row-index: 1">
          <td colspan="6" class="px-muted">No payments yet.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
