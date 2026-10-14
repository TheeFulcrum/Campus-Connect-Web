# Profile Feature Build — Summary & Test Checklist

Everything runs through the existing `ccw_db()` (SQLite) + `ccw_verify_token_and_get_user()` token check. No new colors, fonts, or scaffolding. All writes use prepared statements + ownership/participant checks. Migrations are idempotent (`CREATE TABLE IF NOT EXISTS` + try/catch `ALTER TABLE`), so they run automatically on first request — no separate migration script needed.

## Files changed (existing)
- [auth_lib.php](auth_lib.php) — migrations for 10 new tables + 5 ALTERs + all FK indexes; `ccw_verify_token_and_get_user()` now returns the full user row; added `ccw_avatar_url()`, `ccw_profile_shape()`, `ccw_notify()`, `ccw_pagination()`
- [api/listings/list.php](api/listings/list.php) — `?limit=` (cap 50), hides archived, adds `is_saved` when authed
- [api/listings/create.php](api/listings/create.php) — accepts `image_ids`
- [api/listings/update.php](api/listings/update.php) — accepts `image_ids`
- [login.php](login.php) + [auth_api.php](auth_api.php) — rate-limit hooks (login 10/5min, OTP 5/5min per IP)

## Files created
- Helpers: [api/_rate_limit.php](api/_rate_limit.php), [api/_uploads.php](api/_uploads.php), [api/uploads/serve.php](api/uploads/serve.php)
- Profile: [get](api/profile/get.php), [update](api/profile/update.php), [avatar](api/profile/avatar.php), [username-check](api/profile/username-check.php), [listings](api/profile/listings.php), [saved](api/profile/saved.php), [sold](api/profile/sold.php), [reviews](api/profile/reviews.php)
- Follow: [follow](api/follow/follow.php), [unfollow](api/follow/unfollow.php), [followers](api/follow/followers.php), [following](api/follow/following.php)
- Listings: [save](api/listings/save.php), [unsave](api/listings/unsave.php), [mark-sold](api/listings/mark-sold.php), [archive](api/listings/archive.php), [restore](api/listings/restore.php), [upload-images](api/listings/upload-images.php)
- Collections: [create](api/collections/create.php), [list](api/collections/list.php), [add-item](api/collections/add-item.php), [remove-item](api/collections/remove-item.php), [delete](api/collections/delete.php)
- Notes: [create](api/notes/create.php), [list](api/notes/list.php), [delete](api/notes/delete.php)
- Reviews: [create](api/reviews/create.php), [list](api/reviews/list.php)
- Notifications: [list](api/notifications/list.php), [read-all](api/notifications/read-all.php), [unread-count](api/notifications/unread-count.php)
- Search: [index](api/search/index.php)
- Messages: [conversations](api/messages/conversations.php), [thread](api/messages/thread.php), [send](api/messages/send.php), [mark-read](api/messages/mark-read.php), [unread-count](api/messages/unread-count.php)
- Docs: [API.md](API.md), [NGINX_UPLOADS.md](NGINX_UPLOADS.md)

## Deploy steps (production untouched by me)
```bash
scp -r ~/Downloads/CCW/api root@129.121.149.158:/var/www/campusconnect/
scp ~/Downloads/CCW/auth_lib.php ~/Downloads/CCW/auth_api.php ~/Downloads/CCW/login.php root@129.121.149.158:/var/www/campusconnect/
ssh root@129.121.149.158 'mkdir -p /var/www/campusconnect-data/uploads && chown -R www-data:www-data /var/www/campusconnect-data && chmod 750 /var/www/campusconnect-data /var/www/campusconnect-data/uploads && chown -R www-data:www-data /var/www/campusconnect && php -m | grep -iE "gd|pdo_sqlite" || apt-get install -y php-gd php-sqlite3'
# add CCW_UPLOAD_DIR=/var/www/campusconnect-data/uploads to /var/www/campusconnect/.env
# add nginx block from NGINX_UPLOADS.md, then: nginx -t && systemctl reload nginx
```
The DB migrates itself on the first request to any endpoint.

## Feature-by-feature test checklist

**Migrations** — hit any endpoint once, then `sqlite3 /var/www/campusconnect/ccw.sqlite ".tables"` shows follows, saves, collections, collection_items, notes, reviews, conversations, messages, notifications, rate_limits.

**Profile**
- [ ] `GET api/profile/get.php?username=X` → counts, rating, is_me=false; with token on own username → is_me=true
- [ ] `POST api/profile/update.php` with new username → 200; same username from a 2nd account → 409
- [ ] `POST api/profile/avatar.php -F avatar=@x.jpg` → avatar_url loads via serve.php; try a `.php` renamed to `.jpg` → rejected
- [ ] `username-check.php?username=taken` → `available:false`

**Follow**
- [ ] follow → `following:true`, follower count +1, target gets a notification; unfollow → count -1; follow yourself → 422
- [ ] `followers.php?username=X&q=...` filters; `?limit=200` returns at most 50

**Listings**
- [ ] save/unsave toggle `saves` count; `list.php` with token shows `is_saved:true`
- [ ] mark-sold sets status+sold_at; listing disappears from `list.php?status=active`, appears in `profile/sold.php`
- [ ] archive → hidden from `list.php` + search; restore → back
- [ ] upload-images with 2 files → 2 serve.php URLs; create listing with `image_ids` → `view.php` returns them

**Collections / Notes**
- [ ] create collection → add-item → list shows item; remove-item → gone; another user's collection_id → 403
- [ ] create note → list shows it with expires_at = created+24h; note for another user's id returns only own

**Reviews**
- [ ] review on active listing → 422; on sold listing without a conversation → 403; as the buyer after mark-sold → 201; second review on same listing → 409
- [ ] `profile/reviews.php?username=seller` shows rating average

**Notifications**
- [ ] unread-count increments on follow/message/review; read-all → unread 0; list shows newest first

**Messages**
- [ ] send with `listing_id` → creates conversation; send again → same conversation_id; `thread.php` shows both; `?after_id=<last>` returns only new
- [ ] a third user's token on `thread.php` / `send.php` for that conversation → 403; `mark-read.php` clears sender's unread badge; `unread-count.php` drops

**Search** — `?q=` finds users by username/display_name and listings by title; archived listings excluded

**Rate limits** — 11 rapid logins from one IP → 429; 6 OTP requests → 429; 31 messages in a minute → 429

**Upload security** — direct hit to `https://campusconnect.ink/uploads/...` → 403/404 (nginx deny); serve.php with `f=../../.env` → 400
