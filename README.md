# Total Ninety

Total Ninety is a soccer league and tournament management app built with Laravel 13, Inertia.js 3, Vue 3, and Tailwind CSS 4. Organize leagues, seasons, teams, groups, and fixtures; record results; follow standings and knockout brackets; and run live match commentary and scoreboards through Laravel Reverb.

The dashboard gives league owners a matchday overview with fixtures, season progress, and setup reminders. Leagues can be public or private, and team changes are restricted to their owners.

## Requirements

- PHP **8.4.1+** (use PHP 8.4 or 8.5), with Laravel’s required extensions and **PDO SQLite** for the default database.
- Composer **2.x**.
- Node.js **24.x** and npm, including for the frontend tests.
- A writable `storage/` directory and `bootstrap/cache/` directory.

SQLite is the default database. Local development uses database-backed sessions, queues, and cache; Redis is optional. Reverb provides the WebSocket server for live updates.

## Local setup

Clone the repository and install the locked dependencies:

```sh
git clone https://github.com/ylynfatt/total-ninety.git
cd total-ninety
composer install
npm ci
cp .env.example .env
php artisan key:generate --no-interaction
touch database/database.sqlite
```

In `.env`, set `APP_URL` to the address you will use to open the app, and choose `APP_TIMEZONE` for match scheduling and the dashboard. Leave `DB_CONNECTION=sqlite` for the default setup.

Add the following Reverb configuration to `.env`. Replace the example key and secret with your own random strings; the frontend key must match the server key.

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=total-ninety
REVERB_APP_KEY=your-local-key
REVERB_APP_SECRET=your-local-secret

REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

This configuration is for local HTTP development. If your site uses HTTPS, configure Reverb and the frontend WebSocket connection for HTTPS/WSS as well.

Create the database tables and build the frontend:

```sh
php artisan migrate --no-interaction
npm run build
```

The first frontend build downloads the configured fonts, so it needs internet access.

Start the development processes:

```sh
php artisan dev
```

Laravel automatically discovers and starts the HTTP server, queue listener, Reverb server, log viewer, and Vite development server. `composer run dev` is also available as an alias for this command. Open the address printed by the HTTP server and make sure `APP_URL` matches it. Restart the development processes after changing `.env`.

Register an account, create a league and season, add teams, then create stages and generate fixtures. Seeding is optional. Password reset emails are written to the application log by default (`MAIL_MAILER=log`); configure an SMTP provider to deliver email.

## Development checks

```sh
php artisan test --compact
npm run test:frontend
npm run types:check
npm run lint:check
npm run build
```

The PHP tests use an in-memory SQLite database. Finish the frontend build before running PHP tests, since page-rendering tests read the generated asset manifest.

## Configuration notes

- Supported formats include single and double round-robin, group stages, single elimination, and conferences. Double elimination is not currently supported.
- Fixture scheduling reserves a **120-minute** window for each team and venue. Change `schedule_window_minutes` in `config/games.php` to adjust it.
- Multi-server deployments need a shared cache store so the scheduling lock coordinates edits across instances.
- When updating an existing installation, run `composer install`, `npm ci`, `php artisan migrate --no-interaction`, and `npm run build`. Existing teams without an owner remain read-only until ownership is assigned.
