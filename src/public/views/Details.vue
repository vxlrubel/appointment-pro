<script setup>
import BookingSkeleton from "../components/BookingSkeleton.vue";
import { useRouter } from "vue-router";
import { useBooking } from "../store";
const store = useBooking();
const router = useRouter();
function next() {
  if (!store.customer.full_name || !store.customer.email) {
    store.error = "Name and email are required.";
    return;
  }
  store.error = "";
  router.push("/payment");
}
</script>
<template>
  <div class="pxb-card">
    <h3>Your details</h3>
    <p v-if="store.error" class="pxb-err">{{ store.error }}</p>
    <form class="pxb-form" @submit.prevent="next">
      <input
        required
        placeholder="Full name"
        v-model="store.customer.full_name"
      />
      <input
        required
        placeholder="Email"
        type="email"
        v-model="store.customer.email"
      />
      <input placeholder="Phone" v-model="store.customer.phone" />
      <textarea placeholder="Notes" v-model="store.notes"></textarea>
      <template v-for="f in store.fields" :key="f.id">
        <label>{{ f.label }}</label>
        <textarea
          v-if="f.type === 'textarea'"
          v-model="store.custom_fields[f.id]"
          :required="!!Number(f.required)"
        ></textarea>
        <PxSelect
          :aria-label="f.label"
          v-else-if="f.type === 'select'"
          v-model="store.custom_fields[f.id]"
          :required="!!Number(f.required)"
        >
          <option value="">Select</option>
          <option
            v-for="option in JSON.parse(f.options_json || '[]')"
            :key="option"
          >
            {{ option }}
          </option>
        </PxSelect>
        <input
          v-else
          v-model="store.custom_fields[f.id]"
          :type="
            ['checkbox', 'number', 'date', 'email'].includes(f.type)
              ? f.type
              : 'text'
          "
          :required="!!Number(f.required)"
        />
      </template>
      <label
        ><input type="checkbox" v-model="store.create_account" /> Create an
        account</label
      >
      <div class="pxb-row">
        <input placeholder="Coupon" v-model="store.coupon" />
        <button
          class="pxb-btn ghost"
          type="button"
          :disabled="store.pending.coupon"
          @click="store.applyCoupon()"
        >
          {{ store.pending.coupon ? "Applying..." : "Apply" }}
        </button>
      </div>
      <BookingSkeleton
        v-if="store.pending.coupon"
        variant="form"
        label="Checking coupon"
        embedded
      />
      <div class="pxb-bar">
        <button
          type="button"
          class="pxb-btn ghost"
          @click="router.push(store.extras.length ? '/extras' : '/time')"
        >
          Back
        </button>
        <button type="submit" class="pxb-btn" :disabled="store.pending.coupon">
          Continue
        </button>
      </div>
    </form>
  </div>
</template>
