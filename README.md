# Praxleo Appointment Pro

A WordPress appointment booking plugin with a Vue administration dashboard and a public booking wizard. PHP handles data storage, availability, notifications, and payment integrations; Vue handles the admin and customer interfaces.

## Quick start

Place this folder at `wp-content/plugins/px-appointment-pro`, then install frontend dependencies and build both applications:

```powershell
cd src/admin
npm ci
npm run build:all
```

Activate **Praxleo Appointment Pro** in **WordPress > Plugins**, then open **Appointment Pro** in the admin sidebar. Configure locations, services, staff assignments, and working hours before accepting bookings.

Add this shortcode to a WordPress page:

```text
[px-appointment]
```

Alternatively, insert the **Appointment Pro** block in the WordPress block editor.

## Requirements

| Component | Requirement |
| --- | --- |
| WordPress | 6.0 or later, as declared in the plugin header |
| PHP | 8.1 or later |
| Node.js for development/builds | `^22.18.0` or `>=24.12.0`, matching `src/admin/package.json` |
| npm | Included with a supported Node.js installation |
| Composer | For installing PHP autoload files and development stubs |
| Python and Chrome/Chromium | Only needed for browser smoke tests |

Node.js and npm are build-time dependencies. A deployed WordPress site loads the compiled assets from `lib/` and does not need a running Vite server.

## Features

- Service-first public booking, with related location and category filters at the top of the catalog.
- Bookable services remain selectable when both filters are set to **All**. Selecting a service chooses its category and preserves a compatible location or selects its first assigned location in the configured order.
- Staff selection, including any available staff member; location selection has no separate booking step.
- Always-visible calendar on the date-and-time step. Dates without slots are disabled, and month navigation follows booking limits.
- Availability based on working hours, breaks, days off, existing appointments, service duration, padding, extras, and clinic holidays.
- Customer details, optional WordPress account creation, custom fields, coupons, and service extras.
- Local payments, Stripe card confirmation, PayPal checkout, and WooCommerce order integration. Stripe and PayPal store separate sandbox/live credentials and webhook settings.
- Dashboard statistics, weekly admin calendar, appointment statuses, customer records, and payment history.
- Management of locations, categories, services, staff, assignments, extras, coupons, and notification templates, including supported bulk deletion actions.
- Admin, Staff, and Customer notification tabs with configurable booking, approval, cancellation, and reminder templates, optional customer-email copies, and plain-text table previews.
- FullCalendar month calendar for named clinic holidays, optional yearly recurrence, and automatic closure of public booking dates.
- Loading skeletons in admin and public interfaces, with reduced-motion support.
- Medical demo content: 30 appointments, 30 services, 5 categories, 5 staff, 5 locations, 2 extras, 2 coupons, and 3 custom fields. Database reset and CSV export/import. Reset preserves notification templates.
- Shortcode and block embedding with optional presets; opt-in data removal on uninstall.

See [FEATURE-PARITY.md](FEATURE-PARITY.md) for the detailed reference feature comparison.

## Project structure

```text
px-appointment-pro/
|-- appointment-pro.php          WordPress plugin entry point
|-- inc/                         PHP domain, database, REST, admin, and payment code
|-- src/
|   |-- admin/
|   |   |-- package.json         Shared npm dependencies and build scripts
|   |   |-- vite.config.ts       Admin development/build configuration
|   |   |-- vite.public.config.ts Public production/watch configuration
|   |   `-- src/                 Admin Vue source
|   `-- public/                  Booking Vue source, store, and styles
|-- lib/
|   |-- admin/                   Compiled admin assets and lazy-loaded chunks
|   |-- public/                  Compiled public booking assets
|   `-- editor/                  WordPress booking block registration script
|-- tests/                       WordPress integration and browser smoke checks
|-- vendor/                      Composer dependencies, when installed
|-- composer.json                PHP dependencies and PSR-4 autoload configuration
`-- uninstall.php                Optional data cleanup
```

The local `praxleo-booking/` reference folder is excluded by `.gitignore` and is not required to run or distribute this plugin.

## Development setup

From the plugin root, install PHP dependencies when setting up a development checkout:

```powershell
composer install
```

The plugin includes a fallback autoloader for its own classes. Composer also supplies the WordPress development stubs declared in `composer.json`.

Install JavaScript dependencies from the existing lockfile:

```powershell
cd src/admin
npm ci
```

Both Vue applications share the dependencies installed in `src/admin/node_modules`. There is no separate npm installation required in `src/public`.

### Admin development with hot reload

Run from `src/admin`:

```powershell
npm run dev
```

Then open the **Appointment Pro** admin page in your local WordPress site. The plugin detects Vite at `http://127.0.0.1:5173` and loads the admin application from it, including hot module replacement.

