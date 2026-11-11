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
    pending: {
      boot: true,
      staff: false,
      slots: false,
      coupon: false,
      month: false,
    },
    locationFilterId: 0,
    categoryFilterId: "",
    slotRequest: 0,
    monthRequest: 0,
    calendarMonth: "",
    calendarDays: {},
    calendarBounds: {},
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
    serviceLocations(state) {
      const ids = state.service?.location_ids || [];
      return state.locations.filter((location) =>
        ids.includes(Number(location.id)),
      );
    },
    filteredServices(state) {
      return state.services.filter(
        (service) =>
          (!state.locationFilterId ||
            (service.location_ids || []).includes(
              Number(state.locationFilterId),
            )) &&
          (state.categoryFilterId === "" ||
            Number(service.category_id || 0) ===
              Number(state.categoryFilterId)),
      );
    },
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
      this.pending.boot = true;
      this.preset = preset;
      this.error = "";
      try {
        const [config, locations, catalog] = await Promise.all([
          api("config"),
          api("locations"),
          api("services"),
        ]);
        this.config = config;
        this.locations = locations;
        this.services = catalog.services || [];
        this.categories = catalog.categories || [];
        this.locationFilterId = locations.some(
          (location) => Number(location.id) === Number(preset.location_id),
        )
          ? Number(preset.location_id)
          : 0;
        this.service =
          this.services.find(
            (service) => Number(service.id) === Number(preset.service_id),
          ) || null;
      } catch (error) {
        this.error = error.message;
      } finally {
        this.pending.boot = false;
      }
    },
    resetSelection() {
      this.staff = [];
      this.extras = [];
      this.fields = [];
      this.slots = [];
      this.staffMember = null;
      this.anyStaff = false;
      this.slot = null;
      this.extraSel = {};
      this.couponInfo = null;
      this.coupon = "";
      this.custom_fields = {};
      this.result = null;
      this.error = "";
      this.key = uuid();
      this.slotRequest++;
      this.pending.slots = false;
      this.monthRequest++;
      this.pending.month = false;
      this.calendarDays = {};
      this.calendarMonth = "";
      this.calendarBounds = {};
    },
    async pickService(service, locationId = 0) {
      this.resetSelection();
      this.service = service;
      const locations = this.serviceLocations;
      this.location =
        locations.find(
          (location) =>
            Number(location.id) === Number(locationId || this.locationFilterId),
        ) ||
        locations[0] ||
        null;
      if (!this.location) {
        this.error = "Please choose a location for this service.";
        return;
      }
      this.locationFilterId = Number(this.location.id);
      this.categoryFilterId = String(service.category_id || 0);
      await this.loadStaff();
    },
    async loadStaff() {
      if (!this.location || !this.service) return;
      this.pending.staff = true;
      this.error = "";
      try {
        const query = `location_id=${this.location.id}&service_id=${this.service.id}`;
        const [staff, extras, fields] = await Promise.all([
          api(`staff?${query}`),
          api("extras?service_id=" + this.service.id),
          api("custom-fields?service_id=" + this.service.id),
        ]);
        this.staff = staff;
        this.extras = extras;
        this.fields = fields;
        this.staffMember =
          staff.find(
            (member) => Number(member.id) === Number(this.preset.staff_id),
          ) || (staff.length === 1 ? staff[0] : null);
      } finally {
        this.pending.staff = false;
      }
    },
    async pickStaff(member, any = false) {
      this.staffMember = any ? null : member;
      this.anyStaff = any;
      this.slot = null;
      this.slots = [];
      this.calendarDays = {};
    },
    async loadCalendar(
      month = this.calendarMonth ||
        (this.config.today || this.date).slice(0, 7),
    ) {
      if (!this.location || !this.service) return;
      const request = ++this.monthRequest;
      this.slotRequest++;
      this.pending.slots = false;
      this.pending.month = true;
      this.error = "";
      this.calendarMonth = month;
      this.calendarDays = {};
      this.slot = null;
      this.slots = [];
      let selected = "";
      try {
        const staffId = this.anyStaff ? 0 : this.staffMember?.id || 0;
        const extras = encodeURIComponent(JSON.stringify(this.extrasList));
        const data = await api(
          `availability/dates?location_id=${this.location.id}&service_id=${this.service.id}&staff_id=${staffId}&month=${month}&extras=${extras}`,
        );
        if (request !== this.monthRequest) return;
        this.calendarDays = data.dates;
        this.calendarBounds = {
          today: data.today,
          min_date: data.min_date,
          max_date: data.max_date,
        };
        selected =
          data.dates[this.date] > 0
            ? this.date
            : Object.keys(data.dates).find((date) => data.dates[date] > 0) ||
              "";
        this.date = selected;
      } catch (error) {
        if (request === this.monthRequest) {
          this.error = error.message;
          this.date = "";
        }
      } finally {
        if (request === this.monthRequest) this.pending.month = false;
      }
      if (request === this.monthRequest && selected) {
        try {
          await this.loadSlots();
        } catch {
          /* Error feedback comes from loadSlots. */
        }
      }
    },
    async loadSlots() {
      if (!this.location || !this.service) return;
      const request = ++this.slotRequest;
      this.pending.slots = true;
      this.error = "";
      try {
        const extras = encodeURIComponent(JSON.stringify(this.extrasList));
        const staffId = this.anyStaff ? 0 : this.staffMember?.id || 0;
        const slots = await api(
          `availability?location_id=${this.location.id}&service_id=${this.service.id}&staff_id=${staffId}&date=${this.date}&extras=${extras}`,
        );
        if (request === this.slotRequest) {
          this.slots = slots;
          if (Object.hasOwn(this.calendarDays, this.date))
            this.calendarDays[this.date] = slots.length;
          if (!slots.length) this.slot = null;
        }
      } catch (error) {
        if (request !== this.slotRequest) return;
        if (request === this.slotRequest) {
          this.slots = [];
          this.error = error.message;
        }
        throw error;
      } finally {
        if (request === this.slotRequest) this.pending.slots = false;
      }
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
      if (this.pending.coupon) return;
      this.pending.coupon = true;
      this.couponInfo = null;
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
      } finally {
        this.pending.coupon = false;
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
            affiliate_code: new URLSearchParams(window.location.search).get("px_aff") || "",
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
