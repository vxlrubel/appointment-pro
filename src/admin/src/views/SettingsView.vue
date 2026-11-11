<script setup>
import { onMounted, ref, inject } from 'vue'
import { api } from '../api'
import LoadingSkeleton from '../components/LoadingSkeleton.vue'
import { currencies } from '../../../shared/currency'
const setCurrency = inject('pxSetCurrency', () => {})
const loading = ref(true),
  form = ref({}),
  error = ref(''),
  saved = ref(false),
  saving = ref(false)
const gateways = [
  {
    key: 'stripe',
    name: 'Stripe',
    fields: [
      { key: 'publishable', label: 'Publishable key', type: 'text' },
      { key: 'secret', label: 'Secret key', type: 'password' },
      { key: 'webhook_secret', label: 'Webhook signing secret', type: 'password' },
    ],
  },
  {
    key: 'paypal',
    name: 'PayPal',
    fields: [
      { key: 'client_id', label: 'Client ID', type: 'text' },
      { key: 'secret', label: 'Secret', type: 'password' },
      { key: 'webhook_id', label: 'Webhook ID', type: 'text' },
    ],
  },
]
function credentialKey(gateway, field) {
  return gateway.key + (form.value[gateway.key + '_sandbox'] ? '_sandbox_' : '_') + field.key
}
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
    setCurrency(form.value.currency)
    saved.value = true
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>
<template>
  <p v-if="error" class="px-error" role="alert">{{ error }}</p>
  <p v-if="saved" class="px-success" role="status">Settings saved.</p>
  <LoadingSkeleton v-if="loading" variant="form" label="Loading settings" />
  <form v-else @submit.prevent="save">
    <fieldset class="px-fieldset px-anim-fadein" style="--px-table-row-index: 0">
      <legend>Company Settings</legend>
      <div class="px-field-row">
        <div
          v-for="field in [
            { key: 'company_name', label: 'Company name' },
            { key: 'company_email', label: 'Company email', type: 'email' },
            { key: 'timezone', label: 'Timezone' },
          ]"
          :key="field.key"
          class="px-field"
        >
          <label :for="field.key">{{ field.label }}</label
          ><input :id="field.key" v-model="form[field.key]" :type="field.type || 'text'" />
        </div>
        <div class="px-field">
          <label for="currency">Currency</label
          ><PxSelect id="currency" v-model="form.currency">
            <option
              v-if="form.currency && !currencies.some((item) => item.code === form.currency)"
              :value="form.currency"
            >
              {{ form.currency }}
            </option>
            <option
              v-for="item in currencies"
              :key="item.code"
              :value="item.code"
              :data-symbol="item.symbol"
            >
              {{ item.code }} ({{ item.symbol }})
            </option>
          </PxSelect>
        </div>
        <div class="px-field">
          <label for="slot-length">Slot length (min)</label
          ><input id="slot-length" v-model.number="form.slot_length" type="number" min="5" />
        </div>
        <div class="px-field">
          <label for="auto-approve">Auto-approve</label
          ><PxSelect id="auto-approve" v-model="form.auto_approve">
            <option :value="true">Yes</option>
            <option :value="false">No</option>
          </PxSelect>
        </div>
      </div>
    </fieldset>
    <fieldset class="px-fieldset px-anim-fadein" style="--px-table-row-index: 1">
      <legend>Rules &amp; Reminders</legend>
      <div class="px-field-row">
        <div
          v-for="field in [
            { key: 'hold_ttl', label: 'Payment hold (seconds)' },
            { key: 'min_lead_hours', label: 'Minimum notice (hours)' },
            { key: 'max_lead_days', label: 'Book ahead (days)' },
            { key: 'reminder_hours', label: 'Reminder before appointment (hours)' },
          ]"
          :key="field.key"
          class="px-field"
        >
          <label :for="field.key">{{ field.label }}</label
          ><input :id="field.key" v-model.number="form[field.key]" type="number" min="0" />
        </div>
        <div class="px-field">
          <label for="uninstall-data">Delete data on uninstall</label
          ><PxSelect id="uninstall-data" v-model="form.delete_data_on_uninstall">
            <option :value="false">Keep data</option>
            <option :value="true">Delete data</option>
          </PxSelect>
        </div>
      </div>
    </fieldset>
    <fieldset class="px-fieldset px-anim-fadein" style="--px-table-row-index: 2">
      <legend>Payment Gateways</legend>
      <div class="px-field-row">
        <div
          v-for="gateway in [
            { key: 'local', name: 'Local payments' },
            { key: 'woocommerce', name: 'WooCommerce' },
          ]"
          :key="gateway.key"
          class="px-field"
        >
          <label :for="gateway.key + '-enabled'">{{ gateway.name }}</label
          ><PxSelect :id="gateway.key + '-enabled'" v-model="form[gateway.key + '_enabled']">
            <option :value="true">On</option>
            <option :value="false">Off</option>
          </PxSelect>
        </div>
      </div>
      <div class="px-gateway-grid">
        <section
          v-for="gateway in gateways"
          :key="gateway.key"
          class="px-card px-gateway-panel"
          :aria-label="gateway.name + ' settings'"
        >
          <h3>{{ gateway.name }}</h3>
          <div class="px-field">
            <label :for="gateway.key + '-enabled'">{{ gateway.name }} enabled</label
            ><PxSelect :id="gateway.key + '-enabled'" v-model="form[gateway.key + '_enabled']">
              <option :value="true">On</option>
              <option :value="false">Off</option>
            </PxSelect>
          </div>
          <template v-if="form[gateway.key + '_enabled']"
            ><div class="px-field">
              <label :for="gateway.key + '-sandbox'">{{ gateway.name }} sandbox</label
              ><PxSelect :id="gateway.key + '-sandbox'" v-model="form[gateway.key + '_sandbox']">
                <option :value="true">On — Sandbox</option>
                <option :value="false">Off — Live</option>
              </PxSelect>
            </div>
            <p class="px-muted">
              {{
                form[gateway.key + '_sandbox']
                  ? 'Sandbox credentials for test payments.'
                  : 'Live credentials for real payments.'
              }}
              Sandbox and live values are saved separately.
            </p>
            <div
              v-for="field in gateway.fields"
              :key="credentialKey(gateway, field)"
              class="px-field"
            >
              <label :for="credentialKey(gateway, field)"
                >{{ form[gateway.key + '_sandbox'] ? 'Sandbox' : 'Live' }} {{ gateway.name }}
                {{ field.label }}</label
              ><input
                :id="credentialKey(gateway, field)"
                v-model="form[credentialKey(gateway, field)]"
                :type="field.type"
                autocomplete="off"
              />
            </div>
          </template>
        </section>
      </div>
    </fieldset>
    <div class="px-actions">
      <button class="px-button" type="submit" :disabled="saving">
        {{ saving ? 'Saving...' : 'Save settings' }}
      </button>
    </div>
  </form>
</template>
<style scoped>
.px-gateway-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 18px;
  margin-top: 20px;
}
.px-gateway-panel {
  margin-bottom: 0;
}
.px-gateway-panel h3 {
  margin-top: 0;
}
.px-gateway-panel .px-field {
  margin-bottom: 14px;
}
</style>
