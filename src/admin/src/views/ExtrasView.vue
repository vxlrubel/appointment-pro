<script setup>
import CrudView from '../components/CrudView.vue'

const fields = [
  {
    key: 'service_id',
    label: 'Service',
    type: 'select',
    optionsPath: 'admin/services',
    optionValue: 'id',
    optionLabel: 'name',
  },
  { key: 'title', label: 'Title' },
  { key: 'duration_sec', label: 'Duration (sec)', type: 'number', defaultValue: 0 },
  { key: 'price', label: 'Price', type: 'number', step: '0.01', defaultValue: 0 },
  { key: 'max_quantity', label: 'Max qty', type: 'number', defaultValue: 1 },
  { key: 'position', label: 'Position', type: 'number', defaultValue: 0 },
  { key: 'enabled', label: 'Enabled', type: 'bool' },
]
</script>

<template>
  <CrudView path="extras" :fields="fields" />
</template>
