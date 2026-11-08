<script setup>
import { useRouter } from "vue-router";
import { useBooking } from "../store";
const store = useBooking();
const router = useRouter();
async function next() {
  try {
    await store.loadSlots();
    if (
      !store.slots.some(
        (s) =>
          s.start === store.slot?.start &&
          Number(s.staff_id) === Number(store.slot?.staff_id),
      )
    ) {
      store.slot = null;
      store.error = "Please choose a time that fits your extras.";
      router.push("/time");
      return;
    }
    store.couponInfo = null;
    router.push("/details");
  } catch (e) {
    store.error = e.message;
  }
}
</script>
<template>
  <div class="pxb-card">
    <h3>Extras</h3>
    <label v-for="ex in store.extras" :key="ex.id" class="pxb-choice">
      {{ ex.title }} — {{ store.config.currency }} {{ ex.price }}
      <input
        type="number"
        min="0"
        :max="ex.max_quantity"
        v-model.number="store.extraSel[ex.id]"
      />
    </label>
    <div class="pxb-bar">
      <button class="pxb-btn ghost" @click="router.push('/time')">Back</button>
      <button class="pxb-btn" @click="next">Continue</button>
    </div>
  </div>
</template>
