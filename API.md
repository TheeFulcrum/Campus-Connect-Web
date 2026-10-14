
# Campus Connect API Reference

Base URL: `https://auth.campusconnect.ink`
Auth: `Authorization: Bearer <token>` on all endpoints marked 🔒. Tokens come from `auth_api.php` (`register`, `verify_otp`, `login`).

**Response shape (all endpoints):**
- Success: `{"success": true, ...}`
- Error: `{"success": false, "error": "..."}` with HTTP 400 / 401 / 403 / 404 / 405 / 409 / 422 / 429 / 500
- Pagination: `?page=1&limit=20` (limit capped at 50) → `{"pagination": {"page": 1, "per_page": 20, "total": 123}}`

**Rate limits** (429 on exceed): login — 10/5min per IP · OTP — 5/5min per IP · search — 60/min per IP · message send — 30/min per user · uploads — 20/hour per user.

---

## Profile

### GET /api/profile/get.php
Public; pass a token to get `is_me` / `is_following`.

```
GET /api/profile/get.php?username=alice01
```
```json
{
  "success": true,
  "profile": {
    "username": "alice01", "display_name": "Alice", "real_name": "",
    "bio": "Selling textbooks", "link": "https://...", "campus": "Main Campus",
    "avatar_url": "/api/uploads/serve.php?f=avatars/ab12....jpg",
    "created_at": "2026-09-01",
    "counts": {"listings": 3, "followers": 12, "following": 8},
    "rating": {"count": 4, "average": 4.8},
    "note": {"body": "On campus until 5pm", "expires_at": 1759600000},
    "is_me": false, "is_following": true
  }
}
```

### POST /api/profile/update.php 🔒
```json
{"display_name": "Alice", "username": "alice01", "bio": "...", "link": "https://...", "campus": "Main Campus"}
```
All fields optional; only provided keys are updated. Username: 3–30 chars `[A-Za-z0-9_.]`, unique (409 if taken). Bio ≤ 500 chars. Link must start `http(s)://`. Returns `{"success": true, "profile": {...}}`.

### POST /api/profile/avatar.php 🔒 (multipart)
```
curl -X POST .../api/profile/avatar.php -H "Authorization: Bearer $T" -F "avatar=@photo.jpg"
```
JPEG/PNG/WebP/GIF ≤ 5MB, verified by content (finfo), re-encoded via GD, resized to ≤512px, stored outside web root with a random name. → `{"success": true, "avatar_url": "/api/uploads/serve.php?f=avatars/....jpg"}`

### GET /api/profile/username-check.php
`?username=alice01` → `{"success": true, "available": false}` (the current user may keep their own username).

### POST /api/profile/note.php 🔒
The 60-character profile status note shown above the avatar; one per user, expires 24h after save.
```json
{"action": "save", "body": "On campus until 5pm"}
{"action": "delete"}
```
→ 201 `{"success": true, "note": {"id": 1, "body": "...", "created_at": "...", "expires_at": 1759600000}}`. Body must be 1–60 chars. Saving replaces any existing note. `GET /api/profile/get.php` includes `profile.note` (`{body, expires_at}` or `null`).

### Profile tabs
- `GET /api/profile/listings.php?username=alice01&page=1` — active, non-archived listings
- `GET /api/profile/saved.php` 🔒 — owner only
- `GET /api/profile/sold.php?username=alice01` — sold history
- `GET /api/profile/reviews.php?username=alice01` — reviews + `rating` summary

All return `{"success": true, "listings"|"reviews": [...], "pagination": {...}}`. Each listing includes `cover_image`.

---

## Follow

### POST /api/follow/follow.php 🔒 → `{"username": "bob02"}` → `{"success": true, "following": true, "followers": 13}`
### POST /api/follow/unfollow.php 🔒 → same shape, `"following": false`
### GET /api/follow/followers.php?username=alice01&q=bob&page=1
### GET /api/follow/following.php?username=alice01&q=bob&page=1
Both → `{"success": true, "users": [{"username", "display_name", "real_name", "bio", "campus", "avatar_url", "followed_at"}], "pagination": {...}}`

---

## Listings — new actions (all 🔒, POST JSON, owner-only except save)

