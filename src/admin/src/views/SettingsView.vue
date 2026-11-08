<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'

const loading = ref(true)

const form = ref({})
const error = ref('')
const saved = ref(false)
const saving = ref(false)

onMounted(async () => {
  try {
    form.value = await api('settings')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  error.value = ''
  saved.value = false

  try {
    form.value = await api('settings', { method: 'POST', body: form.value })
    saved.value = true
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <p v-if="error" class="px-error">{{ error }}</p>
  <p v-if="saved" class="px-muted">Settings saved.</p>

  <LoadingSkeleton v-if="loading" variant="form" :columns="4" label="Loading settings" />
  <form v-else class="px-form px-card" @submit.prevent="save">
    <div class="px-form-row">
      <div class="px-field">
        <label>Company name</label>
        <input v-model="form.company_name" />
      </div>
      <div class="px-field">
        <label>Company email</label>
        <input v-model="form.company_email" type="email" />
      </div>
      <div class="px-field">
        <label>Timezone</label>
        <input v-model="form.timezone" />
      </div>
      <div class="px-field">
        <label>Slot length (min)</label>
        <input v-model.number="form.slot_length" type="number" min="5" />
      </div>
      <div class="px-field">
        <label>Currency</label>
        <input v-model="form.currency" />
      </div>
      <div class="px-field">
        <label>Auto-approve</label>
        <select v-model="form.auto_approve">
          <option :value="true">Yes</option>
          <option :value="false">No</option>
        </select>
      </div>
    </div>

    <h3>Booking rules and reminders</h3>
    <div class="px-form-row">
      <div
        class="px-field"
        v-for="field in [
          { key: 'hold_ttl', label: 'Payment hold (seconds)' },
          { key: 'min_lead_hours', label: 'Minimum notice (hours)' },
          { key: 'max_lead_days', label: 'Book ahead (days)' },
          { key: 'reminder_hours', label: 'Reminder before appointment (hours)' },
        ]"
        :key="field.key"
      >
        <label>{{ field.label }}</label
        ><input v-model.number="form[field.key]" type="number" min="0" />
      </div>
      <div class="px-field">
        <label>PayPal sandbox</label
        ><select v-model="form.paypal_sandbox">
          <option :value="true">On</option>
          <option :value="false">Off</option>
        </select>
      </div>
      <div class="px-field">
        <label>Delete data on uninstall</label
        ><select v-model="form.delete_data_on_uninstall">
          <option :value="false">Keep data</option>
          <option :value="true">Delete data</option>
        </select>
      </div>
      <div class="px-field">
        <label>Stripe webhook signing secret</label
        ><input v-model="form.stripe_webhook_secret" type="password" />
      </div>
    </div>
    <div class="px-field">
      <label>PayPal webhook ID</label><input v-model="form.paypal_webhook_id" />
    </div>
    <h3>Payments</h3>

    <div class="px-form-row">
      <div class="px-field">
        <label>Local payments</label>
        <select v-model="form.local_enabled">
          <option :value="true">On</option>
          <option :value="false">Off</option>
        </select>
      </div>
      <div class="px-field">
        <label>Stripe enabled</label>
        <select v-model="form.stripe_enabled">
          <option :value="true">On</option>
          <option :value="false">Off</option>
        </select>
      </div>
      <div class="px-field">
        <label>Stripe publishable key</label>
        <input v-model="form.stripe_publishable" />
      </div>
      <div class="px-field">
        <label>Stripe secret key</label>
        <input v-model="form.stripe_secret" type="password" />
      </div>
      <div class="px-field">
        <label>PayPal enabled</label>
        <select v-model="form.paypal_enabled">
          <option :value="true">On</option>
          <option :value="false">Off</option>
        </select>
      </div>
      <div class="px-field">
        <label>PayPal client ID</label>
        <input v-model="form.paypal_client_id" />
      </div>
      <div class="px-field">
        <label>PayPal secret</label>
        <input v-model="form.paypal_secret" type="password" />
      </div>
      <div class="px-field">
        <label>WooCommerce</label>
        <select v-model="form.woocommerce_enabled">
          <option :value="true">On</option>
          <option :value="false">Off</option>
        </select>
      </div>
    </div>

    <div class="px-actions">
      <button class="px-button" type="submit" :disabled="saving">
        {{ saving ? 'Saving...' : 'Save settings' }}
      </button>
    </div>
  </form>
</template>
