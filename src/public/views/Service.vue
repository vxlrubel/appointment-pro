<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";
import { useBooking } from "../store";
const store = useBooking();
const router = useRouter();
const grouped = computed(() => {
  const cats = store.categories || [];
  const services = store.services || [];
  const groups = cats
    .slice()
    .sort((a, b) => Number(a.position || 0) - Number(b.position || 0))
    .map((c) => ({
      id: c.id,
      name: c.name,
      services: services.filter((s) => Number(s.category_id) === Number(c.id)),
    }))
    .filter((g) => g.services.length);
  const uncategorized = services.filter(
    (s) => !s.category_id || s.category_id === "0" || s.category_id === 0,
  );
  if (uncategorized.length) {
    groups.push({
      id: 0,
      name: groups.length ? "Other" : "Services",
      services: uncategorized,
    });
  }
  if (!groups.length) {
    return [{ id: 0, name: "Services", services }];
  }
  return groups;
});
async function pick(s) {
  await store.pickService(s);
  router.push("/staff");
}
</script>
<template>
  <div class="pxb-card">
    <h3>Choose a service</h3>
    <div v-for="g in grouped" :key="g.id">
      <h4 class="pxb-cat">{{ g.name }}</h4>
      <button
        class="pxb-choice"
        v-for="s in g.services"
        :key="s.id"
        @click="pick(s)"
      >
        <b>{{ s.name }}</b>
        <div>
          {{ Math.round(s.duration_sec / 60) }} min ·
          {{ store.config.currency }} {{ s.price }}
        </div>
      </button>
    </div>
    <div class="pxb-bar">
      <button class="pxb-btn ghost" @click="router.push('/location')">
        Back
      </button>
    </div>
  </div>
</template>
