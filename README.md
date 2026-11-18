# YCC Hub

Club management for the Young Conservationist Club (YCC), Universiti Malaysia Terengganu.

- Public landing page with upcoming programs, a live Instagram feed and the LinkedIn link
- Self-registration with exco approval (email on approve/reject)
- Programs with capacity, registration deadline, posters, drafts and cancellation
- Attendance by tap-to-mark sheet or QR self check-in, walk-ins included
- Activity status (New / Active / At-Risk / Inactive) with semester-break pauses
- Exco dashboard, member list with WhatsApp links, CSV export (logged for PDPA)
- Admin analytics: every member listed by status, members about to slip, breakdown by faculty and year, 12-month attendance trend, most engaged members
- Admin: roles, suspend/reinstate, social links, Instagram token, export log

Stack: Laravel 13, PHP 8.3+, MySQL 8 / MariaDB 10.4+, Blade + Alpine.js, Tailwind CSS 4 (built with Vite).

---

## 1. Roles

| Role | Can do |
|------|--------|
| member | Portal, register/cancel programs, QR check-in, edit profile |
| exco | Everything a member can, plus approvals, programs, attendance, dashboard, CSV export |
| admin | Everything an exco can, plus analytics, roles, suspend accounts, delete programs, settings, export log, edit locked attendance |

## 2. Activity status rules

Set in `.env` (defaults shown). "Days" means counted days: calendar days minus any semester breaks added in **Admin > Settings**.

| Status | Rule |
|--------|------|
| New | Approved within `YCC_GRACE_DAYS` (60) and has not attended yet |
| Active | Last attended within `YCC_ACTIVE_DAYS` (60) |
| At-Risk | Last attended 61 to `YCC_AT_RISK_DAYS` (90) days ago |
| Inactive | Last attended more than 90 days ago, or never attended after the grace period |

Only programs that are published (not cancelled, not deleted) and have "Counts toward activity status" ticked are counted.

The status is stored on `users.activity_status` so every screen shows the same value. It is recalculated:
- for one member, right after their attendance changes
- for everyone, nightly at 00:15 (scheduler)
- for everyone, when an admin adds or removes a semester break

Logic: `app/Services/ActivityStatusService.php`. Tests: `tests/Unit/ActivityStatusTest.php`.

---

## 3. Install on conservems.my, step by step

No SSH needed. You build on your laptop, upload one zip, then finish in the browser.
Replace `cpuser` below with your real cPanel username (shown at the top right of cPanel).

### Part A: on your laptop

**Step 1. Get PHP 8.3 and Composer.**
On Windows, Laragon or XAMPP with PHP 8.3 works. Check in a terminal:
```
php -v          # must say 8.3 or newer
composer -V
```

**Step 2. Unzip `ycc-hub.zip`** somewhere, and open a terminal inside the `ycc-hub` folder.

**Step 3. Install the PHP packages** (this creates the `vendor` folder):
```
composer install --no-dev --optimize-autoloader
```
`public/build` (CSS/JS) is already included, so you don't need npm unless you change the styles.

**Step 4. Generate an app key** and keep it somewhere for Step 11:
```
php artisan key:generate --show
```
It prints something like `base64:AbC...=`. Copy the whole thing.

**Step 5. Zip the folder again**, this time with `vendor` inside. Leave out `node_modules` and any `.env`.
Name it `ycc-hub.zip`. The zip should contain one top folder called `ycc-hub`.

### Two ways to put it on the server

| | Subfolder (default) | Subdomain |
|--|--|--|
| URL | `https://conservems.my/ycc-hub` | `https://ycc.conservems.my` |
| Files go in | `public_html/ycc-hub` | `public_html/ycc-hub` (or outside public_html) |
| Extra cPanel step | none | create the subdomain with document root `public_html/ycc-hub/public` |
| `.env` | `APP_URL=https://conservems.my/ycc-hub`, `SESSION_PATH=/ycc-hub` | `APP_URL=https://ycc.conservems.my`, `SESSION_PATH=/` |

Both are tested on Apache with a catch-all Node proxy rule in `public_html/.htaccess`, like conservems.my has.
The root `.htaccess` and `public/.htaccess` both contain `RewriteOptions IgnoreInherit`, so that proxy rule doesn't hijack the app.
The root `.htaccess` also blocks `.env`, `vendor`, `storage`, the source code and the database files from being downloaded.
**Don't delete the `.htaccess` file in the `ycc-hub` folder.**

