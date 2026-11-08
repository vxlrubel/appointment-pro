<script setup>
import CrudView from '../components/CrudView.vue'

const fields = [
  { key: 'label', label: 'Label' },
  {
    key: 'type',
    label: 'Type',
    type: 'select',
    defaultValue: 'text',
    options: [
      { value: 'text', label: 'Text' },
      { value: 'textarea', label: 'Textarea' },
      { value: 'select', label: 'Select' },
      { value: 'checkbox', label: 'Checkbox' },
      { value: 'number', label: 'Number' },
      { value: 'date', label: 'Date' },
    ],
  },
  { key: 'options_json', label: 'Options (JSON array)', type: 'textarea', defaultValue: '[]' },
  {
    key: 'services_json',
    label: 'Service IDs (JSON array; empty for all)',
    type: 'textarea',
    defaultValue: '[]',
  },
  { key: 'position', label: 'Position', type: 'number', defaultValue: 0 },
  { key: 'required', label: 'Required', type: 'bool' },
  { key: 'enabled', label: 'Enabled', type: 'bool' },
]
</script>

<template>
  <CrudView path="custom-fields" :fields="fields" />
</template>
