<script setup>
import { useRouter } from "vue-router";
import { useBooking } from "../store";
const store = useBooking();
const router = useRouter();
async function pick(m, any) {
  try {
    await store.pickStaff(m, any);
    router.push("/time");
  } catch (error) {
    store.error = error.message;
  }
}
</script>
<template>
  <div class="pxb-card">
    <h3>Choose staff</h3>
    <button
      v-if="store.staff.length"
      class="pxb-choice"
      @click="pick(null, true)"
    >
      Any staff
    </button>
    <button
      class="pxb-choice"
      v-for="m in store.staff"
      :key="m.id"
      @click="pick(m, false)"
    >
      <b>{{ m.full_name }}</b>
    </button>
    <p v-if="!store.staff.length" class="pxb-muted">
      No staff are available for this service at this location.
    </p>
    <div class="pxb-bar">
      <button class="pxb-btn ghost" @click="router.push('/service')">
        Back
      </button>
    </div>
  </div>
</template>
