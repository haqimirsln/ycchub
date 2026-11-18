# Deployment notes

How YCC Hub runs on the live server and how updates are shipped. Read this before changing anything on the server.

## The server

| Item | Value |
|------|-------|
| Live URL | https://conservems.my/ycc-hub |
| Hosting | Shared cPanel hosting |
| App folder | `/home/serveradmin/public_html/ycc-hub` (a subfolder of `public_html`) |
| PHP for the command line | `/usr/local/bin/ea-php83` (plain `php` may be an older version) |
| Database | MySQL / MariaDB, managed in phpMyAdmin |

### Limits on this host (they shape how the app is built)

- **No terminal.** There is no SSH, and `shell_exec` is disabled, so `php artisan tinker` and one-off commands can't be run. Anything the admins need is a button in the app instead, for example **Settings › Update database** runs pending migrations.
- **`proc_open` is disabled.** Laravel's scheduler normally starts each job as a separate process with `Schedule::command()`, which needs `proc_open`, so on this host every such job fails with *"The Process class relies on proc_open"*. That's why every job in `routes/console.php` uses `Schedule::call()` and runs inside the cron process itself. **Never switch a job back to `Schedule::command()`.** A test (`test_scheduled_jobs_never_need_proc_open`) fails if anyone does.
- **Mail limits.** Shared hosting caps emails per hour, so club emails go out 15 a minute.

## Shipping an update

1. Make the change and run the tests: `vendor/bin/phpunit`.
2. Build the front end: `npm run build`.
3. Zip **only the changed or added files**, plus the **whole `public/build` folder**.
4. **Back up first:** export the database in phpMyAdmin (or check cPanel's daily backup).
5. On the server, in cPanel › File Manager:
   1. Delete `public_html/ycc-hub/public/build`.
   2. Upload the zip into `public_html/ycc-hub`, then extract it and overwrite.
6. If the update adds a migration, log in as superadmin and press **Settings › Update database**. Migrations must only add (columns, tables) and keep existing data: real members use the system.
7. Press Ctrl+F5 in the browser. Installed phone apps pick up the new version on their next open.

While **Update database** runs, a short "updating" page is shown so nobody hits a half-migrated database.

## The cron job

Set in cPanel › Cron Jobs, **Once Per Minute** (`* * * * *`):

```
cd /home/serveradmin/public_html/ycc-hub && /usr/local/bin/ea-php83 artisan schedule:run >> /dev/null 2>&1
```

| Job | When | What it does |
|-----|------|--------------|
| `ycc:send-push` | every minute | Sends queued phone notifications |
| `ycc:send-emails` | every minute | Sends the next batch of club emails |
| `ycc:event-reminders` | every 5 minutes | Reminder emails and notifications before events |
| `ycc:instagram-sync` | every 30 minutes | Refreshes the Instagram posts on the home page |
| `ycc:refresh-activity` | nightly at 00:15 | Recalculates every member's activity status |
| `ycc:instagram-refresh-token` | weekly | Keeps the Instagram token alive |

**Check it in Settings › Cron job.** It shows when each job last worked and the last error. "Running" only shows when the jobs themselves succeed, not just when cron ticks.

**If the cron job stops:** site visits do the same work instead, at most once a minute, right after the page has gone to the browser (`app/Support/Scheduler.php`). Things still go out, but can be late when nobody is on the site.

## Phone notifications (Web Push)

- No extra packages: encryption (RFC 8291) and VAPID signing (RFC 8292) use PHP's built-in OpenSSL (`app/Support/WebPush.php`).
- The site's key pair is created on first use and stored in the `settings` table, with the private key encrypted with `APP_KEY`. **If `APP_KEY` ever changes, every phone has to turn notifications on again.**
- Notifications are queued in `push_messages` and sent by `ycc:send-push`. Anything waiting more than 12 hours is dropped instead of being sent late.
- A refusal from Apple or Google is recorded (Settings › Phone notifications › Last failure, and the log). A phone is only removed when the push service says it's gone (404 or 410).
- iPhones need iOS 16.4+ and YCC Hub added to the home screen.

## Logs

`storage/logs/laravel-YYYY-MM-DD.log` (one file per day). Safe to delete old ones from File Manager.
