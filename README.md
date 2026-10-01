# Campus Connect Web

A campus marketplace prototype built with HTML, CSS, vanilla JavaScript, and PHP. The PHP endpoints provide account registration, password login, email one-time-code verification, and a waitlist. Marketplace listings, preferences, and conversations are currently browser-local prototype data.

## Run locally

The site requires PHP. From the repository root, start PHP's built-in server:

```bash
php -S localhost:8000
```

Open `http://localhost:8000`. For email verification, configure `RESEND_API_KEY` and `CCW_FROM_EMAIL` in the process environment or a root `.env` file.

## Private data storage

PHP stores account, OTP, token, and waitlist data outside the web root. By default, the application creates a `campus-connect-data` directory beside the web root. The PHP process must be able to create and write to that directory. Set `CCW_DATA_DIR` to another writable directory outside the web root when needed.

Existing `users.json`, `auth_tokens.json`, `otp_codes.json`, and `waitlist.csv` files in the application directory are moved to private storage on first access. Apache deployments also deny direct access to these legacy filenames and `.env` through `.htaccess`; configure equivalent access rules on other web servers.

## Main files

- `index.html`, `login.html`, `signup.html`, `otp.html`, `home.html`, and other root HTML files provide the pages.
- `auth.js`, `otp.js`, `home.js`, and other JavaScript files implement browser interactions.
- `auth_api.php`, `auth_lib.php`, `login.php`, `signup.php`, `session.php`, and `logout.php` implement authentication.
- `submit.php` handles waitlist submissions.

## Prototype limitations

- Passwords are hashed by PHP, and password login requires email verification.
- Accounts and waitlist entries use JSON/CSV files, not a database.
- Marketplace listings, preferences, and conversations are stored locally in the browser and are not shared between users or devices.
- Use HTTPS and a production database, and review hosting-specific PHP and web-server configuration before deployment.