The host and port must match `inc/Admin/AdminMenu.php` and `src/admin/vite.config.ts`. Detection is cached briefly; if you just started or stopped Vite, allow a few seconds and reload the page.

To force the admin to use compiled assets, add this before the WordPress bootstrap in `wp-config.php`:

```php
define( 'PX_APPOINTMENT_VITE_DEV', false );
```

For local development, setting it to `true` forces Vite assets and requires that server to be running. Remove the override to restore automatic detection. Set it to `false` on production sites if you want to disable detection explicitly.

### Public booking development

The public form uses the compiled assets enqueued by WordPress. Rebuild after editing `src/public`:

```powershell
# Run inside src/admin.
npm run build:public
```

Or keep the public build running in watch mode:

```powershell
npm run build:public -- --watch
```

Open the WordPress page containing `[px-appointment]` and refresh after a rebuild. This is a compiled-asset watch workflow; the current plugin does not provide public Vite hot reload. Admin hot reload and public watch can run in separate terminals.

Use the WordPress page for normal testing: it supplies the REST URL, nonce, PHP backend, and real booking data. Opening the public HTML entry directly does not supply that WordPress environment.

## Production builds

Run all npm commands below from `src/admin`:

| Command | Purpose |
| --- | --- |
| `npm run build:all` | Type-check/build admin, then build public booking |
| `npm run build` | Existing admin build, including its TypeScript check |
| `npm run build:public` | Public booking production build |
| `npm run build-only` | Admin Vite build without the separate type check |
| `npm run type-check` | Admin TypeScript/Vue type checking |
| `npm run dev` | Admin Vite development server |
| `npm run lint` | Run configured linters; these scripts apply fixes |
| `npm run format` | Format the admin `src/` directory |

The combined build runs sequentially and stops if either build fails:

```powershell
npm run build:all
```

From the plugin root, the equivalent command is:

```powershell
npm --prefix src/admin run build:all
```

Build outputs:

- Admin: `lib/admin/main.js`, `lib/admin/main.css`, and `lib/admin/chunk-*.js`.
- Public: `lib/public/public.js` and `lib/public/public.css`.
- Editor: `lib/editor/booking-block.js` is a directly loaded WordPress script and does not require compilation.

PHP files do not require compilation. Edit Vue/CSS source under `src/` and rebuild instead of editing generated bundles in `lib/admin` or `lib/public`.

### Create an installable ZIP on Windows

After installing dependencies with `npm ci` in `src/admin`, run this from the plugin root:

```powershell
.\create-plugin-zip.bat
```

The script runs `npm run build:all`, verifies the required runtime files, and writes:

```text
dist/px-appointment-pro-<version>.zip
```

The version comes from the `Version` header in `appointment-pro.php`. The archive contains a single `px-appointment-pro/` folder, ready for **WordPress > Plugins > Add New > Upload Plugin**. Rerunning it replaces the ZIP for the same version only after the new archive has been created and validated successfully.

Included: the plugin entry point, uninstall handler, PHP classes under `inc/`, built runtime assets under `lib/`, available translation files, and the selected root documentation/license files.

Excluded: Vue/TypeScript source, `node_modules`, tests, tools, reference plugin, Git/editor configuration, source maps, build configuration, Composer development files, and existing archives. The current plugin has no third-party production PHP packages, so the ZIP omits `vendor/` and uses the plugin's built-in autoloader. If production Composer dependencies are introduced, the script stops until packaging is updated to include them.

The script requires Windows PowerShell, Node.js, npm, and the frontend dependencies already installed. It works when the plugin path contains spaces, stops on build/package errors, and does not create or modify WordPress booking data. Generated archives are ignored by Git.

### Deploying the plugin

1. Run `npm ci` in `src/admin`, then `npm run build:all`.
2. If distributing Composer dependencies, run `composer install --no-dev --optimize-autoloader` from the plugin root for the production package.
3. Include `appointment-pro.php`, `inc/`, `lib/`, `uninstall.php`, and the production `vendor/` directory when used. Keep documentation and any translation resources you distribute.
4. Exclude local reference files, `node_modules`, `.git`, test fixtures, and local development configuration from the deployment package.
5. Copy the plugin folder to the target site's plugins directory, or zip it with `px-appointment-pro/` as the top-level folder and upload it through WordPress.
6. Activate the plugin and verify the admin screen and a published booking page using compiled assets.

The builds generate frontend assets; they do not create a plugin ZIP, configure WordPress, or deploy the site.

## Using the plugin

### Set up the booking catalog

1. Open **Appointment Pro > Settings** and set company details, timezone, currency, booking limits, reminder timing, and payment methods.
2. Create at least one **Location**.
3. Add **Categories** and **Services**, including prices, durations, and any padding.
4. Create **Staff**, assign each person to services at the appropriate locations, and save their weekly hours.
5. Add breaks and days off as needed. Optionally configure extras, coupons, and custom fields.
6. Review the notification templates and publish a booking page.