The steps below use the subfolder. For a subdomain, see Step 9.

### Part B: in cPanel

**Step 6. Check PHP version.** cPanel > **MultiPHP Manager**. You'll set the subdomain to PHP 8.3 in Step 9.

**Step 7. Create the database.** cPanel > **MySQL Database Wizard**:
1. Database name: `ycchub` (cPanel makes it `cpuser_ycchub`).
2. User: `ycchub` with a strong password (becomes `cpuser_ycchub`). Save the password.
3. Privileges: tick **ALL PRIVILEGES**, then Next.

**Step 8. Upload the app.** cPanel > **File Manager**:
1. Open `public_html`.
2. Upload `ycc-hub.zip`, then right-click > **Extract**. You should now have `public_html/ycc-hub` with `app`, `public`, `vendor`, `index.php` and `.htaccess` inside.
3. Delete the uploaded zip.
4. Settings (top right) > tick **Show Hidden Files**, and check `public_html/ycc-hub/.htaccess` is there.

**Step 9. Set PHP 8.3.** cPanel > **MultiPHP Manager**: the app needs PHP 8.3 or newer.
If conservems.my itself is on an older PHP and you can't change it, use the subdomain option instead: **Domains** > **Create A New Domain** > `ycc.conservems.my`, untick "Share document root", document root `public_html/ycc-hub/public`, set that subdomain to PHP 8.3, run **AutoSSL**, and use the subdomain values in `.env`.

**Step 10. Create the email sender (for approval and password emails).** cPanel > **Email Accounts** > Create `noreply@conservems.my` and save its password.

**Step 11. Create `.env`.** In File Manager:
1. Settings (top right) > tick **Show Hidden Files (dotfiles)**.
2. Open `public_html/ycc-hub`, copy `.env.example` and name the copy `.env`.
3. Right-click `.env` > **Edit** and set:
```
APP_KEY=base64:...           (from Step 4)
APP_URL=https://conservems.my/ycc-hub
SESSION_PATH=/ycc-hub
DB_DATABASE=cpuser_ycchub
DB_USERNAME=cpuser_ycchub
DB_PASSWORD=...              (from Step 7)
MAIL_PASSWORD=...            (from Step 10)
INSTALL_KEY=pick-any-long-random-text
```
Leave `APP_ENV=production` and `APP_DEBUG=false`.

**Step 12. Check folder permissions.** These must be writable (755 is normally fine on cPanel):
`storage` (and everything inside), `bootstrap/cache`, `public/uploads`.
Right-click > **Change Permissions** if needed.

**Step 13. Run the installer.** Open `https://conservems.my/ycc-hub/install`:
1. It should say "Connected to database". If not, re-check Step 7 and Step 11.
2. Enter the `INSTALL_KEY`, your admin name, matric/staff number, email, phone and a password (10+ characters).
3. Leave **Add demo data** unticked for the real launch (tick it only on a test copy).
4. Click **Install**. It creates the tables and logs you in as admin.

**Step 14. Lock it.** Edit `.env` again and **delete the `INSTALL_KEY` line**. The installer is already disabled, this is just extra safety.

**Step 15. Set up the cron job.** cPanel > **Cron Jobs**:
- Common Settings: **Once Per Minute** (`* * * * *`)
- Command:
```
/opt/cpanel/ea-php83/root/usr/bin/php /home/cpuser/public_html/ycc-hub/artisan schedule:run >> /dev/null 2>&1
```
This keeps statuses up to date every night and syncs Instagram every 30 minutes.
If your server's PHP 8.3 is somewhere else, ask the host, or try `/usr/local/bin/php` if that one is 8.3+.

### Part C: first-day setup inside YCC Hub

