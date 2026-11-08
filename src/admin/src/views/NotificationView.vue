<script setup>
import CrudView from '../components/CrudView.vue'

const fields = [
  { key: 'event', label: 'Event' },
  { key: 'subject', label: 'Subject' },
  { key: 'body_html', label: 'Body', type: 'textarea' },
  { key: 'enabled', label: 'Enabled', type: 'bool' },
]
</script>

<template>
  <CrudView path="notifications" :fields="fields" :allow-add="false" :allow-delete="false" />
</template>