| Endpoint | Body | Notes |
|---|---|---|
| `/api/listings/save.php` | `{"listing_id": 1}` | → `{"success": true, "saved": true, "saves": 5}` |
| `/api/listings/unsave.php` | `{"listing_id": 1}` | → `"saved": false` |
| `/api/listings/mark-sold.php` | `{"listing_id": 1}` | Sets `status=sold`, `sold_at`. 403 if not owner |
| `/api/listings/archive.php` | `{"listing_id": 1}` | Hides from public browse |
| `/api/listings/restore.php` | `{"listing_id": 1}` | Un-archives |

### POST /api/listings/upload-images.php 🔒 (multipart)
```
curl -X POST .../api/listings/upload-images.php -H "Authorization: Bearer $T" \
  -F "listing_id=1" -F "images[]=@a.jpg" -F "images[]=@b.jpg"
```
Up to 10 images/call, ≤ 5MB each, re-encoded + resized ≤1600px. With `listing_id`, attaches immediately (owner check). Without it, returns serving URLs you can pass to create/update.
→ `{"success": true, "images": [{"id": 7, "image_url": "/api/uploads/serve.php?f=listings/....jpg"}]}`

### POST /api/listings/create.php 🔒 — now accepts optional `"image_ids": ["/api/uploads/serve.php?f=...", ...]`
### POST /api/listings/update.php 🔒 — same optional `image_ids` (appends images)
### GET /api/listings/list.php — now excludes archived listings, honors `?limit=` (cap 50), includes `is_saved: true/false` per listing when a token is sent.

---

## Collections 🔒 (private to owner)

- `POST /api/collections/create.php` `{"name": "Textbooks"}` → 201 `{"collection": {"id", "name", "created_at"}}`
- `GET  /api/collections/list.php` → collections with `items` and `item_count`
- `POST /api/collections/add-item.php` `{"collection_id": 1, "listing_id": 5}`
- `POST /api/collections/remove-item.php` `{"collection_id": 1, "listing_id": 5}`
- `POST /api/collections/delete.php` `{"collection_id": 1}`

## Notes 🔒 (private, auto-expire 24h)

Listing-scoped notes. For the profile status note, see `api/profile/note.php` above.

- `POST /api/notes/create.php` `{"listing_id": 5, "body": "Offer 20?"}` → 201, note includes `expires_at` (unix)
- `GET  /api/notes/list.php?page=1` → only unexpired notes
- `POST /api/notes/delete.php` `{"id": 3}`

## Reviews 🔒

- `POST /api/reviews/create.php` `{"listing_id": 5, "rating": 5, "comment": "Great seller"}`
  - Requires: listing is `sold`, and the caller has a conversation about that listing with the seller (proves a transaction). One review per transaction (409 on duplicate). Rating 1–5.
- `GET /api/reviews/list.php?username=alice01&page=1` → reviews received + average

## Notifications 🔒

- `GET  /api/notifications/list.php?page=1` → newest first, includes `actor_username`, `actor_avatar_url`
- `POST /api/notifications/read-all.php` → `{"success": true}`
- `GET  /api/notifications/unread-count.php` → `{"success": true, "unread": 3}`
- Types emitted: `follow`, `message`, `review`

## Search

`GET /api/search/index.php?q=calc&page=1` →
```json
{"success": true, "query": "calc",
 "users": [{"username", "display_name", "real_name", "bio", "campus", "avatar_url"}],
 "listings": [{"id", "title", "description", "price", "category", "campus", "created_at", "seller_username", "cover_image"}],
 "pagination": {"page": 1, "per_page": 20, "users_total": 2, "listings_total": 7}}
```
Rate-limited 60/min/IP. Only active, non-archived listings.

## Messages 🔒 (participants only)

