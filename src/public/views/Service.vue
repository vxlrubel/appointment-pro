<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";
import { useBooking } from "../store";
const store = useBooking();
const router = useRouter();
const locationOptions = computed(() => {
  if (store.categoryFilterId === "") return store.locations;
  const ids = new Set(
    store.services
      .filter(
        (service) =>
          Number(service.category_id || 0) === Number(store.categoryFilterId),
      )
      .flatMap((service) => service.location_ids || [])
      .map(Number),
  );
  return store.locations.filter((location) => ids.has(Number(location.id)));
});
const categoryOptions = computed(() => {
  const services = store.services.filter(
    (service) =>
      !store.locationFilterId ||
      (service.location_ids || [])
        .map(Number)
        .includes(Number(store.locationFilterId)),
  );
  const ids = new Set(
    services.map((service) => Number(service.category_id || 0)),
  );
  const categories = store.categories.filter((category) =>
    ids.has(Number(category.id)),
  );
  if (ids.has(0)) categories.push({ id: 0, name: "Uncategorized" });
  return categories;
});
function changeLocation(event) {
  store.locationFilterId = Number(event.target.value);
  if (
    store.categoryFilterId !== "" &&
    !categoryOptions.value.some(
      (category) => Number(category.id) === Number(store.categoryFilterId),
    )
  ) {
    store.categoryFilterId = "";
  }
  store.error = "";
}
function changeCategory(event) {
  store.categoryFilterId = event.target.value;
  if (
    store.locationFilterId &&
    !locationOptions.value.some(
      (location) => Number(location.id) === Number(store.locationFilterId),
    )
  ) {
    store.locationFilterId = 0;
  }
  store.error = "";
}
const grouped = computed(() => {
  const categories = store.categories
    .slice()
    .sort((a, b) => Number(a.position || 0) - Number(b.position || 0));
  const known = new Set(categories.map((category) => Number(category.id)));
  const groups = categories
    .map((category) => ({
      ...category,
      services: store.filteredServices.filter(
        (service) => Number(service.category_id) === Number(category.id),
      ),
    }))
    .filter((group) => group.services.length);
  const other = store.filteredServices.filter(
    (service) => !known.has(Number(service.category_id || 0)),
  );
  if (other.length)
    groups.push({
      id: 0,
      name: groups.length ? "Other services" : "Services",
      services: other,
    });
  return groups;
});
async function pick(service) {
  try {
    await store.pickService(service);
    if (store.location) router.push("/staff");
  } catch (error) {
    store.error = error.message;
  }
}
</script>
<template>
  <div class="pxb-card">
    <h3>Choose a service</h3>
    <p class="pxb-muted">
      Browse our services, or filter by location and category.
    </p>
    <div class="pxb-filters">
      <label
        >Location
        <select :value="store.locationFilterId" @change="changeLocation">
          <option :value="0">All locations</option>
          <option
            v-for="location in locationOptions"
            :key="location.id"
            :value="Number(location.id)"
          >
            {{ location.name }}
          </option>
        </select>
      </label>
      <label
        >Category
        <select :value="store.categoryFilterId" @change="changeCategory">
          <option value="">All categories</option>
          <option
            v-for="category in categoryOptions"
            :key="category.id"
            :value="String(category.id)"
          >
            {{ category.name }}
          </option>
        </select>
      </label>
    </div>
    <p class="pxb-muted" aria-live="polite">
      {{ store.filteredServices.length }}
      {{ store.filteredServices.length === 1 ? "service" : "services" }}
    </p>
    <div v-for="group in grouped" :key="group.id">
      <h4 class="pxb-cat">{{ group.name }}</h4>
      <article
        v-for="service in group.services"
        :key="service.id"
        class="pxb-service-card"
      >
        <button
          class="pxb-choice"
          :class="{ sel: Number(store.service?.id) === Number(service.id) }"
          :disabled="!service.location_ids?.length"
          @click="pick(service)"
        >
          <b>{{ service.name }}</b>
          <div>
            {{ Math.round(service.duration_sec / 60) }} min &middot;
            {{ store.config.currency }} {{ Number(service.price).toFixed(2) }}
          </div>
          <small v-if="!service.location_ids?.length" class="pxb-muted"
            >Currently unavailable for booking</small
          >
        </button>
      </article>
    </div>
    <p v-if="!store.filteredServices.length" class="pxb-muted">
      No services match these filters. Try another location or category.
    </p>
  </div>
</template>
