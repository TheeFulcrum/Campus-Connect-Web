# Campus Connect Mobile

A hyper-local, peer-to-peer marketplace built for university students to advertise services, skills, gigs, and goods to other verified students on campus. Restricted to `.education` email holders only.

This repo contains two related pieces of the same product:

- **`android/`** — the native Android (Java) mobile app
- **`website/`** — the companion marketing site + web login/signup, sharing the same brand and color system

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Brand & Color System](#brand--color-system)
- [Android App Setup](#android-app-setup)
- [Website Setup](#website-setup)
- [Project Structure](#project-structure)
- [Known Limitations](#known-limitations)
- [Roadmap](#roadmap)

---

## Features

- **Verified access** — sign-up restricted to `.education` university email addresses
- **Splash screen** — branded launch screen (AndroidX Core SplashScreen API on mobile)
- **Login & Sign Up** — with local credential storage
- **Profile setup** — username, campus location, and feed content preferences captured at sign-up
- **Home screen** — personalized welcome, ready to host the marketplace feed
- **Two campuses supported today**: Main Campus, Great East Campus
- **Dark mode** — website supports light/dark theme with saved preference (`localStorage`)
- **Shared brand system** — identical color tokens across the Android app and the website

---

## Tech Stack

| Layer | Technology |
|---|---|
| Mobile app | Native Android, Java |
| Mobile local storage | SQLite (`SQLiteOpenHelper`) |
| Mobile splash | AndroidX Core SplashScreen (`androidx.core:core-splashscreen:1.0.1`) |
| Website frontend | HTML, CSS, vanilla JavaScript |
| Website backend | PHP (flat-file JSON storage for prototype stage) |

> **Note:** early planning documents for this project referenced Flutter/Firebase as the intended stack. The project has since moved to **native Android (Java) + PHP**, which is what's reflected in this repo.

---

## Brand & Color System

Both the app and the website use the exact same color tokens, defined in [`colors.md`](./colors.md).

| Token | Hex | Role |
|---|---|---|
| Primary (Navy) | `#243B6B` | Headers, primary buttons, nav bar |
| Primary Dark | `#182747` | Pressed/hover states, footer |
| Accent (Orange) | `#FF6B45` | CTAs, highlights, badges |
| Success (Green) | `#2E9E6E` | Verified badges, ratings |
| Background | `#F7F6F2` | App/page background |
| Surface | `#FFFFFF` | Cards, sheets, form fields |
| Text Primary | `#1D1B16` | Headings, body text |
| Text Muted | `#6B6558` | Captions, placeholders |
| Border | `#E4E1D8` | Dividers, card borders |
| Error | `#C1432E` | Form errors, destructive actions |

Android: `android/colors.xml` · Website: CSS custom properties in `website/style.css` (`:root`).

---

## Android App Setup

1. Open the `android/` project folder in **Android Studio**.
2. Ensure the splash screen dependency is present in **`app/build.gradle`** (module-level, not project-level):
   ```gradle
   dependencies {
       implementation "androidx.core:core-splashscreen:1.0.1"
   }
   ```
3. Sync Gradle.
4. Run on an emulator or physical device (minSdk 26+).

**App flow:** `SplashActivity` → `MainActivity` (login) → `SignUpActivity` (if new) → `ProfileSetupActivity` (username, campus, feed preferences) → `HomeActivity`.

**Test account creation:** since the SQLite database starts empty, you must sign up through the app at least once before logging in — the `users` table has no seed data.

---

## Website Setup

Requires any PHP-capable server (e.g. local `php -S`, XAMPP, MAMP, or a real host).

1. Copy the contents of `website/` to your server root (or a subfolder).
2. Ensure the folder is **writable** by PHP — `signup.php` and `submit.php` write to `users.json` and `waitlist.csv` respectively.
3. Visit `index.html` in your browser.

**Quick local test (PHP built-in server):**
```bash
cd website
php -S localhost:8000
```
Then open `http://localhost:8000`.

**Site flow:** `index.html` → `signup.html` (username + email + campus + password, all in one form) or `login.html` → `home.html`.

---

## Project Structure

```
campus-connect-mobile/
├── colors.md                     # Shared brand color reference
├── README.md
├── android/
│   ├── colors.xml                # res/values/colors.xml
│   └── ic_splash_logo.xml        # res/drawable/ic_splash_logo.xml
│   # (full Android Studio project lives alongside these — see app/ folder)
└── website/
    ├── index.html                # Landing page (hero video, features, waitlist)
    ├── login.html
    ├── signup.html
    ├── home.html
    ├── style.css                 # Shared styling + theme tokens
    ├── script.js                 # Theme toggle + waitlist form (index.html)
    ├── auth.js                   # Login/signup form handling
    ├── home.js                   # Session check + logout
    ├── login.php                 # Auth backend
    ├── auth_api.php              # Shared REST API for mobile & web auth
    ├── auth_lib.php              # Auth business logic, token & password handling
    ├── login.php                 # Auth backend
    ├── signup.php                # Account creation backend
    └── submit.php                # Waitlist signup backend
```

---

## Security & Architecture Highlights

- **Password Hashing** — Passwords are standard-hashed using `password_hash()` (BCrypt) on the PHP backend and `PBKDF2WithHmacSHA256` locally in Android.
- **Shared Authentication Backend** — `auth_api.php` serves both the web frontend and mobile client, unifying user authentication across platforms.
- **Session Tokens & Cookies** — Token-based session authentication with `auth_tokens.json` / SQLite token store and HttpOnly cookies.
- **OTP Verification** — 6-digit OTP delivery support via Resend integration with hashing and attempt rate-limiting.

---

## Deployment & Production Considerations

- **HTTPS Deployment** — Ensure the backend is served over HTTPS in production.
- **Environment & Secrets** — Keep `.env` and `Plain Text.env` out of public web directories and version control.
- **Database Scaling** — Migrate flat-file/SQLite stores to a managed production database (e.g. MySQL/PostgreSQL) as traffic grows.

---

## Roadmap

Suggested next steps, roughly in priority order:

1. **Marketplace Feed** — The actual gig/goods listings, filtered by user preferences and campus.
2. **In-app Messaging** between buyers and sellers.
3. **Reviews & Ratings** system.
4. **University Partnerships / Official Endorsements** as part of the go-to-market strategy.

---

*Campus Connect — currently piloting on select campuses (Main Campus, Great East Campus).*
