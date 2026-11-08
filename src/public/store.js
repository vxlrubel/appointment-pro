import { defineStore } from "pinia";
import { api } from "./api";

function uuid() {
  return crypto.randomUUID
    ? crypto.randomUUID()
    : String(Date.now()) + Math.random();
}

export const useBooking = defineStore("booking", {
  state: () => ({
    config: {},
    preset: { location_id: 0, service_id: 0, staff_id: 0 },
    locations: [],
    services: [],
    categories: [],
    staff: [],
    extras: [],
    fields: [],
    slots: [],
    date: new Date().toISOString().slice(0, 10),
    location: null,
    service: null,
    staffMember: null,
    anyStaff: false,
    slot: null,
    extraSel: {},
    customer: { full_name: "", email: "", phone: "" },
    custom_fields: {},
    notes: "",
    coupon: "",
    couponInfo: null,
    create_account: false,
    error: "",
    result: null,
    loading: false,
    key: uuid(),
  }),
  getters: {
    extrasList(state) {
      return Object.entries(state.extraSel)
        .filter(([, qty]) => qty > 0)
        .map(([id, qty]) => ({ id: Number(id), qty: Number(qty) }));
    },
    subtotal(state) {
      let n = Number(state.service?.price || 0);
      if (
        state.staffMember?.override_price != null &&
        state.staffMember.override_price !== ""
      )
        n = Number(state.staffMember.override_price);
      for (const ex of state.extras) {
        const qty = Number(state.extraSel[ex.id] || 0);
        n += Number(ex.price) * qty;
      }
      if (state.couponInfo) n = Number(state.couponInfo.total);
      return Math.max(0, n);
    },
  },
  actions: {
    async boot(preset) {
      this.preset = preset;
      this.config = await api("config");
      this.locations = await api("locations");
      if (preset.location_id) {
        this.location =
          this.locations.find(
            (l) => Number(l.id) === Number(preset.location_id),
          ) || null;
      }
      if (this.locations.length === 1) this.location = this.locations[0];
      if (this.location) await this.loadServices();
    },
    async loadServices() {
      const data = await api("services?location_id=" + this.location.id);
      this.services = data.services || data;
      this.categories = data.categories || [];
      if (this.preset.service_id) {
        this.service =
          this.services.find(
            (s) => Number(s.id) === Number(this.preset.service_id),
          ) || null;
      }
      if (this.services.length === 1) this.service = this.services[0];
      if (this.service) await this.loadStaff();
    },
    async pickLocation(loc) {
      this.extraSel = {};
      this.couponInfo = null;
      this.coupon = "";
      this.custom_fields = {};
      this.location = loc;
      this.service = null;
      this.staffMember = null;
      this.slot = null;
      await this.loadServices();
    },
    async pickService(svc) {
      this.extraSel = {};
      this.couponInfo = null;
      this.coupon = "";
      this.custom_fields = {};
      this.service = svc;
      this.staffMember = null;
      this.slot = null;
      await this.loadStaff();
    },
    async loadStaff() {
      this.staff = await api(
        `staff?location_id=${this.location.id}&service_id=${this.service.id}`,
      );
      this.extras = await api("extras?service_id=" + this.service.id);
      this.fields = await api("custom-fields?service_id=" + this.service.id);
      if (this.preset.staff_id) {
        this.staffMember =
          this.staff.find(
            (s) => Number(s.id) === Number(this.preset.staff_id),
          ) || null;
      }
      if (this.staff.length === 1) this.staffMember = this.staff[0];
    },
    async pickStaff(member, any = false) {
      this.staffMember = any ? null : member;
      this.anyStaff = any;
      this.slot = null;
      await this.loadSlots();
    },
    async loadSlots() {
      const extras = JSON.stringify(this.extrasList);
      const staffId = this.anyStaff ? 0 : this.staffMember?.id || 0;
      this.slots = await api(
        `availability?location_id=${this.location.id}&service_id=${this.service.id}&staff_id=${staffId}&date=${this.date}&extras=${encodeURIComponent(extras)}`,
      );
    },
    pickSlot(slot) {
      this.slot = slot;
      if (slot.staff_id) {
        this.staffMember =
          this.staff.find((s) => Number(s.id) === Number(slot.staff_id)) ||
          this.staffMember;
      }
    },
    async applyCoupon() {
      this.error = "";
      try {
        this.couponInfo = await api("coupons/validate", {
          method: "POST",
          body: {
            code: this.coupon,
            service_id: this.service.id,
            amount: this.subtotal,
          },
        });
      } catch (e) {
        this.couponInfo = null;
        this.error = e.message;
      }
    },
    async submit(method) {
      this.loading = true;
      this.error = "";
      try {
        const created = await api("appointments", {
          method: "POST",
          headers: { "Idempotency-Key": this.key },
          body: {
            idempotency_key: this.key,
            location_id: this.location.id,
            service_id: this.service.id,
            staff_id: this.staffMember.id,
            start: this.slot.start,
            extras: this.extrasList,
            custom_fields: this.custom_fields,
            customer: this.customer,
            notes: this.notes,
            coupon: this.coupon,
            create_account: this.create_account,
            payment_method: method,
          },
        });
        const appointment_id = created.appointment.id;
        const amount = created.amount;
        const token = created.customer_appointment.token;
        const alreadyPaid =
          (created.payments || []).some((p) => p.status === "completed") ||
          ["approved", "pending"].includes(created.appointment?.status);
        if (method === "local") {
          if (
            alreadyPaid &&
            (created.payments || []).some((p) => p.status === "completed")
          ) {
            this.result = created;
          } else {
            try {
              this.result = await api("payments/local", {
                method: "POST",
                body: { appointment_id, amount, token },
              });
            } catch {
              this.result = created;
            }
          }
        } else if (method === "stripe") {
          const intent = await api("payments/stripe/intent", {
            method: "POST",
            body: { appointment_id, amount, token },
          });
          this.result = { pending: "stripe", intent, appointment_id, token };
        } else if (method === "paypal") {
          const order = await api("payments/paypal/order", {
            method: "POST",
            body: { appointment_id, amount, token },
          });
          this.result = { pending: "paypal", order, appointment_id, token };
        } else if (method === "woocommerce") {
          const order = await api("payments/woocommerce/order", {
            method: "POST",
            body: { appointment_id, amount, token },
          });
          if (order.checkout_url) window.location.href = order.checkout_url;
          this.result = { pending: "woo", order };
        }
      } catch (e) {
        this.error = e.message;
        if (e.status === 409) this.slot = null;
      } finally {
        this.loading = false;
      }
    },
  },
});
