# TimeOnUs Deployment & Operations Guide

## Prerequisites
- PHP 8.2+, Composer, Node 18+, npm
- MySQL 8 or MariaDB 10.6+
- Redis (recommended for cache/queue optimisations)
- NativePHP prerequisites (Electron, Node toolchain) installed according to https://nativephp.com docs

## Environment Variables
Add the following keys to `.env` alongside your existing configuration:

```
TIME_TRACKER_IDLE_THRESHOLD=180
TIME_TRACKER_MAX_HEARTBEAT_DELTA=300
TIME_TRACKER_HELP_TIMEOUT=900
```

> The API uses Laravel Sanctum for desktop authentication. Install and publish Sanctum if not already present: `composer require laravel/sanctum` then `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` and migrate.

## Database & Seeders
```
php artisan migrate
php artisan db:seed
```

Seeders provision the core roles, a sample HQ location, and a super admin account (`superadmin@timeonus.test` / `ChangeMe123!`). Update or remove after initial login.

## Native Desktop Development
```
npm install
npm run dev
php artisan native:serve
```

- The Native window auto-launches on OS login and opens the dashboard route (`/native/dashboard`).
- Front-end assets live in `resources/js/native/dashboard.js`; Vite watches this entry automatically.

## Background Processing
- **Scheduler**: run continuously via `php artisan schedule:work` or cron (`* * * * * php /path/artisan schedule:run >> /dev/null 2>&1`).
  - `time:detect-idle` marks inactive sessions idle after 3 minutes.
  - `time:monitor-help` escalates help requests that exceed the 15-minute SLA.
- **Queue**: configure `QUEUE_CONNECTION=database` (already set) and start a worker (`php artisan queue:work`).
- **Cache/Session**: `.env` already set to database drivers; consider Redis for scale.

## Desktop Behaviour
- When the authenticated user opens the desktop app, it immediately posts `/api/v1/attendance/login` (with geolocation if available).
- Activity heartbeats fire every 60 seconds capturing keyboard/mouse counts; idle enforcement is handled server-side via the scheduler.
- Help requests can be submitted, escalated, or stopped directly from the desktop pane. Team leads are notified via the escalation command (hook into notifications/emails in a later iteration).
- On window close or shutdown, the app posts `/api/v1/attendance/logout` using a `keepalive` fetch to guarantee logout is captured.

## Security Notes
- Sanctum protects all desktop APIs. Distribute personal access tokens or keep users signed-in using the session guard inside the native shell.
- All JSON endpoints expect `Accept: application/json` and return 401/419 on auth issues. Ensure CSRF token meta tag is present in the native view (`native/dashboard.blade.php`).
- Anti-automation heuristics live inside `App\Services\AntiCheatService`. Adjust thresholds as needed.

## Testing Touch Points
- Run `php artisan test` after implementing feature-specific tests.
- Use `php artisan migrate --pretend` to ensure schema integrity before applying to production DBs.
- Trigger idle detection manually by invoking `php artisan time:detect-idle` and verifying `idle_periods` rows.
- Trigger help escalation via `php artisan time:monitor-help`.

## Building For Production
```
npm run build
php artisan native:build --os=windows
```

Use the generated installer from `native/build` for distribution. Configure auto-update providers through `config/nativephp.php` if desired.