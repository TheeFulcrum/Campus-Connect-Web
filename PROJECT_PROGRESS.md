# Campus Connect Project Progress

## Overview
This document records the work completed across the Campus Connect web app and Android mobile app, including authentication, onboarding, backend architecture, email verification, and production-hardening improvements.

---

## 1. Project Status Summary

The project evolved from a basic campus marketplace prototype into a fuller authentication and onboarding system with:

- a real server-backed authentication flow
- secure password handling
- OTP verification for email-based sign-up validation
- a redesigned onboarding flow
- Android app integration with the shared backend
- environment and security hardening for future production deployment

The work spans both of these repositories:

- Web repo: Campus Connect Web
- Mobile repo: Campus Connect Mobile

---

## 2. Web Project Work

### 2.1 Initial State
The web project began as a front-end heavy campus app with static HTML, CSS, JavaScript, and PHP components. It included user flows for signup, login, profile, home, and marketplace interactions but lacked a robust backend identity model.

### 2.2 Authentication Upgrade
The web backend was upgraded to use a real server-side auth model instead of relying on client-side logic or unsafe storage.

Key files:
- auth_lib.php
- auth_api.php
- login.php
- logout.php
- session.php

Implemented features:
- email validation against the university format
- secure password hashing using PHP password APIs
- token generation and validation
- session-based and bearer-token support
- user lookup and account verification routines

This moved the system closer to a real production-friendly authentication flow.

### 2.3 OTP Flow Implementation
A dedicated OTP workflow was added to support signup verification and secure code-entry steps.

Key files:
- auth_lib.php
- auth_api.php
- otp.js
- otp.html

Implemented features:
- OTP generation
- expiration enforcement
- resend throttling
- attempt limits
- hashed OTP storage
- email delivery integration

The OTP flow supports request and verification actions and is tied to the onboarding sequence.

### 2.4 Email Delivery Integration
Email sending was integrated with Resend for OTP verification.

Key behavior:
- 6-digit code generation
- email subject and template formatting
- sender configuration using environment variables
- delivery error handling for provider rejection

The implementation includes explicit error messaging for common configuration problems such as bad sender identity or invalid recipient permission.

### 2.5 Flow Redesign and UX Updates
The onboarding flow was updated to match the intended process:

1. Create account
2. Request OTP
3. Verify code
4. Return to login
5. Complete preferences
6. Splash welcome screen
7. Home screen

This replaced earlier flows that allowed more ambiguous or inconsistent paths.

### 2.6 Production Hardening
The web backend was hardened to prepare for production use by:

- separating environment values from source files
- adding secure backend initialization via .env support
- moving the auth store away from flat JSON-only structures
- adding SQLite-backed storage for users, tokens, and OTP state
- preserving migration compatibility with legacy JSON data

This reduces the risk of auth state loss and makes the app more suitable for deployment beyond a local-only environment.

---

## 3. Android App Work

### 3.1 App Architecture and Flow
The Android app includes a fragment-based and activity-based structure for user navigation and onboarding.

Key files:
- MainActivity.java
- SignUpActivity.java
- SplashActivity.java
- HomeActivity.java
- ProfileSetupActivity.java
- OtpLoginActivity.java
- AuthApiClient.java

### 3.2 Real Authentication Integration
The Android app was updated to use the backend authentication API instead of relying on local-only logic.

Implemented features:
- backend registration requests
- login requests against the shared server
- token-based session handling
- OTP verification before continuing onboarding

### 3.3 Password Security Update
The app was updated to use strong password hashing instead of plain-text storage.

Key file:
- PasswordHasher.java

Features:
- PBKDF2 with HMAC-SHA256
- secure salt generation
- verification logic for stored hashes
- compatibility handling for older plaintext entries

This makes Android-side auth much safer than a simple local comparison approach.

### 3.4 Network Configuration Update
The Android app was shifted toward a more production-safe networking model.

Key files:
- app/build.gradle.kts
- AndroidManifest.xml
- app/src/main/res/xml/network_security_config.xml

Implemented changes:
- production default URL uses HTTPS
- emulator/local development remains possible via Gradle property override
- cleartext traffic is controlled through network security configuration
- dev and prod endpoints are now easier to separate cleanly

