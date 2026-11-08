<template>
  <div class="px-card px-docs">
    <h3>What this plugin does</h3>
    <p>
      Appointment Pro lets customers book appointments on your WordPress site. You manage locations,
      services, staff, extras, coupons, and payments from this admin screen.
    </p>

    <h3>1. Put the booking form on a page</h3>
    <p>Add the shortcode to any page or post:</p>
    <pre>[px-appointment]</pre>
    <p>Or insert the Gutenberg block named <strong>Appointment Pro</strong>.</p>

    <h3>2. Set up your catalog</h3>
    <ol>
      <li>Create <strong>Locations</strong> (at least one).</li>
      <li>
        Create <strong>Categories</strong>, then <strong>Services</strong> with duration and price.
      </li>
      <li>
        Add <strong>Staff</strong>, then assign weekly hours and which services they offer at which
        location.
      </li>
      <li>
        Optional: add <strong>Extras</strong>, <strong>Coupons</strong>, and
        <strong>Custom fields</strong>.
      </li>
    </ol>
    <p>
      The public wizard only shows a service if a staff member is linked to that service at the
      chosen location and has hours that day.
    </p>

    <h3>3. Public booking steps</h3>
    <p>Location &rarr; Service &rarr; Staff &rarr; Time &rarr; Extras &rarr; Details &rarr; Payment &rarr; Done.</p>
    <p>
      Guests can book without a WordPress account. Optional account creation is available on the
      details step.
    </p>

    <h3>4. Payments</h3>
    <p>Open <strong>Settings</strong> and enable the methods you need:</p>
    <ul>
      <li><strong>Local</strong> — pay at the venue. Works without WooCommerce.</li>
      <li><strong>Stripe</strong> — card payment (publishable and secret keys).</li>
      <li><strong>PayPal</strong> — PayPal Checkout (client id and secret).</li>
      <li><strong>WooCommerce</strong> — only if WooCommerce is installed.</li>
    </ul>

    <p>For Stripe, configure the webhook URL <code>/wp-json/px-appointment/v1/webhooks/stripe</code> with payment intent success and failure events, then save its signing secret in Settings.</p>
<p>For PayPal, configure <code>/wp-json/px-appointment/v1/webhooks/paypal</code> and save its webhook ID. Test using sandbox credentials before switching to live payments.</p>
<h3>5. Appointments</h3>
    <p>
      Use <strong>Appointments</strong> to add bookings, approve, cancel, or mark done. Use
      <strong>Calendar</strong> for a week view. Email templates live under
      <strong>Notifications</strong> (edit only; they are not deleted).
    </p>

    <h3>6. Demo data</h3>
    <p>
      Open <strong>Database</strong> to reset booking tables or insert sample locations, services,
      staff, and appointments. Reset does not remove notification templates.
    </p>

    <h3>7. Move data between sites</h3>
    <p>
      On <strong>Database</strong>, use <strong>Export CSV</strong> on the source site, then
      <strong>Import CSV</strong> on the destination. The file includes locations, services, staff,
      schedules, customers, appointments, extras, coupons, custom fields, and notification
      templates.
    </p>
  </div>
</template>
