<script setup>
import BookingSkeleton from "../components/BookingSkeleton.vue";
import { nextTick, onBeforeUnmount, ref } from "vue";
import { useRouter } from "vue-router";
import { useBooking } from "../store";
import { api } from "../api";
const store = useBooking();
const router = useRouter();
const cardHost = ref(null);
const paypalHost = ref(null);
const confirming = ref(false);
const providerLoading = ref(false);
const cardReady = ref(false);
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
  providerLoading.value = true;
  cardReady.value = false;
  try {
    await nextTick();
    if (method === "stripe") {
      const Stripe = await loadScript("https://js.stripe.com/v3/", "Stripe");
      stripe = Stripe(store.config.stripe_publishable);
      card?.destroy();
      card = stripe.elements().create("card");
      card.mount(cardHost.value);
      cardReady.value = true;
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
            confirming.value = true;
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
            } finally {
              confirming.value = false;
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
  } finally {
    providerLoading.value = false;
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
    <p>Total: <PxCurrency :amount="store.subtotal" /></p>
    <BookingSkeleton
      v-if="store.loading || confirming || providerLoading"
      variant="form"
      :label="
        providerLoading
          ? 'Loading secure payment form'
          : 'Processing booking payment'
      "
      embedded
    />
    <p v-if="store.error" class="pxb-err" role="alert">{{ store.error }}</p>
    <button
      v-for="method in ['local', 'stripe', 'paypal', 'woocommerce'].filter(
        (m) => store.config[m + '_enabled'],
      )"
      :key="method"
      class="pxb-choice"
      :disabled="store.loading || confirming || providerLoading"
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
      <small
        v-if="
          ['stripe', 'paypal'].includes(method) &&
          store.config[method + '_sandbox']
        "
        class="pxb-sandbox-badge"
        >Sandbox</small
      >
    </button>
    <p
      v-if="
        (store.config.stripe_sandbox && store.config.stripe_enabled) ||
        (store.config.paypal_sandbox && store.config.paypal_enabled)
      "
      class="pxb-muted"
    >
      Sandbox payment methods use provider test credentials.
    </p>
    <div v-if="store.result?.pending === 'stripe'">
      <div ref="cardHost" class="pxb-card"></div>
      <button
        class="pxb-btn"
        :disabled="confirming || !cardReady || providerLoading"
        @click="confirmCard"
      >
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
        :disabled="store.loading || confirming || providerLoading"
        @click="router.push('/details')"
      >
        Back
      </button>
    </div>
  </div>
</template>
