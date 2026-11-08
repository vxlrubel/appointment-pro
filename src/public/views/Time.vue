<script setup>
import { useRouter } from "vue-router";
import { useBooking } from "../store";
const store = useBooking();
const router = useRouter();
async function changeDate() {
  store.slot = null;
  try {
    await store.loadSlots();
  } catch (e) {
    store.error = e.message;
  }
}
function pick(slot) {
  store.pickSlot(slot);
  router.push(store.extras.length ? "/extras" : "/details");
}
</script>
<template>
  <div class="pxb-card">
    <h3>Date and time</h3>
    <p v-if="store.error" class="pxb-err">{{ store.error }}</p>
    <input type="date" v-model="store.date" @change="changeDate" />
    <div class="pxb-grid" style="margin-top: 12px">
      <button
        class="pxb-slot"
        v-for="s in store.slots"
        :key="s.start + s.staff_id"
        @click="pick(s)"
      >
        {{ s.display }}
      </button>
    </div>
    <p v-if="!store.slots.length" class="px-muted">No times on this date.</p>
    <div class="pxb-bar">
      <button class="pxb-btn ghost" @click="router.push('/staff')">Back</button>
    </div>
  </div>
</template>
