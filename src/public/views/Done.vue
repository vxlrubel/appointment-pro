<script setup>
import { useBooking } from "../store";
const store = useBooking();
</script>
<template>
  <div class="pxb-card">
    <h3>Booking received</h3>
    <p v-if="store.result">
      Thank you {{ store.customer.full_name }}. A confirmation email is on its
      way.
    </p>
    <p>Service: {{ store.service?.name }}</p>
    <p>When: {{ store.slot?.display }} {{ store.date }}</p>
  </div>
</template>
