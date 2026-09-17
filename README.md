# Surf4Miles

Surf4Miles is an energy-efficiency report website for BYD Dolphin Surf owners. It's a PHP rewrite of a personal Go CLI tool, turned into something other owners can use — **without accounts, and without the site storing anyone's driving data.**

Each visitor's cumulative trip history lives only in their own browser (via IndexedDB). To add trips, they upload the small `EC_database.db` file copied off their car's USB stick; the server processes it just long enough to calculate the report, then discards its temporary copy. Nothing is written to persistent server storage or logged.

Surf4Miles is an independent, fan-made tool and is not affiliated with or endorsed by BYD.

## Features

- **No accounts, no server-side data.** Cumulative trip history, electricity rates, and comparison settings all live in the visitor's own browser.
- **Strict upload validation.** Uploaded `.db` files are checked against a whitelisted schema and opened read-only before anything is queried.
- **Duplicate-safe imports.** Re-uploading the same `EC_database.db` never double-counts a trip.
- **Backup / Restore.** Download your cumulative data as a `.db` file, or merge a backup back in — safe to use across multiple devices (e.g. a desktop and the in-car browser) without losing trips recorded on either one.
- **Multiple car / profile support** for multi-car households, each with its own local dataset.
- **Live UK fuel prices** (official DESNZ weekly data), with a week-over-week trend indicator, and a configurable petrol/diesel/MPG comparison.
- **Print-friendly report** that fits cleanly onto a single page.

## Requirements

- PHP 8.3+ with the `sqlite3` extension
- [Composer](https://getcomposer.org/)
- Nginx (or any web server that can serve a PHP-FPM app from a `public/` docroot)

## Setup

```bash
composer install
```

Point your web server's document root at `public/`.

The app writes to disk in exactly two places:

- Temporary files while processing an upload — the system temp directory (`sys_get_temp_dir()`, typically `/tmp`), which is world-writable by default. No setup needed.
- A shared, non-personal cache of the UK fuel price data, in `var/cache/`. This one **does** need attention: it must be writable by whichever user your web server/PHP-FPM pool runs as (commonly `www-data`). Give that user ownership rather than loosening the directory's permissions:

  ```bash
  sudo chown www-data:www-data var/cache
  ```

  (substitute your actual PHP-FPM pool user if it isn't `www-data` — check `user =` in your pool's `.conf`). If this is skipped, the site still works, it just silently re-fetches the fuel price on every request instead of caching it for up to 24h.

## Usage

1. Copy `EC_database.db` off your car's USB stick.
2. Open the site and upload it under "Add trips from EC_database.db".
3. Set your electricity rates and fuel comparison once — they're remembered in your browser.
4. Use **Backup** to save a copy of your data, and **Restore** to bring it into another browser/device.
5. Use **Print report** for a clean, single-page printout.

See the in-page "How does this work?" link for a plain-language explanation of exactly what stays local and what (briefly) touches the server.

## License

Commons Clause + GNU Affero General Public License v3.0 — see [LICENSE.md](LICENSE.md). In short: the source is available and modifiable, but it may not be sold or resold as a hosted product or service.
