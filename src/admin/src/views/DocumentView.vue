<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const content = ref(null)
const isTyping = ref(false)
const millisecondsPerCharacter = 2
let typingTimer = 0
let textEntries = []
let hiddenElements = []
let cursor = null
let motionPreference = null

function showAll() {
  window.clearTimeout(typingTimer)
  for (const entry of textEntries) entry.node.data = entry.original
  for (const { element, hidden } of hiddenElements) element.hidden = hidden
  cursor?.remove()
  cursor = null
  textEntries = []
  hiddenElements = []
  isTyping.value = false
}

function handleMotionChange(event) {
  if (event.matches) showAll()
}

onMounted(() => {
  motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)')
  motionPreference.addEventListener('change', handleMotionChange)
  if (motionPreference.matches) return

  const root = content.value
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  const segmenter =
    typeof Intl.Segmenter === 'function'
      ? new Intl.Segmenter(undefined, { granularity: 'grapheme' })
      : null
  let node
  while ((node = walker.nextNode())) {
    if (!node.data.trim()) continue
    const original = node.data
    const characters = segmenter
      ? Array.from(segmenter.segment(original), (part) => part.segment)
      : Array.from(original)
    textEntries.push({ node, original, characters, revealed: 0 })
  }
  if (!textEntries.length) return

  hiddenElements = Array.from(root.querySelectorAll('*'), (element) => ({
    element,
    hidden: element.hidden,
  }))
  for (const entry of textEntries) entry.node.data = ''
  for (const { element } of hiddenElements) element.hidden = true
  cursor = document.createElement('span')
  cursor.className = 'px-docs-cursor'
  cursor.setAttribute('aria-hidden', 'true')
  isTyping.value = true

  let currentEntry = 0
  let revealedTotal = 0
  const startedAt = performance.now()
  function typeFrame(now = performance.now()) {
    let remaining = Math.floor((now - startedAt) / millisecondsPerCharacter) - revealedTotal
    while (remaining > 0 && currentEntry < textEntries.length) {
      const entry = textEntries[currentEntry]
      const amount = Math.min(remaining, entry.characters.length - entry.revealed)
      entry.revealed += amount
      entry.node.data = entry.characters.slice(0, entry.revealed).join('')
      let parent = entry.node.parentElement
      while (parent && parent !== root) {
        parent.hidden = false
        parent = parent.parentElement
      }
      entry.node.parentNode.insertBefore(cursor, entry.node.nextSibling)
      remaining -= amount
      revealedTotal += amount
      if (entry.revealed === entry.characters.length) currentEntry++
    }
    if (currentEntry === textEntries.length) showAll()
    else typingTimer = window.setTimeout(typeFrame, 16)
  }
  typingTimer = window.setTimeout(typeFrame, 16)
})

onBeforeUnmount(() => {
  window.clearTimeout(typingTimer)
  cursor?.remove()
  motionPreference?.removeEventListener('change', handleMotionChange)
})
</script>

