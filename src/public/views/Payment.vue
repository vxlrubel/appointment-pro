<script setup>
import { nextTick, onBeforeUnmount, ref } from "vue";
import { useRouter } from "vue-router";
import { useBooking } from "../store";
import { api } from "../api";
const store = useBooking();
const router = useRouter();
const cardHost = ref(null);
const paypalHost = ref(null);
const confirming = ref(false);
let stripe, card;
function loadScript(src, name) {
  if (window[name]) return Promise.resolve(window[name]);
  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = src;
    script.onload = () => resolve(window[name]);
    script.onerror = () =>
      reject(new Error("Unable to load payment form. Please try again."));
    document.head.appendChild(script);
  });
}
async function pay(method) {
  await store.submit(method);
  if (store.error || !store.result) return;
  if (!store.result.pending) return router.push("/done");
  try {
    await nextTick();
    if (method === "stripe") {
      const Stripe = await loadScript("https://js.stripe.com/v3/", "Stripe");
      stripe = Stripe(store.config.stripe_publishable);
      card?.destroy();
      card = stripe.elements().create("card");
      card.mount(cardHost.value);
    }
    if (method === "paypal") {
      const paypal = await loadScript(
        "https://www.paypal.com/sdk/js?client-id=" +
          encodeURIComponent(store.config.paypal_client_id) +
          "&currency=" +
          encodeURIComponent(store.config.currency),
        "paypal",
      );
      paypalHost.value.replaceChildren();
      await paypal
        .Buttons({
          createOrder: () => store.result.order.id,
          onApprove: async (data) => {
            try {
              await api("payments/paypal/capture", {
                method: "POST",
                body: {
                  order_id: data.orderID,
                  appointment_id: store.result.appointment_id,
                  token: store.result.token,
                },
              });
              store.result = await api("appointments/" + store.result.token);
              router.push("/done");
            } catch (e) {
              store.error = e.message;
            }
          },
          onError: () => {
            store.error =
              "PayPal could not complete the payment. Please try again.";
          },
          onCancel: () => {
            store.error = "Payment cancelled. You can try again.";
          },
        })
        .render(paypalHost.value);
    }
  } catch (e) {
    store.error = e.message;
  }
}
async function confirmCard() {
  confirming.value = true;
  store.error = "";
  try {
    const result = await stripe.confirmCardPayment(
      store.result.intent.client_secret,
      {
        payment_method: {
          card,
          billing_details: {
            name: store.customer.full_name,
            email: store.customer.email,
          },
        },
      },
    );
    if (result.error) throw new Error(result.error.message);
    if (result.paymentIntent.status === "succeeded") {
      store.result = await api("appointments/" + store.result.token);
      router.push("/done");
    } else {
      store.error =
        "Payment is processing. Your confirmation will arrive by email.";
    }
  } catch (e) {
    store.error = e.message;
  } finally {
    confirming.value = false;
  }
}
onBeforeUnmount(() => card?.destroy());
</script>
<template>
  <div class="pxb-card">
    <h3>Payment</h3>
    <p>Total: {{ store.config.currency }} {{ store.subtotal.toFixed(2) }}</p>
    <p v-if="store.error" class="pxb-err" role="alert">{{ store.error }}</p>
    <button
      v-for="method in ['local', 'stripe', 'paypal', 'woocommerce'].filter(
        (m) => store.config[m + '_enabled'],
      )"
      :key="method"
      class="pxb-choice"
      :disabled="store.loading || confirming"
      @click="pay(method)"
    >
      {{
        {
          local: "Pay at location",
          stripe: "Pay by card",
          paypal: "Pay with PayPal",
          woocommerce: "Pay with WooCommerce",
        }[method]
      }}
    </button>
    <div v-if="store.result?.pending === 'stripe'">
      <div ref="cardHost" class="pxb-card"></div>
      <button class="pxb-btn" :disabled="confirming" @click="confirmCard">
        {{ confirming ? "Processing..." : "Confirm payment" }}
      </button>
    </div>
    <div v-if="store.result?.pending === 'paypal'" ref="paypalHost"></div>
    <p
      v-if="
        !['local', 'stripe', 'paypal', 'woocommerce'].some(
          (m) => store.config[m + '_enabled'],
        )
      "
    >
      No payment methods are available. Please contact us to book.
    </p>
    <div class="pxb-bar">
      <button
        class="pxb-btn ghost"
        :disabled="store.loading || confirming"
        @click="router.push('/details')"
      >
        Back
      </button>
    </div>
  </div>
</template>
