# Appointment Pro feature parity

The bundled `praxleo-booking` folder is the reference. Its plugin files remain unchanged. The implementation uses Appointment Pro's namespace, `px_appointment_*` tables/options, and existing blue, pink and teal UI palette.

| Reference functionality | Appointment Pro implementation |
| --- | --- |
| Locations, categories, services, descriptions, prices, duration and padding | Existing admin catalog retained; missing catalog fields exposed |
| Staff and service/location assignments | Existing forms retained; per-assignment prices exposed |
| Weekly hours, breaks, days off | Nested breaks survive schedule edits; time-off controls added |
| Availability, notice limits, booking horizon, conflicts and holds | Existing availability engine retained; public creation validates the offered slot |
| Public booking wizard | Service-first catalog with location/category filters; mutually filtered location/category fields above the catalog without a location step; `src/public`, compiled into `lib/public` |
| Shortcode with location/service/staff presets | `[px-appointment location_id="1" service_id="2" staff_id="3"]`; omit presets for customer selection |
| Gutenberg booking block | Appointment Pro block with preset controls |
| Extras and quantity limits | Public extras step; duration rechecked before continuing |
| Coupon validation and discounts | Public coupon entry and existing domain validation |
| Custom fields and service targeting | Field options/service IDs exposed in admin; typed public inputs and required validation |
| Guest customers and optional WordPress accounts | Existing customer/account domain used by the public details step |
| Appointment creation, statuses, rescheduling, calendar | Existing admin flows retained; token lookup/cancellation and deletion added |
| Catalog/staff/appointment bulk deletion | Protected API endpoints and selection controls |
| Local payment, Stripe, PayPal, WooCommerce | Provider handlers and WooCommerce hooks added; Stripe card confirmation and PayPal buttons implemented |
| Email templates, placeholders, booking/cancellation mail, reminders | Existing mailer and hourly maintenance retained; cancellation links connected |
| Dashboard and payment history | Existing admin screens retained |
| Demo data, reset, CSV export/import | Database screen; all 20 booking tables, including holidays, business hours, and affiliates, supported; reset retains templates |
| Documentation | Documentation screen with current shortcode and webhook paths |
| Optional uninstall cleanup | Data retained unless deletion is enabled in Settings |

## Payment setup

Configure enabled providers in Settings. Stripe and PayPal keep sandbox and live keys and webhook credentials separately; public checkout resolves only the selected environment. Stripe requires its publishable/secret keys and webhook signing secret. Use `/wp-json/px-appointment/v1/webhooks/stripe` for payment intent success/failure events. PayPal requires its client ID/secret and webhook ID; use `/wp-json/px-appointment/v1/webhooks/paypal`. WooCommerce requires an active WooCommerce installation.

Webhook verification follows the provider references: [Stripe signature verification](https://docs.stripe.com/webhooks/signature) and [PayPal webhooks](https://developer.paypal.com/api/rest/webhooks/rest/).

Payment amounts are stored with the booking. Public payment requests require its booking token. Provider callbacks are authenticated and repeated callbacks do not duplicate completed payments.

## Verification

From the plugin directory:

```powershell
npm --prefix src/admin run build
npm --prefix src/admin run build:public
php tests/integration.php
python tests/browser-smoke.py
```

The PHP integration suite loads the local WordPress installation, uses temporary booking tables, intercepts mail, mocks provider HTTP responses, and restores settings after the run. It is CLI-only. Browser fixtures exercise the compiled public/admin assets against mock APIs. Set `PX_CHROME` if Chrome is installed in a different location.

Live Stripe/PayPal transactions, actual email delivery, and WooCommerce checkout require verification in the configured site/provider environments. The automated checks do not charge a card or modify live booking records.

The public catalog includes location IDs for enabled staff/service assignments. Loading skeletons cover initial catalog loading, staff options, availability, coupon checks, and payment processing; animations respect reduced-motion preferences.

The date-and-time step uses an always-visible month calendar. A monthly availability endpoint returns actual slot counts, including staff schedules, days off, existing bookings, extras, and booking limits. Dates with zero slots are disabled, and month navigation stays within the booking window.

Bookable service cards remain enabled with both filters set to All. Selecting a service automatically selects its category and retains a compatible selected location, falling back to the first assigned location in the configured location order.

## Extended administration features

- Holiday: FullCalendar month view with required name, description, and date, yearly recurrence, edit/delete, and booking availability enforcement in the company timezone.
- Notifications: Admin, Staff, and Customer tabs; four event types per audience; additional customer email and optional admin copies; plain-text previews limited to 30 characters with complete HTML editing.
- Medical demo: 30 appointments, 30 services, 5 categories, 5 staff, 5 locations, 2 extras, 2 coupons, and 3 custom fields.

- Business Hours: default weekly shifts and breaks for staff without personal hours, optional locations, and complete personal-week overrides.
- Affiliate Programs: unique booking page links, persisted appointment attribution, expandable booking details, enable/disable controls, and deletion that preserves bookings.
- Shared administration form modals and shared symbol-first currency formatting with two decimals across admin, booking, and email amounts.
