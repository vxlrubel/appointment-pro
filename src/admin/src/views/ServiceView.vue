<script setup>
import CrudView from '../components/CrudView.vue'

const fields = [
  { key: 'id', label: 'ID', hideInForm: true },
  { key: 'name', label: 'Name' },
  {
    key: 'category_id',
    label: 'Category',
    type: 'select',
    optionsPath: 'admin/categories',
    optionValue: 'id',
    optionLabel: 'name',
  },
  { key: 'duration_sec', label: 'Duration (sec)', type: 'number', defaultValue: 3600 },
  { key: 'price', label: 'Price', type: 'number', step: '0.01', defaultValue: 0 },
  { key: 'padding_left_sec', label: 'Padding before', type: 'number', defaultValue: 0 },
  { key: 'padding_right_sec', label: 'Padding after', type: 'number', defaultValue: 0 },
  { key: 'color', label: 'Color', defaultValue: '#0d6efd' },
  { key: 'info', label: 'Description', type: 'textarea' },
  { key: 'position', label: 'Position', type: 'number', defaultValue: 0 },
  { key: 'enabled', label: 'Enabled', type: 'bool' },
]
</script>

<template>
  <CrudView path="services" :fields="fields" />
</template>