### 3.5 Build Validation
The Android project was verified by building the debug app using the hardened production-style URL configuration.

Command used:
- ./gradlew test assembleDebug -PccwApiBaseUrl=https://api.campusconnect.local/

Result:
- BUILD SUCCESSFUL

---

## 4. Shared Backend and Auth Strategy

The project now uses a shared backend strategy where both the web and Android app rely on the same server-side auth endpoint structure.

This helps align the app flows and avoids divergent authentication implementations between web and mobile.

### Shared backend responsibilities
- user registration
- login validation
- session/token management
- OTP generation and verification
- email delivery
- account validation rules

This approach is significantly safer than the earlier split model, where the web and app flows were not fully aligned and some logic lived in client code.

---

## 5. Security and Privacy Improvements

The project now includes or reflects several important security measures:

- password hashing with modern algorithms
- OTP hashing with expiry/attempt tracking
- verification of sender and recipient settings for email delivery
- environment variable support for secret configuration
- Git ignore rules for sensitive files
- SQLite storage for auth-sensitive data instead of raw JSON-only state

These steps reduce the chance of exposing user credentials or session data through development artifacts.

---

## 6. Remaining Production Work

Some work is still required before a fully public deployment is advisable.

### Remaining items
- deploy the backend on HTTPS with a real domain
- configure a verified Resend sender domain
- move from local development server to permanent hosting
- complete a production-grade rate limit and monitoring layer
- consider a full database migration to a managed production DB if traffic grows

Even with the current hardening, the app is still best described as a secure prototype/dev-ready system rather than a fully production-deployed public service.

---

## 7. Current State

The major project milestones completed include:

- auth flow redesign
- real password hashing
- OTP flow implementation
- web/mobile backend alignment
- onboarding sequence update
- Android build validation
- backend hardening via SQLite-backed auth storage
- production-oriented network configuration for Android

This is a strong foundation for the next deployment phase.

---

## 8. Recommended Next Phase

The next immediate steps are:

1. deploy the backend to HTTPS with a real host
2. configure a verified sender domain for email delivery
3. set production env values separately from dev values
4. perform a live smoke test on the real deployment URL
5. add rate limiting and audit logging for abuse prevention

---

## 9. Final Note

The work completed so far has moved Campus Connect from a local prototype toward a more realistic production-ready architecture, especially in the areas of authentication, onboarding flow, OTP verification, and mobile app configuration.

---

## 10. Latest Web and Android Parity Pass (2026-10-01)

The Android app was brought closer to the current website behavior in the following areas:

- Android signup now collects optional real name and bio, matching the web signup form.
- Android profile now displays and edits username, real name, campus, bio, and profile image.
- Profile edits are sent to the shared auth API using the signed-in bearer token.
- The auth API rejects profile updates without a valid token.
- Android caches the profile fields returned by registration, login, OTP verification, and profile updates.
- Added a non-destructive local SQLite migration for the Android real-name field.
- Added category normalization so broad interests such as Services and Goods & Textbooks match specific feed listing categories.
- Android signup password copy now matches the server requirement of at least eight characters.

### Validation performed

- Android `test` and `assembleDebug` completed successfully with the local API URL override.
- PHP syntax checks passed for `auth_api.php` and `auth_lib.php`.
- JavaScript syntax checks passed for the auth and OTP scripts.
- Unauthenticated profile-update request was rejected with HTTP 401.

### Remaining platform differences

- Android listings and conversations are still stored locally; they are not synchronized with the web app through a shared listings/messages API.
- Website messaging currently uses browser-local demo conversations and scripted replies; it is not production server messaging.
- Website profile saved/review/follower sections contain prototype/demo content and do not yet have full Android equivalents.
- The app's local feed cache and local messaging are prototype functionality, not shared multi-user production data.
- The development PHP server runs on the laptop LAN only; it is not the always-on public server.

### Current status

Authentication and basic profile data are now shared between web and Android. The visual surfaces are platform-native rather than pixel-identical. The next meaningful parity step is a real shared listings API, followed by server-backed conversations and messages; these should replace the current local/demo data rather than copying demo behavior into Android.
