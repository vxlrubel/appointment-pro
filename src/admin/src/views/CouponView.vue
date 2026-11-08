<script setup>
import CrudView from '../components/CrudView.vue'

const fields = [
  { key: 'code', label: 'Code' },
  {
    key: 'discount_type',
    label: 'Type',
    type: 'select',
    defaultValue: 'percent',
    options: [
      { value: 'percent', label: 'Percent' },
      { value: 'fixed', label: 'Fixed' },
    ],
  },
  { key: 'discount_amount', label: 'Amount', type: 'number', step: '0.01', defaultValue: 0 },
  { key: 'usage_limit', label: 'Usage limit', type: 'number' },
  { key: 'min_amount', label: 'Min amount', type: 'number', step: '0.01', defaultValue: 0 },
  { key: 'start_date', label: 'Valid from', type: 'date' },
  { key: 'end_date', label: 'Valid until', type: 'date' },
  { key: 'enabled', label: 'Enabled', type: 'bool' },
]
</script>

<template>
  <CrudView path="coupons" :fields="fields" />
</template>
