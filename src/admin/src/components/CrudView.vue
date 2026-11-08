<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'

const props = defineProps({
  path: { type: String, required: true },
  allowDelete: { type: Boolean, default: true },
  allowAdd: { type: Boolean, default: true },
  fields: { type: Array, required: true },
})

const selected = ref([])
async function bulkDelete() {
  if (!selected.value.length || !window.confirm('Delete selected records?')) return
  try {
    await api('admin/' + props.path + '/bulk-delete', {
      method: 'POST',
      body: { ids: selected.value },
    })
    selected.value = []
    await load()
  } catch (e) {
    error.value = e.message
  }
}
const rows = ref([])
const form = ref({})
const editing = ref(null)
const lookups = ref({})
const error = ref('')
const saving = ref(false)

const visibleFields = computed(() => props.fields.filter((field) => !field.hideInForm))

async function loadLookups() {
  for (const field of props.fields) {
    if (field.type === 'select' && field.optionsPath) {
      lookups.value[field.key] = await api(field.optionsPath)
    }
  }
}

function optionsFor(field) {
  if (field.options) {
    return field.options
  }

  const list = lookups.value[field.key] || []

  return list.map((item) => ({
    value: item[field.optionValue || 'id'],
    label: item[field.optionLabel || 'name'],
  }))
}

function optionLabel(field, value) {
  const found = optionsFor(field).find((option) => String(option.value) === String(value))

  return found ? found.label : value
}

async function load() {
  try {
    rows.value = await api('admin/' + props.path)
  } catch (e) {
    error.value = e.message
  }
}

function start() {
  const blank = {}

  for (const field of visibleFields.value) {
    blank[field.key] = field.defaultValue ?? (field.type === 'bool' ? 1 : '')
  }

  form.value = blank
  editing.value = 'new'
  error.value = ''
}

function edit(row) {
  form.value = { ...row }
  editing.value = row.id
  error.value = ''
}

async function save() {
  saving.value = true
  error.value = ''

  try {
    if (editing.value === 'new') {
      await api('admin/' + props.path, { method: 'POST', body: form.value })
    } else {
      await api('admin/' + props.path + '/' + editing.value, {
        method: 'PUT',
        body: form.value,
      })
    }

    editing.value = null
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function remove(id) {
  if (!window.confirm('Delete this record?')) {
    return
  }

  try {
    await api('admin/' + props.path + '/' + id, { method: 'DELETE' })
    await load()
  } catch (e) {
    error.value = e.message
  }
}

onMounted(async () => {
  await loadLookups()
  await load()
})
</script>

<template>
  <div class="px-toolbar">
    <button
      v-if="allowDelete"
      class="px-button px-button-danger"
      :disabled="!selected.length"
      @click="bulkDelete"
    >
      Delete selected
    </button>
    <button v-if="allowAdd" class="px-button" type="button" @click="start">Add</button>
  </div>

  <p v-if="error" class="px-error">{{ error }}</p>

  <div v-if="editing" class="px-card">
    <form class="px-form" @submit.prevent="save">
      <div class="px-form-row">
        <div v-for="field in visibleFields" :key="field.key" class="px-field">
          <label>{{ field.label }}</label>

          <textarea v-if="field.type === 'textarea'" v-model="form[field.key]" rows="4"></textarea>

          <select v-else-if="field.type === 'bool'" v-model.number="form[field.key]">
            <option :value="1">Yes</option>
            <option :value="0">No</option>
          </select>

          <select v-else-if="field.type === 'select'" v-model="form[field.key]">
            <option value="">{{ field.placeholder || 'Select ' + field.label }}</option>
            <option v-for="option in optionsFor(field)" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>

          <input v-else v-model="form[field.key]" :type="field.type || 'text'" :step="field.step" />
        </div>
      </div>

      <div class="px-actions">
        <button class="px-button" type="submit" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save' }}
        </button>
        <button class="px-button px-button-ghost" type="button" @click="editing = null">
          Cancel
        </button>
      </div>
    </form>
  </div>

  <div class="px-card">
    <table class="px-table">
      <thead>
        <tr>
          <th v-if="allowDelete">
            <input
              type="checkbox"
              aria-label="Select all"
              @change="selected = $event.target.checked ? rows.map((r) => r.id) : []"
            />
          </th>
          <th v-for="field in fields" :key="field.key">{{ field.label }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td v-if="allowDelete">
            <input v-model="selected" type="checkbox" :value="row.id" aria-label="Select record" />
          </td>
          <td v-for="field in fields" :key="field.key">
            <template v-if="field.type === 'select'">
              {{ optionLabel(field, row[field.key]) }}
            </template>
            <template v-else>{{ row[field.key] }}</template>
          </td>
          <td>
            <button class="px-button-outline approve" type="button" @click="edit(row)">Edit</button>
            <button
              v-if="allowDelete"
              class="px-button-outline cancel"
              type="button"
              @click="remove(row.id)"
            >
              Delete
            </button>
          </td>
        </tr>
        <tr v-if="!rows.length">
          <td :colspan="fields.length + 1" class="px-muted">No records yet.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
