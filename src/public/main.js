import PxCurrency from "../shared/PxCurrency.vue";
import { createApp, reactive, watch } from "vue";
import { createPinia } from "pinia";
import {
  createRouter,
  createWebHashHistory,
  createMemoryHistory,
} from "vue-router";
import App from "./App.vue";
import ServiceBookingModal from "./components/ServiceBookingModal.vue";
import { useBooking } from "./store";
import Service from "./views/Service.vue";
import Staff from "./views/Staff.vue";
import Time from "./views/Time.vue";
import Extras from "./views/Extras.vue";
import Details from "./views/Details.vue";
import Payment from "./views/Payment.vue";
import Done from "./views/Done.vue";
import "./styles.css";

const routes = [
  { path: "/", redirect: "/service" },
  { path: "/location", redirect: "/service" },
  { path: "/service", name: "service", component: Service },
  { path: "/staff", name: "staff", component: Staff },
  { path: "/time", name: "time", component: Time },
  { path: "/extras", name: "extras", component: Extras },
  { path: "/details", name: "details", component: Details },
  { path: "/payment", name: "payment", component: Payment },
  { path: "/done", name: "done", component: Done },
];

function bookingApp(component, history, props = {}) {
  const router = createRouter({ history, routes });
  const app = createApp(component, props);
  const pinia = createPinia();
  app.component("PxCurrency", PxCurrency);
  app.use(pinia);
  const store = useBooking(pinia);
  router.beforeEach((to) => {
    if (
      store.lockedServiceId &&
      to.path === "/service" &&
      store.location &&
      store.service
    )
      return "/staff";
    if (to.path !== "/service" && !store.service) return "/service";
    if (to.path !== "/service" && !store.location) return "/service";
    if (
      ["/details", "/extras", "/payment", "/done"].includes(to.path) &&
      !store.slot
    )
      return "/time";
    if (to.path === "/done" && !store.result) return "/payment";
  });
  app.use(router);
  return { app, router, store };
}

async function mountBooking() {
  const el = document.getElementById("px-appointment-booking-app");
  if (!el || el.__praxleoMounted) {
    return;
  }
  el.__praxleoMounted = true;
  const { app, router, store } = bookingApp(App, createWebHashHistory());
  await router.isReady();
  app.mount(el);
  await store.boot({
    location_id: Number(el.dataset.locationId || 0),
    service_id: Number(el.dataset.serviceId || 0),
    staff_id: Number(el.dataset.staffId || 0),
  });
}

// Each service button uses isolated memory history and booking state. Modal navigation
// never changes the page hash or the state of an inline booking form.
const serviceBookings = new WeakMap();
let activeServiceBooking;
async function openServiceBooking(button) {
  const serviceId = Number(button.dataset.pxServiceBooking);
  if (!Number.isSafeInteger(serviceId) || serviceId < 1) return;
  if (activeServiceBooking) activeServiceBooking.open = false;
  let controller = serviceBookings.get(button);
  if (!controller) {
    controller = reactive({ open: true });
    activeServiceBooking = controller;
    serviceBookings.set(button, controller);
    const host = document.createElement("div");
    host.className = "pxb-service-booking-host";
    document.body.appendChild(host);
    const { app, router } = bookingApp(
      ServiceBookingModal,
      createMemoryHistory(),
      { controller, serviceId },
    );
    watch(
      () => controller.open,
      (open) => {
        button.setAttribute("aria-expanded", String(open));
      },
    );
    await router.replace("/service");
    app.mount(host);
  } else {
    controller.open = true;
  }
  activeServiceBooking = controller;
  button.setAttribute("aria-expanded", "true");
}
function boot() {
  mountBooking();
  document.addEventListener("click", (event) => {
    const button =
      event.target instanceof Element
        ? event.target.closest("[data-px-service-booking]")
        : null;
    if (!button || button.disabled) return;
    event.preventDefault();
    openServiceBooking(button);
  });
}
if (document.readyState === "loading")
  document.addEventListener("DOMContentLoaded", boot);
else boot();
