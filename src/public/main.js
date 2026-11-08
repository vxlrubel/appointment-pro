import { createApp } from "vue";
import { createPinia } from "pinia";
import { createRouter, createWebHashHistory } from "vue-router";
import App from "./App.vue";
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

async function mountBooking() {
  const el = document.getElementById("px-appointment-booking-app");
  if (!el || el.__praxleoMounted) {
    return;
  }
  el.__praxleoMounted = true;
  const router = createRouter({ history: createWebHashHistory(), routes });
  const app = createApp(App);
  const pinia = createPinia();
  app.use(pinia);
  const store = useBooking(pinia);
  router.beforeEach((to) => {
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
  await router.isReady();
  app.mount(el);
  await store.boot({
    location_id: Number(el.dataset.locationId || 0),
    service_id: Number(el.dataset.serviceId || 0),
    staff_id: Number(el.dataset.staffId || 0),
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", mountBooking);
} else {
  mountBooking();
}
