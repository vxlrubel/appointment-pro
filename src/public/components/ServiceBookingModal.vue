<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import PxModal from "../../admin/src/components/PxModal.vue";
import BookingSkeleton from "./BookingSkeleton.vue";
import App from "../App.vue";
import { useBooking } from "../store";
const props = defineProps({
  controller: { type: Object, required: true },
  serviceId: { type: Number, required: true },
});
const store = useBooking(),
  router = useRouter(),
  route = useRoute();
const ready = ref(false),
  initializing = ref(false);
const heading = computed(() => store.service?.name || "Book an appointment");
async function initialize() {
  if (initializing.value) return;
  initializing.value = true;
  ready.value = false;
  store.$reset();
  store.lockedServiceId = props.serviceId;
  try {
    await store.boot({
      service_id: props.serviceId,
      location_id: 0,
      staff_id: 0,
    });
    if (store.error) throw new Error(store.error);
    const service = store.services.find(
      (item) => Number(item.id) === props.serviceId,
    );
    if (!service)
      throw new Error("This service is no longer available for booking.");
    await store.pickService(service);
    if (store.error) throw new Error(store.error);
    await router.replace("/staff");
    ready.value = true;
  } catch (error) {
    store.error = error.message;
  } finally {
    initializing.value = false;
  }
}
watch(
  () => props.controller.open,
  (open) => {
    if (open && route.name === "done") initialize();
  },
);
onMounted(initialize);
</script>
<template>
  <PxModal
    :open="controller.open"
    :heading="heading"
    :busy="store.loading"
    hide-footer
    keep-mounted
    @close="controller.open = false"
  >
    <div class="pxb-service-modal-content">
      <BookingSkeleton
        v-if="initializing"
        variant="cards"
        label="Loading service booking"
      />
      <template v-else-if="!ready"
        ><p class="pxb-err" role="alert">{{ store.error }}</p>
        <button type="button" class="pxb-btn ghost" @click="initialize">
          Try again
        </button></template
      >
      <template v-else
        ><App />
        <div v-if="route.name === 'done'" class="pxb-bar">
          <button
            type="button"
            class="pxb-btn"
            @click="controller.open = false"
          >
            Close
          </button>
        </div></template
      >
    </div>
  </PxModal>
</template>
