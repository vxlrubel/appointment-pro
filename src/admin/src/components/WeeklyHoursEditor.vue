<script setup>
import { ref } from 'vue'
import PxModal from './PxModal.vue'
const props = defineProps({
  modelValue: { type: Array, required: true },
  locations: { type: Array, default: () => [] },
  disabled: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
const editing = ref(null),
  form = ref(null),
  error = ref('')
function edit(index = -1) {
  editing.value = index
  error.value = ''
  form.value =
    index < 0
      ? { weekday: 1, location_id: null, start_time: '09:00', end_time: '17:00', breaks: [] }
      : JSON.parse(JSON.stringify(props.modelValue[index]))
  form.value.breaks ||= []
  form.value.start_time = form.value.start_time.slice(0, 5)
  form.value.end_time = form.value.end_time.slice(0, 5)
  form.value.breaks.forEach((pause) => {
    pause.start_time = pause.start_time.slice(0, 5)
    pause.end_time = pause.end_time.slice(0, 5)
  })
}
function save() {
  const row = form.value
  if (
    row.start_time >= row.end_time ||
    row.breaks.some(
      (p) =>
        !p.start_time ||
        !p.end_time ||
        p.start_time >= p.end_time ||
        p.start_time < row.start_time ||
        p.end_time > row.end_time,
    )
  ) {
    error.value = 'Opening must precede closing. Breaks must fit within the shift.'
    return
  }
  const rows = [...props.modelValue]
  if (editing.value < 0) rows.push({ ...row })
  else rows[editing.value] = { ...row }
  emit('update:modelValue', rows)
  form.value = null
}
</script>
<template>
  <div class="px-weekly-hours">
    <p v-if="!modelValue.length" class="px-muted">No weekly hours configured.</p>
    <div v-for="(shift, index) in modelValue" :key="index" class="px-row">
      <strong>{{ weekdays[shift.weekday] }}</strong
      ><span>{{ shift.start_time }} &ndash; {{ shift.end_time }}</span
      ><span>{{
        locations.find((item) => Number(item.id) === Number(shift.location_id))?.name ||
        'All locations'
      }}</span
      ><span>{{ (shift.breaks || []).length }} breaks</span
      ><button
        type="button"
        class="px-button-outline approve"
        :disabled="disabled"
        @click="edit(index)"
      >
        Edit shift</button
      ><button
        type="button"
        class="px-button-outline cancel"
        :disabled="disabled"
        @click="
          emit(
            'update:modelValue',
            modelValue.filter((_, i) => i !== index),
          )
        "
      >
        Remove shift
      </button>
    </div>
    <button type="button" class="px-button px-button-ghost" :disabled="disabled" @click="edit()">
      Add shift
    </button>
  </div>
  <PxModal
    :open="!!form"
    heading="Weekly shift"
    form-id="weekly-shift-form"
    primary-text="Save shift"
    @close="form = null"
    ><form v-if="form" id="weekly-shift-form" class="px-form" @submit.prevent="save">
      <p v-if="error" class="px-error" role="alert">{{ error }}</p>
      <div class="px-form-row">
        <div class="px-field">
          <label for="shift-day">Day</label
          ><PxSelect id="shift-day" v-model.number="form.weekday">
            <option v-for="(day, index) in weekdays" :key="index" :value="index">{{ day }}</option>
          </PxSelect>
        </div>
        <div class="px-field">
          <label for="shift-location">Location</label
          ><PxSelect id="shift-location" v-model.number="form.location_id">
            <option :value="null">All locations</option>
            <option v-for="location in locations" :key="location.id" :value="location.id">
              {{ location.name }}
            </option>
          </PxSelect>
        </div>
        <div class="px-field">
          <label for="shift-start">Opens</label
          ><input id="shift-start" v-model="form.start_time" type="time" required />
        </div>
        <div class="px-field">
          <label for="shift-end">Closes</label
          ><input id="shift-end" v-model="form.end_time" type="time" required />
        </div>
      </div>
      <h3>Breaks</h3>
      <div v-for="(pause, index) in form.breaks" :key="index" class="px-row">
        <input v-model="pause.start_time" type="time" required aria-label="Break starts" /><input
          v-model="pause.end_time"
          type="time"
          required
          aria-label="Break ends"
        /><button
          type="button"
          class="px-button-outline cancel"
          @click="form.breaks.splice(index, 1)"
        >
          Remove break
        </button>
      </div>
      <button
        type="button"
        class="px-button px-button-ghost"
        @click="form.breaks.push({ start_time: '12:00', end_time: '13:00' })"
      >
        Add break
      </button>
    </form></PxModal
  >
</template>