**Step 16.** Admin > **Settings**:
- Check the Instagram and LinkedIn links.
- Add the semester breaks (so members don't turn at-risk during holidays).
- Instagram: paste a token for live posts, or paste a few post links to pin (see section 4).

**Step 17.** Admin > **Roles & access**: once the excos sign up and you approve them, change their role to **Exco**.

**Step 18. Quick test:**
1. Open the site in a private window and sign up as a test member.
2. As admin, approve it in **Approvals** (check the email arrives).
3. Create a program, register as the test member, open the QR on the attendance page, scan it with your phone and check in.
4. Check **Analytics** and **Dashboard** show the member.
5. Suspend or delete the test account when done.

### If something goes wrong

| Problem | Fix |
|---------|-----|
| 500 error | Read `storage/logs/laravel.log`. For a quick look, set `APP_DEBUG=true` in `.env`, reload, then set it back to `false`. |
| "Vite manifest not found" | `public/build` is missing. Re-upload it from the zip. |
| PHP version / syntax errors | The subdomain isn't on PHP 8.3 yet (Step 9.4). |
| `/install` shows 404 | `INSTALL_KEY` is empty, or an admin already exists (installer already ran). |
| Can't connect to database | Database/user names need the `cpuser_` prefix; user must be added to the database with All Privileges. |
| Emails not arriving | Check `MAIL_*` in `.env` and the mailbox password; look in spam. |
| Subdomain not found | DNS for a new subdomain can take a few minutes to an hour. |
| Subfolder shows the main site / Node page, or a 500 | `public_html/ycc-hub/.htaccess` is missing (hidden files) or was overwritten. Re-upload it. |
| Page loads but looks unstyled | `APP_URL` doesn't match the address you opened (subfolder vs subdomain). |
| Uploaded posters don't show | `public/uploads` isn't writable (Step 12). |

### Updating later
Make changes on your laptop, run `composer install --no-dev` if packages changed (and `npm run build` if styles changed), then upload the changed files.
If an update adds a migration, add a one-off cron job running
`/opt/cpanel/ea-php83/root/usr/bin/php /home/cpuser/public_html/ycc-hub/artisan migrate --force`, wait a minute, then delete that cron job.

### Running it locally (optional)
```
cp .env.example .env    # then set DB_CONNECTION=sqlite, APP_ENV=local, APP_DEBUG=true, APP_URL=http://127.0.0.1:8000
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed     # admin login: ADMIN01 / password
php artisan serve
```
Tests (needs dev packages from `composer install`): `vendor/bin/phpunit`

### No installer? (phpMyAdmin route)
Import `database/sql/schema.sql`, then edit and import `database/sql/seed-admin.sql` (login `ADMIN01` / `ChangeMe123`, change it right away).

## 4. Instagram feed

The landing page reads cached posts only, so the site never waits on Instagram.

**Live sync (latest posts automatically)**
Instagram's Basic Display API was shut down in 2024, so this uses the *Instagram API with Instagram Login*:
1. Switch @young.conservationist to a **Professional** account (Business or Creator).
2. developers.facebook.com > create an app > add **Instagram** (API setup with Instagram login).
3. Add the club account as an Instagram tester and accept the invite.
4. Generate a token with the `instagram_business_basic` permission.
5. Paste it in **Admin > Settings > Instagram feed**. Posts sync immediately, then every 30 minutes; images are copied to `public/uploads/instagram` because Instagram image links expire. The token is refreshed weekly (tokens last 60 days).

**Pinned posts (no token)**
Paste post links (one per line) in the same settings box. They render with Instagram's official embed until live sync is set up.

**LinkedIn** has no public feed API for company pages, so the site shows a linked card instead.

---

## 5. Data and privacy (PDPA 2010)

- Registration requires consent to the personal data notice (`pdpa_consent_at`).
- Contact details are visible to exco/admin only.
- Every CSV export is logged (who, when, filter, rows): **Admin > Settings > Export log**.
- To remove a member's data on request, an admin can suspend the account; full deletion is a database delete (registrations cascade).

## 6. Where things are

| Area | Files |
|------|-------|
| Routes | `routes/web.php`, scheduler in `routes/console.php` |
| Status engine | `app/Services/ActivityStatusService.php` |
| Registration, capacity lock, attendance, QR | `app/Services/ProgramRegistrationService.php` |
| Instagram | `app/Services/InstagramFeedService.php` |
| Settings, rules, faculty list | `config/ycc.php`, `.env` |
| Analytics | `app/Http/Controllers/Admin/AnalyticsController.php` |
| Views | `resources/views` (layouts in `components/layouts`) |
| Styles / tokens | `resources/css/app.css` |
