# Campus Connect — Landing Page

Plain HTML/CSS/JS front end with a PHP backend for the waitlist form.

## Files
- `index.html` — the page
- `style.css` — styling, uses the shared color variables (see `colors.md`)
- `script.js` — handles the waitlist form, validates `.edu` email client-side, then posts to `submit.php`
- `submit.php` — validates and saves signups to `waitlist.csv`
- `colors.md` — shared color palette for both the website and the Android app

## Running it locally
You need PHP installed (most systems have it, or install via your package manager).

From inside this folder, run:
```bash
php -S localhost:8000
```
Then open **http://localhost:8000** in your browser.

The form won't work by opening `index.html` directly as a file — it needs to be served by PHP for `submit.php` to run.

## Deploying
Upload all files to any standard PHP-capable web host (most shared hosting, e.g. cPanel-based hosts, supports this out of the box — no special setup needed).

Make sure the folder is writable so `submit.php` can create/append to `waitlist.csv`.

## Where signups go
Each signup gets appended to `waitlist.csv` as `timestamp,email`. You can open this in Excel/Sheets, or swap in a database/mailing list service later — the validation logic in `submit.php` stays the same either way.

There's also a commented-out `mail()` line in `submit.php` if you want an email notification every time someone joins.
