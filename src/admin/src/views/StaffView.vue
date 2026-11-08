<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const rows = ref([])
const form = ref({})
const editing = ref(null)
const schedule = ref([])
const services = ref([])
const locations = ref([])
const allServices = ref([])
const error = ref('')
const saving = ref(false)

const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

async function load() {
	try {
		rows.value = await api('admin/staff')
		locations.value = await api('admin/locations')
		allServices.value = await api('admin/services')
	} catch (e) {
		error.value = e.message
	}
}

function start() {
	form.value = { full_name: '', email: '', phone: '', enabled: 1 }
	editing.value = 'new'
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
	try {
		schedule.value = await api('staff/' + id + '/schedule')
		services.value = await api('staff/' + id + '/services')
	} catch (e) {
		error.value = e.message
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
	try {
		schedule.value = await api('staff/' + editing.value + '/schedule', {
			method: 'POST',
			body: schedule.value,
		})
	} catch (e) {
		error.value = e.message
	}
}

function addShift() {
	schedule.value.push({
		weekday: 1,
		start_time: '09:00:00',
		end_time: '17:00:00',
		location_id: null,
	})
}

function removeShift(index) {
	schedule.value.splice(index, 1)
}

async function saveServices() {
	try {
		services.value = await api('staff/' + editing.value + '/services', {
			method: 'POST',
			body: services.value,
		})
	} catch (e) {
		error.value = e.message
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
	<div class="px-toolbar">
		<button class="px-button" type="button" @click="start">Add</button>
	</div>

	<p v-if="error" class="px-error">{{ error }}</p>

	<div v-if="editing" class="px-card">
		<form class="px-form" @submit.prevent="save">
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

			<div class="px-actions">
				<button class="px-button" type="submit" :disabled="saving">
					{{ saving ? 'Saving...' : 'Save staff' }}
				</button>
				<button class="px-button px-button-ghost" type="button" @click="editing = null">
					Close
				</button>
			</div>
		</form>

		<div v-if="editing !== 'new'">
			<h3>Weekly hours</h3>

			<div v-for="(shift, index) in schedule" :key="'shift-' + index" class="px-row">
				<select v-model.number="shift.weekday">
					<option v-for="day in weekdays" :key="day" :value="weekdays.indexOf(day)">
						{{ day }}
					</option>
				</select>
				<input v-model="shift.start_time" type="time" />
				<input v-model="shift.end_time" type="time" />
				<select v-model.number="shift.location_id">
					<option :value="null">All locations</option>
					<option v-for="location in locations" :key="location.id" :value="location.id">
						{{ location.name }}
					</option>
				</select>
				<button class="px-button px-button-danger" type="button" @click="removeShift(index)">
					Remove
				</button>
			</div>

			<div class="px-actions">
				<button class="px-button px-button-ghost" type="button" @click="addShift">
					Add shift
				</button>
				<button class="px-button" type="button" @click="saveSchedule">Save hours</button>
			</div>

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
				<button class="px-button px-button-danger" type="button" @click="removeServiceLink(index)">
					Remove
				</button>
			</div>

			<div class="px-actions">
				<button class="px-button px-button-ghost" type="button" @click="addServiceLink">
					Add service
				</button>
				<button class="px-button" type="button" @click="saveServices">Save services</button>
			</div>
		</div>
	</div>

	<div class="px-card">
		<table class="px-table">
			<thead>
				<tr>
					<th>Name</th>
					<th>Email</th>
					<th>Phone</th>
					<th>Enabled</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in rows" :key="row.id">
					<td>{{ row.full_name }}</td>
					<td>{{ row.email }}</td>
					<td>{{ row.phone }}</td>
					<td>{{ row.enabled ? 'Yes' : 'No' }}</td>
					<td>
						<button class="px-button-outline approve" type="button" @click="edit(row)">
							Edit
						</button>
					</td>
				</tr>
				<tr v-if="!rows.length">
					<td colspan="5" class="px-muted">No staff yet.</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>
