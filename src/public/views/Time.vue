<script setup>
import { computed, nextTick, onMounted, ref } from "vue";
import BookingSkeleton from "../components/BookingSkeleton.vue";
import { useRouter } from "vue-router";
import { useBooking } from "../store";
const store = useBooking();
const router = useRouter();
const calendar = ref(null);
const month = computed(
  () => store.calendarMonth || (store.config.today || store.date).slice(0, 7),
);
const firstDay = computed(
  () =>
    new Date(
      Number(month.value.slice(0, 4)),
      Number(month.value.slice(5, 7)) - 1,
      1,
      12,
    ),
);
const monthLabel = computed(() =>
  firstDay.value.toLocaleDateString(undefined, {
    month: "long",
    year: "numeric",
  }),
);
const weekdays = Array.from({ length: 7 }, (_, day) =>
  new Date(2024, 0, 7 + day).toLocaleDateString(undefined, {
    weekday: "short",
  }),
);
const days = computed(() =>
  Array.from(
    {
      length: new Date(
        firstDay.value.getFullYear(),
        firstDay.value.getMonth() + 1,
        0,
      ).getDate(),
    },
    (_, index) => ({
      number: index + 1,
      date: `${month.value}-${String(index + 1).padStart(2, "0")}`,
    }),
  ),
);
function offsetMonth(offset) {
  const date = new Date(
    firstDay.value.getFullYear(),
    firstDay.value.getMonth() + offset,
    1,
    12,
  );
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
}
function canNavigate(offset) {
  if (store.pending.month || store.pending.slots) return false;
  const target = offsetMonth(offset);
  return (
    (!store.calendarBounds.min_date ||
      target >= store.calendarBounds.min_date.slice(0, 7)) &&
    (!store.calendarBounds.max_date ||
      target <= store.calendarBounds.max_date.slice(0, 7))
  );
}
function fullDate(date) {
  return new Date(date + "T12:00:00").toLocaleDateString(undefined, {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}
function enabled(date) {
  return (
    !store.pending.month &&
    !store.pending.slots &&
    Number(store.calendarDays[date]) > 0
  );
}
async function chooseDate(date) {
  if (!enabled(date)) return;
  store.date = date;
  store.slot = null;
  try {
    await store.loadSlots();
  } catch {
    /* Shared error feedback is provided by the store. */
  }
}
async function keyboard(event, date) {
  const offsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
  const offset = offsets[event.key];
  if (!offset) return;
  event.preventDefault();
  let index = days.value.findIndex((day) => day.date === date) + offset;
  while (index >= 0 && index < days.value.length) {
    const target = days.value[index].date;
    if (enabled(target)) {
      await nextTick();
      calendar.value.querySelector(`[data-date="${target}"]`)?.focus();
      return;
    }
    index += offset > 0 ? 1 : -1;
  }
}
function pick(slot) {
  store.pickSlot(slot);
  router.push(store.extras.length ? "/extras" : "/details");
}
onMounted(() => store.loadCalendar());
</script>
<template>
  <div class="pxb-card">
    <h3>Date and time</h3>
    <p class="pxb-muted">Choose an available date to see appointment times.</p>
    <div
      ref="calendar"
      class="pxb-calendar"
      role="group"
      aria-label="Appointment calendar"
      :aria-busy="store.pending.month"
    >
      <div class="pxb-calendar-header">
        <button
          class="pxb-calendar-nav"
          aria-label="Previous month"
          :disabled="!canNavigate(-1)"
          @click="store.loadCalendar(offsetMonth(-1))"
        >
          &lsaquo;
        </button>
        <strong aria-live="polite">{{ monthLabel }}</strong>
        <button
          class="pxb-calendar-nav"
          aria-label="Next month"
          :disabled="!canNavigate(1)"
          @click="store.loadCalendar(offsetMonth(1))"
        >
          &rsaquo;
        </button>
      </div>
      <div class="pxb-calendar-grid">
        <span
          v-for="weekday in weekdays"
          :key="weekday"
          class="pxb-calendar-weekday"
          aria-hidden="true"
          >{{ weekday }}</span
        >
        <span
          v-for="blank in firstDay.getDay()"
          :key="'blank-' + blank"
          aria-hidden="true"
        ></span>
        <button
          v-for="day in days"
          :key="day.date"
          type="button"
          class="pxb-calendar-day"
          :class="{
            selected: store.date === day.date,
            today: day.date === store.calendarBounds.today,
            'is-loading': store.pending.month,
          }"
          :disabled="!enabled(day.date)"
          :data-date="day.date"
          :aria-label="
            fullDate(day.date) +
            (store.calendarDays[day.date] > 0
              ? ', ' + store.calendarDays[day.date] + ' available times'
              : ', no available times')
          "
          :aria-pressed="store.date === day.date"
          :aria-current="
            day.date === store.calendarBounds.today ? 'date' : undefined
          "
          @click="chooseDate(day.date)"
          @keydown="keyboard($event, day.date)"
        >
          {{ day.number
          }}<span
            v-if="store.pending.month"
            class="pxb-skeleton pxb-calendar-placeholder"
            aria-hidden="true"
          ></span>
        </button>
      </div>
      <p
        v-if="store.pending.month"
        role="status"
        class="pxb-sr-only"
        aria-label="Loading available dates"
      >
        Loading available dates. Please wait.
      </p>
      <p class="pxb-calendar-legend">
        <span aria-hidden="true"></span>Available dates &middot; Dates without
        times are disabled
      </p>
    </div>
    <button
      v-if="store.error"
      class="pxb-btn ghost"
      :disabled="store.pending.month || store.pending.slots"
      @click="store.loadCalendar()"
    >
      Retry availability
    </button>
    <BookingSkeleton
      v-if="store.pending.slots"
      variant="slots"
      label="Loading available times"
      embedded
    />
    <template v-else-if="!store.pending.month">
      <h4 v-if="store.date" class="pxb-times-heading">
        {{ fullDate(store.date) }}
      </h4>
      <p v-if="store.config.timezone" class="pxb-muted">
        Times shown in {{ store.config.timezone }}
      </p>
      <div class="pxb-grid">
        <button
          v-for="slot in store.slots"
          :key="slot.start + slot.staff_id"
          class="pxb-slot"
          @click="pick(slot)"
        >
          {{ slot.display }}
        </button>
      </div>
      <p v-if="!store.error && !store.slots.length" class="pxb-muted">
        {{
          store.date
            ? "No times remain for this date. Please choose another available date."
            : "No appointments are available this month. Try another month."
        }}
      </p>
    </template>
    <div class="pxb-bar">
      <button class="pxb-btn ghost" @click="router.push('/staff')">Back</button>
    </div>
  </div>
</template>