A service needs an enabled staff/service/location assignment to be bookable. Its dates also need working hours and available time within the configured booking window.

### Embed the booking form

Use the shortcode without presets to show the full catalog:

```text
[px-appointment]
```

Supply optional IDs to initialize location, service, or staff choices:

```text
[px-appointment location_id="1" service_id="2" staff_id="3"]
```

Presets initialize the selection; they do not replace catalog/staff configuration or bypass availability checks. The service-first page remains the starting point. Use the **Appointment Pro** block's preset controls for the equivalent block-based setup.

Use one full booking form embed per page. You can add multiple service booking buttons on the same page, including alongside the full form.

### Service booking button and modal

Use this shortcode for a button that books a specific service:

```text
[px-appointment-service id="2" text="Book Annual Health Examination"]
```

`id` is the service ID from the Services screen. `text` is the exact button label; when omitted, the service name is used. Missing or disabled services do not render a button.

Clicking opens a modal with the service already selected and **Staff** active as step 2. Compatible locations can be changed on that step when the service has multiple locations. The service stays fixed; staff, calendar availability, holidays, extras, customer details, custom fields, coupons, and payments use the normal booking flow.

Each button keeps separate booking state. Closing and reopening an unfinished modal resumes its progress, including a pending payment form. Reopening after confirmation starts a new booking. Modal steps do not change the page URL or the full booking form's state. Affiliate referral parameters on the page continue to be submitted with the booking.

### Customer flow

```text
Service > Staff > Date and time > Extras (when available) > Details > Payment > Confirmation
```

Location and category selectors appear only at the top of the service catalog and show related options. The calendar stays visible on the date-and-time step during availability loading and retries. Guests can book without an account and optionally request account creation in the details form.

### Payments and notifications

| Method | Setup |
| --- | --- |
| Local | Enable local payments in Settings; no online provider is required |
| Stripe | Enable Stripe, choose sandbox/live, and save the selected environment?s publishable key, secret key, and webhook signing secret |
| PayPal | Enable PayPal, choose sandbox/live, and save the selected environment?s client ID, secret, and webhook ID |
| WooCommerce | Install and activate WooCommerce, configure its checkout, and enable the integration |

Configure provider webhook URLs using your site's domain:

```text
https://your-site.example/wp-json/px-appointment/v1/webhooks/stripe
https://your-site.example/wp-json/px-appointment/v1/webhooks/paypal
```

Subscribe Stripe to `payment_intent.succeeded` and `payment_intent.payment_failed`, and PayPal to `PAYMENT.CAPTURE.COMPLETED`. Use matching sandbox/test or live credentials. Public payment requests use booking tokens, and payment totals come from the stored booking.

Both providers show only the credentials for the selected environment. Switching environments preserves the other profile; save Settings to activate the selection. Existing sandbox credentials are migrated to sandbox fields automatically. Public checkout uses only the active profile and displays a Sandbox badge in test mode. Configure and verify each environment?s webhook separately before taking bookings.

The **Notifications** screen separates **Admin**, **Staff**, and **Customer** templates. Each audience supports booking creation, approval, cancellation, and reminders. The body column shows up to 30 plain-text characters; editing opens the complete HTML template. Staff notifications go to the assigned staff member?s email. Admin notifications use the company email configured in Settings.

Under **Customer**, an optional custom email receives an additional copy of customer notifications. Enable **Send a copy to the admin email** to send those customer templates to the configured company email as well. Each recipient receives a separate message. Customer delivery remains enabled according to each template?s own Enabled setting.

Delivery uses WordPress mail. Reminders and expired hold cleanup run through the hourly WordPress cron hook `px_appointment_hourly`; ensure WordPress cron is running on the site.

### Business Hours and staff overrides

Open **Business Hours**, immediately above Settings, to configure default weekly shifts and breaks. Use **Add shift** or **Edit shift**, choose a weekday and optional location, set opening/closing times, and add breaks. Save the shift, then choose **Save business hours** to publish the weekly schedule. Days without shifts are closed; an empty business schedule supplies no availability.

Staff with no saved personal weekly hours inherit Business Hours. Once a staff member has any personal shifts, that personal schedule overrides the entire business week. Remove all personal shifts and save hours to restore inheritance. Location assignments, holidays, time off, existing bookings, and booking limits still apply to inherited hours.

### Affiliate Programs

Open **Affiliate Programs**, choose **Add affiliate**, and enter the partner name and the URL of a published booking page on this site. Each link receives a unique `px_aff` query parameter; existing URL parameters and booking page fragments are retained. Share or copy that generated link. Copy uses the browser Clipboard API with a selection-based fallback for local HTTP pages or blocked API access.