<template>
  <div class="px-card px-docs">
    <div class="px-docs-toolbar">
      <span class="px-muted" role="status">{{
        isTyping ? 'Displaying documentation...' : 'Setup and usage guide'
      }}</span>
      <button v-if="isTyping" type="button" class="px-button px-button-ghost" @click="showAll">
        Show all
      </button>
    </div>
    <div ref="content" class="px-docs-content" :aria-busy="isTyping">
      <h3>What this plugin does</h3>
      <p>
        Appointment Pro lets customers book appointments on your WordPress site. You manage
        locations, services, staff, extras, coupons, and payments from this admin screen.
      </p>

      <h3>1. Put the booking form on a page</h3>
      <p>Add the shortcode to any page or post:</p>
      <pre>[px-appointment]</pre>
      <p>Or insert the Gutenberg block named <strong>Appointment Pro</strong>.</p>

      <h3>2. Set up your catalog</h3>
      <ol>
        <li>Create <strong>Locations</strong> (at least one).</li>
        <li>
          Create <strong>Categories</strong>, then <strong>Services</strong> with duration and
          price.
        </li>
        <li>
          Add <strong>Staff</strong>, then assign weekly hours and which services they offer at
          which location.
        </li>
        <li>
          Optional: add <strong>Extras</strong>, <strong>Coupons</strong>, and
          <strong>Custom fields</strong>.
        </li>
      </ol>
      <p>
        The public wizard starts with all enabled services and provides location and category
        filters. Customers can book a service at locations with an enabled staff assignment and
        available hours.
      </p>

      <h3>3. Public booking steps</h3>
      <p>
        Service &rarr; Staff &rarr; Time &rarr; Extras &rarr; Details &rarr; Payment &rarr; Done.
      </p>
      <p>
        Guests can book without a WordPress account. Optional account creation is available on the
        details step.
      </p>

      <p>
        Use the location and category filters above the services. Choosing either filter shows only
        related options in the other. Selecting a service automatically selects its category and
        keeps the chosen location, or uses its first configured location when All locations is
        selected. There is no separate location step. The date-and-time step includes a visible
        calendar; dates without available slots, including clinic holidays, are disabled.
      </p>
      <h3>4. Payments</h3>
      <p>Open <strong>Settings</strong> and enable the methods you need:</p>
      <ul>
        <li><strong>Local</strong> — pay at the venue. Works without WooCommerce.</li>
        <li><strong>Stripe</strong> — card payment (publishable and secret keys).</li>
        <li><strong>PayPal</strong> — PayPal Checkout (client id and secret).</li>
        <li><strong>WooCommerce</strong> — only if WooCommerce is installed.</li>
      </ul>

      <p>
        For Stripe, configure the webhook URL
        <code>/wp-json/px-appointment/v1/webhooks/stripe</code> with payment intent success and
        failure events, then save its signing secret in Settings.
      </p>
      <p>
        For PayPal, configure <code>/wp-json/px-appointment/v1/webhooks/paypal</code> and save its
        webhook ID. Test using sandbox credentials before switching to live payments.
      </p>
      <p>
        Stripe and PayPal each have a sandbox toggle inside their enabled section. Sandbox and live
        credentials, including webhook secrets or IDs, are saved separately. Switching the toggle
        shows that environment's fields; save Settings to activate it. Checkout uses the active
        credentials and shows a Sandbox badge in test mode.
      </p>
      <h3>5. Appointments, holidays, and notifications</h3>
      <p>
        Use <strong>Appointments</strong> to add bookings, approve, cancel, or mark done. Use
        <strong>Calendar</strong> for a week view. Email templates live under
        <strong>Notifications</strong> in Admin, Staff, and Customer tabs. All audiences support
        creation, approval, cancellation, and reminder events. Table previews show 30 plain-text
        characters; editing shows the full HTML body. The Customer tab also offers an additional
        custom email and an optional copy to the company/admin email.
      </p>

      <p>
        Open <strong>Holiday</strong> above Settings for the month calendar. Select a date, then
        enter a name, description, and date. Yearly recurrence and booking closure are enabled by
        default. You can edit or delete holidays using the calendar or table. Closed dates apply
        across locations in the company timezone; existing appointments remain unchanged.
      </p>
      <h3>6. Demo data</h3>
      <p>
        Open <strong>Database</strong> to reset booking tables or insert medical demo content: 30
        appointments, 30 services, 5 categories, 5 staff, 5 locations, 2 extras, 2 coupons, and 3
        custom fields. Demo insertion replaces booking content, including holidays. Export a backup
        first. Reset does not remove notification templates.
      </p>

      <h3>7. Move data between sites</h3>
      <p>
        On <strong>Database</strong>, use <strong>Export CSV</strong> on the source site, then
        <strong>Import CSV</strong> on the destination. The file includes locations, services,
        staff, schedules, customers, appointments, extras, coupons, custom fields, holidays, and
        notification templates.
      </p>
    </div>
  </div>
</template>

<style scoped>
.px-docs-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  min-height: 34px;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--px-line);
}
.px-docs-content :deep([hidden]) {
  display: none !important;
}
.px-docs-content :deep(.px-docs-cursor) {
  display: inline-block;
  width: 2px;
  height: 1em;
  margin-left: 2px;
  vertical-align: -0.12em;
  background: var(--px-primary);
  animation: px-docs-cursor-blink 1s step-end infinite;
}
@keyframes px-docs-cursor-blink {
  50% {
    opacity: 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .px-docs-content :deep(.px-docs-cursor) {
    animation: none;
  }
}
</style>