- `GET  /api/messages/conversations.php` → conversations with `listing_title`, `other_username`, `other_avatar_url`, `last_message`, `unread`
- `GET  /api/messages/thread.php?conversation_id=1&after_id=42` → messages; `after_id` returns only newer ones (polling). Marks incoming messages read.
- `POST /api/messages/send.php` — reply: `{"conversation_id": 1, "body": "..."}` · start new: `{"listing_id": 5, "body": "..."}`, `{"recipient_id": 2, "body": "..."}`, or `{"recipient": "bob02", "body": "..."}` (username). Reuses an existing conversation for the same listing + pair. 1–2000 chars.
- `POST /api/messages/mark-read.php` `{"conversation_id": 1}`
- `GET  /api/messages/unread-count.php` → `{"success": true, "unread": 4}`

## Uploads

`GET /api/uploads/serve.php?f=avatars/<32hex>.jpg` — the only way files are served. Validates the path pattern, re-sniffs MIME, sends `X-Content-Type-Options: nosniff`. Files live outside the web root in `CCW_UPLOAD_DIR` (default `/var/www/campusconnect-data/uploads`).

---

# Handoff — Current State (for the next agent)

## Stack & layout
- PHP + SQLite (`ccw_db()` singleton in [auth_lib.php](auth_lib.php)), nginx, no framework, no build step.
- Web root: `/var/www/campusconnect` on `129.121.149.158`; prod API at `https://auth.campusconnect.ink`, site at `https://campusconnect.ink`.
- Auth: bearer token in `sessionStorage['cc-auth-token']` (client) → `Authorization: Bearer` header → `ccw_verify_token_and_get_user($token)` returns the **full user row** or exits 401 JSON. Optional-auth reads use `ccw_user_for_token()`.

## DB
- Schema lives entirely in `ccw_db()` as idempotent `CREATE TABLE IF NOT EXISTS` + try/catch `ALTER TABLE ADD COLUMN` — runs automatically on first request; there is no migration runner.
- Tables: users, tokens, otp_codes, password_reset_tokens, listings (+ `archived`, `sold_at`), listing_images, follows, saves, collections, collection_items, notes (`listing_id` nullable — legacy DBs self-heal via a one-time table rebuild in `ccw_db()`), reviews, conversations, messages, notifications, rate_limits.
- Shared helpers in auth_lib.php: `ccw_profile_shape()`, `ccw_avatar_url()`, `ccw_notify()`, `ccw_pagination()` (page/limit, cap 50). Rate limiting: `ccw_rate_limit_or_fail()` in [api/_rate_limit.php](api/_rate_limit.php). Uploads: `ccw_store_image_upload()` / `ccw_upload_url()` in [api/_uploads.php](api/_uploads.php) (finfo-validated, GD re-encoded, random names, stored outside web root).

## Frontend conventions
- Vanilla JS, DOM built with `textContent`/`createElement` — never `innerHTML` with user data.
- [shared-header.js](shared-header.js) is loaded on every authenticated page and injects: desktop icon sidebar, search + notifications slide-out panels, menu popover, floating messages pill + chat drawer, mobile top-bar icons, unread-badge polling (30s). It exposes `window.ccToast(msg, isErr)` and `window.ccUpdateBadges()`.
- [profile-api.js](profile-api.js) powers profile.html: `?u=username` for other users, `?tab=` synced via pushState, IntersectionObserver infinite scroll, `profile.html?u=X` links everywhere.
- [messages.js](messages.js) supports `?conversation=<id>` (open thread) and `?u=<username>` (compose mode — first message creates the conversation).
- Theme: CSS variables only (`--primary`, `--accent`, `--bg`, `--surface`, `--text`, `--text-muted`, `--border`, `--error`, `--success`, `--radius`), dark mode via `[data-theme="dark"]` + `localStorage['cc-theme']`.

## Verified working
- All PHP files pass `php -l`; all JS passes `node --check`.
- Local runtime testing was NOT possible (dev machine's PHP CLI lacks `pdo_sqlite`); test on the server. Deploy + test steps: [PROFILE_UI_BUILD.md](PROFILE_UI_BUILD.md) and [NGINX_UPLOADS.md](NGINX_UPLOADS.md).

## Known gaps / roadmap
- No delete-conversation endpoint (the chat ⋯ button routes to the seller's profile instead).
- Report action in the profile ⋯ menu is client-side only (no `api/reports/` yet).
- Reviews require listing `sold` + an existing buyer↔seller conversation for that listing.
- Uploads require `php-gd` on the server.