Bookings made in that link's booking flow store the referral code on the appointment. Counts include all attributed appointments, including pending and cancelled bookings. Click an affiliate name to expand customer, service, staff, date, status, and amount details. Disabling a link stops new attribution. Deleting a link clears its attribution while preserving the appointments and payments. Tracking uses the current booking page URL and does not add tracking cookies or a commission/payment system.

### Currency display

Select the currency under **Settings > Company Settings**. `PxCurrency` displays its symbol before every monetary amount with exactly two decimal places in both applications, including dashboard revenue, catalog prices, extras, checkout, payment history, and affiliate details. Payment rows retain their recorded currency; email price placeholders use the same symbol convention. Percentage coupons remain percentages.

### Reusable form modals

Administrative create/edit forms use **PxModal**, teleported to the document body. It supplies a close control, Cancel, a configurable primary action, native form validation, focus trapping/restoration, background locking, and nested dialogs. Settings and recipient preferences remain persistent configuration forms.

For development, import `src/admin/src/components/PxModal.vue` with `open`, `heading`, `primaryText`, `busy`, and `formId` props. Put the form in its default slot, give it the matching ID, and handle `close`. The primary footer button submits that form. `submit` can instead handle an action without `formId`; `hideFooter` allows a custom footer flow. `keepMounted` preserves a hidden modal?s contents, including in-progress payment forms. Shared `src/shared/PxCurrency.vue` accepts `amount` and optional `currency` props, using the provided configured currency when omitted.

### Clinic holidays

Open **Holiday** in the admin sidebar. The FullCalendar month view opens by default. Click a date (or **Add holiday**), then enter a name, description, and date. **Repeat yearly** and **Block bookings** are enabled by default. Turn off yearly recurrence for a one-time closure, or disable blocking to retain a holiday record without closing the date.

Saved holidays apply to every location in the company timezone. Customers cannot select these dates, and the server also rejects new bookings or reschedules that overlap a closure. Existing appointments are retained. Click a calendar event or **Edit** in the holiday table to change it; **Delete** uses the shared confirmation popup. Holidays, Business Hours, and affiliate links/attribution are included in CSV transfers and cleared by database reset/demo replacement.

### Database tools and uninstall

The **Database** screen provides CSV export/import, demo insertion, and reset. Import replaces tables present in the uploaded export; demo insertion replaces current booking content. Export a backup before using these replacement operations. Reset keeps notification templates. Demo insertion creates 30 appointments, 30 medical services, 5 medical categories, 5 doctors, 5 locations, 2 extras, 2 coupons, and 3 custom fields. It does not send notifications or create WordPress user accounts.

Deactivation stops the scheduled maintenance event. Uninstall retains booking data by default; enable **Delete data on uninstall** in Settings only when you want the plugin's tables and primary options removed.

## Verification

Run from the plugin root after building:

```powershell
php tests/integration.php
python tests/browser-smoke.py
```

- The CLI-only integration suite expects this plugin to be inside a local WordPress installation. It uses temporary booking tables, intercepts mail, mocks provider requests, and restores changed settings after the run.
- Browser smoke tests serve local fixtures against the compiled bundles and use mock API responses. They cover the public booking flow, linked filters, automatic selection, calendar availability, loading/error recovery, and admin screens.
- If Chrome is installed elsewhere, set its executable path before running the browser tests:

```powershell
$env:PX_CHROME = 'C:\path\to\chrome.exe'
python tests/browser-smoke.py
```

Live payments, actual email delivery, and WooCommerce checkout also need verification in the configured site/provider environments; mocked tests do not exercise those external systems.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| `Missing script: build:all` | Run inside `src/admin`, or use `npm --prefix src/admin run build:all` from the plugin root |
| Missing Vite or Vue packages | Run `npm ci` inside `src/admin` with a supported Node.js version |
| Public changes do not appear | Rebuild/watch the public application, refresh the booking page, and clear any page/CDN cache |
| Admin tries to load an unavailable dev server | Stop the forced dev override or set `PX_APPOINTMENT_VITE_DEV` to `false`, then refresh |
| Services cannot be booked | Check enabled services, locations, staff, and service/location assignments |
| Calendar has no enabled dates | Check staff hours, breaks/days off, existing appointments, timezone, notice limits, booking horizon, clinic holidays, and extra duration |
| Online payment does not confirm | Check enabled methods, matching provider credentials, webhook URLs/secrets, and provider logs |
| Reminders or emails do not arrive | Check notification settings, WordPress mail delivery, and WordPress cron |

## Third-party notices

The Holiday calendar uses the MIT-licensed FullCalendar packages. See [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md) for their license notices, included in production ZIPs.
