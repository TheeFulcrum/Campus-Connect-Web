# Campus Connect — What We've Built So Far

**Date:** October 2026
**Platforms:** Web (PHP/HTML/JS) + Android (Java)
**Live server:** `129.121.149.158` (nginx) → https://campusconnect.ink · https://auth.campusconnect.ink

---

## Overview

Campus Connect is a hyper-local, peer-to-peer marketplace for university students, restricted to verified `.education` email holders. Both the website and the Android app share a single PHP backend with SQLite storage, token-based auth, and a unified brand system.

---

## 🌐 Website (CCW)

### Authentication & Onboarding
- Server-side auth API ([auth_api.php](auth_api.php), [auth_lib.php](auth_lib.php)) — no client-side-only auth
- Secure password hashing (PHP password APIs), token generation/validation, session + bearer-token support
- OTP email verification flow: 6-digit codes, expiry, resend throttling, attempt limits, hashed OTP storage
- Email delivery via **Resend** with environment-based sender config
- Login ([login.php](login.php)), signup ([signup.php](signup.php)), logout ([logout.php](logout.php)), sessions ([session.php](session.php))
- Redesigned onboarding: Sign up → OTP → Login → Preferences → Splash welcome → Home

### Password Recovery
- Full reset flow in [api/password-recovery/](api/password-recovery/):
  - `request-reset.php` — request a reset token
  - `verify-token.php` — validate token
  - `reset-password.php` — set new password
- Pages: [forgot-password.html](forgot-password.html), [reset-password.html](reset-password.html)

### Marketplace / Listings
- SQLite tables: `listings`, `listing_images` (with indexes)
- API endpoints in [api/listings/](api/listings/): `create.php`, `list.php`, `view.php`, `update.php`, `delete.php`
- Token verification on all writes; ownership checks for edit/delete; prepared statements (SQL-injection safe)
- Web UI: [listings.html](listings.html) (browse/search by campus & category), [create-listing.html](create-listing.html), [listing-detail.html](listing-detail.html), [listings.js](listings.js)

### Other Pages & Features
- Home feed ([home.html](home.html)), posts ([post.html](post.html)), adverts ([adverts.html](adverts.html))
- Messaging UI ([messages.html](messages.html), [messages.js](messages.js))
- Profile management ([profile.html](profile.html), [profile_update.php](profile_update.php))
- Preferences ([preferences.html](preferences.html)), settings ([settings.html](settings.html))
- Shared header/component system ([shared-header.html](shared-header.html), [route-pages.js](route-pages.js))
- Dark mode with saved preference; shared brand tokens (Navy `#243B6B`, Orange `#FF6B45`, etc.)

### Backend Hardening
- SQLite-backed storage for users, tokens, OTPs (migrated from flat JSON)
- `.env` support — secrets kept out of source
- `.gitignore` rules for sensitive files

### Deployment
- Backend + web deployed to production server via scp/rsync
- nginx serving the app; ownership/permissions set for `www-data`
- Verified live via access logs

---

## 📱 Android App (CampusConnectMobile)

### Stack
- Native Android, **Java**, Gradle Kotlin DSL
- Min SDK 26 (Android 8.0), target API 36
- AndroidX, Material Design, ConstraintLayout, SplashScreen API, Facebook Shimmer
- Local storage: SQLite (`DatabaseHelper`)

### Screens / Activities
- `SplashActivity` — branded launch screen
- `MainActivity`, `SignUpActivity`, `OtpLoginActivity` — onboarding & auth
- `ProfileSetupActivity`, `ProfileFragment` — profile setup & management
- `HomeActivity` — main feed
- `ListingsActivity`, `CreateListingActivity`, `ListingDetailActivity` — marketplace
- `ChatActivity`, `MessagesFragment`, `Conversation`/`ConversationAdapter`, `MessageAdapter` — in-app chat
- `AdvertsFragment`, `PostAdapter` — adverts & posts
- `ThemeHelper` — dark/light theme support

### Backend Integration
- `AuthApiClient` — real backend registration, login, OTP verification, token sessions
- `PasswordHasher` — PBKDF2-HMAC-SHA256 with secure salts (plus legacy plaintext compatibility)

### Network & Build Hardening
- API base URL via Gradle property (`ccwApiBaseUrl`) — dev emulator default (`10.0.2.2`), prod via `-PccwApiBaseUrl=https://...`
- `network_security_config.xml` — HTTPS for production, cleartext only for local dev
- Verified: `./gradlew test assembleDebug` → **BUILD SUCCESSFUL**

---

## 🔒 Security Measures (Both Platforms)

- Hashed passwords (web + mobile)
- Hashed, expiring OTPs with attempt limits
- Token-based auth across all API endpoints
- Ownership checks on listing edits/deletes
- Prepared statements everywhere (no SQL injection)
- Secrets in environment variables, never in Git

---

## ✅ Working End-to-End

- Sign up → OTP email → verify → login → preferences → home
- Create, browse, search, view listings (web + Android)
- Password reset via email token
- Live deployment serving real traffic

## 🔜 Next Up (Roadmap)

- Image uploads for listing photos
- Buyer–seller messaging API (`/api/messages/`)
- Reviews & ratings after transactions
- Rate limiting on OTP/login endpoints
- Admin moderation dashboard
