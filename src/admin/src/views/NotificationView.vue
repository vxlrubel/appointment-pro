<script setup>
import PxModal from '../components/PxModal.vue'
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'
const audience = ref('admin'),
  rows = ref([]),
  editing = ref(null),
  loading = ref(true),
  saving = ref(false),
  error = ref(''),
  message = ref(''),
  preferences = ref({ notification_customer_email: '', notification_customer_admin_copy: false })
const visibleRows = computed(() =>
  rows.value.filter((row) => row.event.endsWith('_' + audience.value)),
)
function plainText(html) {
  const parsed = new DOMParser().parseFromString(html || '', 'text/html')
  parsed.querySelectorAll('script,style').forEach((node) => node.remove())
  parsed
    .querySelectorAll('p,div,br,li,h1,h2,h3,h4')
    .forEach((node) => node.insertAdjacentText('afterend', ' '))
  return (parsed.body.textContent || '').replace(/\s+/g, ' ').trim()
}
function preview(html) {
  return Array.from(plainText(html)).slice(0, 30).join('')
}
async function load() {
  try {
    const [templates, settings] = await Promise.all([api('admin/notifications'), api('settings')])
    rows.value = templates
    preferences.value = {
      notification_customer_email: settings.notification_customer_email || '',
      notification_customer_admin_copy: !!settings.notification_customer_admin_copy,
    }
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
function tab(value) {
  audience.value = value
  editing.value = null
  error.value = ''
  message.value = ''
}
async function save() {
  saving.value = true
  error.value = ''
  try {
    const row = await api('admin/notifications/' + editing.value.id, {
      method: 'PUT',
      body: {
        subject: editing.value.subject,
        body_html: editing.value.body_html,
        enabled: Number(editing.value.enabled),
      },
    })
    rows.value = rows.value.map((item) => (item.id === row.id ? row : item))
    editing.value = null
    message.value = 'Notification saved.'
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
async function saveRecipients() {
  saving.value = true
  error.value = ''
  try {
    await api('settings', { method: 'POST', body: preferences.value })
    message.value = 'Customer email preferences saved.'
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
onMounted(load)
</script>
<template>
  <div class="px-notification-tabs" role="tablist" aria-label="Notification recipients">
    <button
      v-for="item in ['admin', 'staff', 'customer']"
      :key="item"
      :id="'tab-' + item"
      role="tab"
      :aria-selected="audience === item"
      :aria-controls="'panel-' + item"
      :class="{ active: audience === item }"
      @click="tab(item)"
    >
      {{ item.charAt(0).toUpperCase() + item.slice(1) }}
    </button>
  </div>
  <p v-if="error" class="px-error" role="alert">{{ error }}</p>
  <p v-if="message" class="px-success" role="status">{{ message }}</p>
  <LoadingSkeleton v-if="loading" label="Loading notifications" />
  <section v-else role="tabpanel" :id="'panel-' + audience" :aria-labelledby="'tab-' + audience">
    <form v-if="audience === 'customer'" class="px-card px-form" @submit.prevent="saveRecipients">
      <h3 class="mt-0">Customer email delivery</h3>
      <div class="px-form-row">
        <div class="px-field">
          <label for="customer-custom-email">Custom email</label
          ><input
            id="customer-custom-email"
            v-model="preferences.notification_customer_email"
            type="email"
            placeholder="Additional recipient (optional)"
          /><small class="px-muted"
            >Receives an additional copy of enabled customer notifications.</small
          >
        </div>
        <div class="px-field">
          <label for="customer-admin-copy">Also send customer notifications to admin</label
          ><select id="customer-admin-copy" v-model="preferences.notification_customer_admin_copy">
            <option :value="false">Disabled</option>
            <option :value="true">Enabled</option>
          </select>
        </div>
      </div>
      <button class="px-button" :disabled="saving">Save email preferences</button>
    </form>
    <PxModal
      :open="!!editing"
      :heading="'Edit notification'"
      :busy="saving"
      form-id="notification-form"
      primary-text="Save notification"
      @close="editing = null"
      ><p v-if="error" class="px-error" role="alert">{{ error }}</p>
      <form v-if="editing" id="notification-form" class="px-card px-form" @submit.prevent="save">
        <h3 class="mt-0">Edit {{ editing.event }}</h3>
        <div class="px-field">
          <label for="notification-subject">Subject</label
          ><input id="notification-subject" v-model="editing.subject" required />
        </div>
        <div class="px-field">
          <label for="notification-body">Full message (HTML supported)</label
          ><textarea
            id="notification-body"
            v-model="editing.body_html"
            rows="10"
            required
          ></textarea>
        </div>
        <div class="px-field">
          <label for="notification-enabled">Enabled</label
          ><select id="notification-enabled" v-model.number="editing.enabled">
            <option :value="1">Yes</option>
            <option :value="0">No</option>
          </select>
        </div>
      </form>
    </PxModal>
    <div class="px-card">
      <table class="px-table">
        <thead>
          <tr class="px-table-row" style="--px-table-row-index: 0">
            <th>Event</th>
            <th>Subject</th>
            <th>Body</th>
            <th>Enabled</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in visibleRows"
            :key="row.id"
            class="px-table-row"
            :style="{ '--px-table-row-index': index + 1 }"
          >
            <td>{{ row.event }}</td>
            <td>{{ row.subject }}</td>
            <td class="px-notification-preview">{{ preview(row.body_html) }}</td>
            <td>{{ Number(row.enabled) ? 'Yes' : 'No' }}</td>
            <td>
              <button
                class="px-button-outline approve"
                :disabled="saving"
                @click="editing = { ...row }"
              >
                Edit
              </button>
            </td>
          </tr>
          <tr v-if="!visibleRows.length" class="px-table-row" style="--px-table-row-index: 1">
            <td colspan="5">No notifications for this recipient.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
<style scoped>
.px-notification-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 18px;
  border-bottom: 1px solid var(--px-line);
  padding-bottom: 12px;
}
.px-notification-tabs button {
  padding: 10px 20px;
  border: 1px solid var(--px-line);
  border-radius: 7px;
  background: white;
  color: var(--px-color);
  cursor: pointer;
  font-weight: 600;
}
.px-notification-tabs button.active {
  background: var(--px-primary);
  border-color: var(--px-primary);
  color: white;
}
.px-notification-preview {
  max-width: 260px;
  overflow-wrap: anywhere;
}
</style>
