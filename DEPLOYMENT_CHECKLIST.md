# Campus Connect Deployment Checklist

## Goal
Prepare the Campus Connect web app and Android app for a real production deployment after the auth, OTP, and onboarding work already completed.

---

## 1. Production hosting
- [ ] Deploy the PHP backend to a real HTTPS host
- [ ] Set a production domain for the API
- [ ] Ensure the web frontend and API are served from the correct domain
- [ ] Confirm the app points to the production API URL, not the local dev URL

Example production pattern:
- API: https://api.campusconnect.edu
- Web: https://campusconnect.edu

---

## 2. Verified email provider setup
- [ ] Create or configure the production Resend account
- [ ] Verify the sender domain used for OTP emails
- [ ] Set the production sender value such as:
  - Campus Connect <auth@yourdomain.edu>
- [ ] Confirm the university email domain is allowed for OTP delivery
- [ ] Remove any test or dev sender configuration from production

---

## 3. Environment and secrets
- [ ] Create a production .env file on the server
- [ ] Store the production API keys in the server environment, not in Git
- [ ] Verify `RESEND_API_KEY` is set correctly
- [ ] Verify `CCW_FROM_EMAIL` uses the verified sender domain
- [ ] Keep all secrets out of the repo and build output

---

## 4. Authentication hardening
- [ ] Confirm server-side auth remains the source of truth
- [ ] Confirm all passwords are stored hashed
- [ ] Confirm OTP codes are hashed and expire correctly
- [ ] Confirm token expiry is enforced
- [ ] Confirm session invalidation works on logout and expiration
- [ ] Confirm no plaintext credentials are stored in the app or repo

---

## 5. Database and persistence
- [ ] Move the app from flat JSON storage to a production database
- [ ] Add database indexes for user lookup and token/OTP queries
- [ ] Add cleanup jobs for expired tokens and OTP records
- [ ] Ensure backups and restore procedures are in place
- [ ] Add admin monitoring for auth records and failed attempts

---

## 6. Security reviews
- [ ] Add rate limiting for OTP requests
- [ ] Add rate limiting for login attempts
- [ ] Log failed login attempts and suspicious activity
- [ ] Add audit logging for account creation and verification events
- [ ] Ensure error messages do not expose internal data unnecessarily

---

## 7. Android app production settings
- [ ] Confirm production API URL in Gradle build config
- [ ] Confirm HTTPS is required in Android network configuration
- [ ] Confirm cleartext traffic is off in production builds
- [ ] Validate the app works against the live production API
- [ ] Test signup, OTP verification, login, and preferences flow end-to-end

---

## 8. Web app production validation
- [ ] Test account creation on the live server
- [ ] Test OTP request and verification flows
- [ ] Test login and session retrieval
- [ ] Test logout and session invalidation
- [ ] Confirm the onboarding flow works end-to-end

---

## 9. Smoke tests before launch
- [ ] Create a fresh user account
- [ ] Request an OTP
- [ ] Verify the code successfully
- [ ] Log in with the verified account
- [ ] Complete preferences
- [ ] Check the welcome/splash flow
- [ ] Open the home screen successfully
- [ ] Test error handling for invalid email, OTP, and expired code

---

## 10. Launch readiness
- [ ] Confirm API and web domain are live and reachable
- [ ] Confirm HTTPS certificate is valid
- [ ] Confirm email delivery works in production mode
- [ ] Confirm no secrets are exposed in app builds or repo history
- [ ] Confirm the production environment is stable and monitored

---

## Final readiness signal
Only mark the project as production-ready when all checklist items pass in the real hosted environment.
