<template>
    <div class="px-card px-docs">
        <div ref="content" class="px-docs-content">
            <h3 class="mt-0">What this plugin does</h3>
            <p>
                Appointment Pro lets customers book appointments on your WordPress site. You manage locations, services,
                staff, extras, coupons, and payments from this admin screen.
            </p>

            <div class="px-anim-fadein" style="--px-table-row-index: 1">
                <h3>1. Put the booking form on a page</h3>
                <p>Add the shortcode to any page or post:</p>
                <pre>[px-appointment]</pre>
                <p>Or insert the Gutenberg block named <strong>Appointment Pro</strong>.</p>
                <p>For a button that opens a specific service in a booking modal, use:</p>
                <pre>[px-appointment-service id="2" text="Book Annual Health Examination"]</pre>
                <p>
                    The text attribute sets the button label; omit it to use the service name. The modal starts at Staff
                    (step 2), keeps the selected service fixed, and continues through the normal booking steps. Multiple
                    service buttons can share a page with the full form. Closing an unfinished modal preserves progress;
                    reopening after confirmation starts a new booking.
                </p>
            </div>

            <div class="px-anim-fadein" style="--px-table-row-index: 2">
                <h3>2. Set up your catalog</h3>
                <ol>
                    <li>Create <strong>Locations</strong> (at least one).</li>
                    <li>Create <strong>Categories</strong>, then <strong>Services</strong> with duration and price.</li>
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
                    The public wizard starts with all enabled services and provides location and category filters.
                    Customers can book a service at locations with an enabled staff assignment and available hours.
                </p>
            </div>

            <div class="px-anim-fadein" style="--px-table-row-index: 3">
                <h3>3. Public booking steps</h3>
                <p>Service &rarr; Staff &rarr; Time &rarr; Extras &rarr; Details &rarr; Payment &rarr; Done.</p>
                <p>
                    Guests can book without a WordPress account. Optional account creation is available on the details
                    step.
                </p>

                <p>
                    Use the location and category filters above the services. Choosing either filter shows only related
                    options in the other. Selecting a service automatically selects its category and keeps the chosen
                    location, or uses its first configured location when All locations is selected. There is no separate
                    location step. The date-and-time step includes a visible calendar; dates without available slots,
                    including clinic holidays, are disabled.
                </p>
            </div>

            <div class="px-anim-fadein" style="--px-table-row-index: 4">
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
                    <code>/wp-json/px-appointment/v1/webhooks/stripe</code> with payment intent success and failure
                    events, then save its signing secret in Settings.
                </p>
                <p>
                    For PayPal, configure <code>/wp-json/px-appointment/v1/webhooks/paypal</code> and save its webhook
                    ID. Test using sandbox credentials before switching to live payments.
                </p>
                <p>
                    Stripe and PayPal each have a sandbox toggle inside their enabled section. Sandbox and live
                    credentials, including webhook secrets or IDs, are saved separately. Switching the toggle shows that
                    environment's fields; save Settings to activate it. Checkout uses the active credentials and shows a
                    Sandbox badge in test mode.
                </p>
            </div>

            <div class="px-anim-fadein" style="--px-table-row-index: 5">
                <h3>5. Appointments, holidays, and notifications</h3>
                <p>
                    Use <strong>Appointments</strong> to add bookings, approve, cancel, or mark done. Use
                    <strong>Calendar</strong> for a week view. Email templates live under
                    <strong>Notifications</strong> in Admin, Staff, and Customer tabs. All audiences support creation,
                    approval, cancellation, and reminder events. Table previews show 30 plain-text characters; editing
                    shows the full HTML body. The Customer tab also offers an additional custom email and an optional
                    copy to the company/admin email.
                </p>

                <p>
                    Open <strong>Holiday</strong> above Settings for the month calendar. Select a date, then enter a
                    name, description, and date. Yearly recurrence and booking closure are enabled by default. You can
                    edit or delete holidays using the calendar or table. Closed dates apply across locations in the
                    company timezone; existing appointments remain unchanged.
                </p>
                <h3>Business Hours, affiliates, and currency</h3>
                <p>
                    Use <strong>Business Hours</strong> above Settings to configure default weekly shifts and breaks.
                    Save each shift and then save business hours. Staff without personal hours inherit this schedule; a
                    personal schedule overrides the entire business week. Holidays and time off still close dates.
                </p>
                <p>
                    Under <strong>Affiliate Programs</strong>, create a named link to a booking page on this site. Share
                    its generated referral URL, then expand the affiliate name to see attributed appointments. Counts
                    include pending and cancelled bookings. Disabling stops new attribution; deleting retains
                    appointments.
                </p>
                <p>
                    Select a currency in <strong>Settings</strong>. Monetary amounts show its symbol followed by two
                    decimals. Create/edit forms open in reusable dialogs with close, Cancel, and save controls.
                </p>
                <p>
                    Select fields use <strong>PxSelect</strong>: choose with the mouse or keyboard, type to find an
                    option, and use Escape to dismiss the list. Long lists scroll within a 300px limit and open above
                    the control when space below is limited. Required selections still validate before saving.
                </p>
            </div>

            <div class="px-anim-fadein" style="--px-table-row-index: 6">
                <h3>6. Demo data</h3>
                <p>
                    Open <strong>Database</strong> to reset booking tables or insert medical demo content: 30
                    appointments, 30 services, 5 categories, 5 staff, 5 locations, 2 extras, 2 coupons, and 3 custom
                    fields. Demo insertion replaces booking content, including holidays. Export a backup first. Reset
                    does not remove notification templates.
                </p>
            </div>

            <div class="px-anim-fadein" style="--px-table-row-index: 7">
                <h3>7. Move data between sites</h3>
                <p>
                    On <strong>Database</strong>, enable <strong>Admin cache</strong> to speed up repeat visits to
                    admin pages. Changes refresh cached data automatically. Use <strong>Purge cache</strong> to
                    reload fresh data on the next visit. Frontend booking availability always stays live.
                </p>
                <p>
                    On <strong>Database</strong>, use <strong>Export CSV</strong> on the source site, then
                    <strong>Import CSV</strong> on the destination. The file includes locations, services, staff,
                    schedules, customers, appointments, extras, coupons, custom fields, holidays, and notification
                    templates.
                </p>
            </div>
        </div>
    </div>
</template>


<style scoped>
  pre{

    background-color: #272822;
    color: hsl(152, 69%, 70%);
    font-family: 'Courier New', Courier, monospace;
    font-size: 14px;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    max-width: 720px;
  }
</style>
