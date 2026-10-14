# Profile Experience — UI Build Summary & Test Checklist

Everything consumes the API in [API.md](API.md). No frameworks, no build step, no new colors/fonts/shadows — only the existing tokens (`--primary`, `--primary-dark`, `--accent`, `--success`, `--bg`, `--surface`, `--text`, `--text-muted`, `--border`, `--error`, `--radius`, `--transition-theme`) and the saved `cc-theme` preference.

## Files created (3)
- [shared-header.js](shared-header.js) — injected app chrome on every page: desktop 72px icon sidebar with tooltips (Home, Listings, Messages + unread badge, Search, Notifications, Create, Profile avatar, Menu popover with Settings/Preferences/Dark mode/Log out), slide-out Search panel (debounced `/api/search/`, recent searches in localStorage) and Notifications panel (`/api/notifications/`, marks read on open), floating Messages pill + chat drawer (hidden on messages page), mobile top-bar Search/Messages icons, 30s badge polling.
- [profile-api.js](profile-api.js) — profile data layer: own/other/logged-out states, optimistic follow/unfollow with live counts, tabs with `history.pushState` (`?tab=saved`) + popstate, IntersectionObserver infinite scroll, skeleton loaders, listing quick-view modal (swipe-down to close, save button, link to listing-detail), followers/following modals with debounced search, note chip + 60-char note modal (save/delete via `api/profile/note.php`), ⋯ menu (copy link, report), edit profile (avatar pick → circular crop canvas → 512px JPEG upload to `profile/avatar.php`, debounced username availability check, live 150-char bio counter, link/campus/display name via `profile/update.php`).
- [PROFILE_UI_BUILD.md](PROFILE_UI_BUILD.md) — this file.

## Files extended
- [profile.html](profile.html) — note chip, collections row, 4th Sold tab, live stats (followers/following clickable), profile link chip, action row container, ⋯ button, quick-view/follow/note/more modals, sentinel for infinite scroll, Link field + bio counter in edit modal. Loads `shared-header.js` + `profile-api.js` instead of `profile.js`.
- [style.css](style.css) — appended layout/spacing/sizing/behavior only: sidebar, panels, scrim, pills, drawer, grid (3-col square, 4px gap desktop / 2px mobile), collections scroll-snap row, skeletons, reviews, toasts, quick-view, focus-visible rings, 44px touch targets, safe-area bottom padding, three breakpoints (≥1024 / 768–1023 / <768 — sidebar and pill hidden on mobile).
- [messages.js](messages.js) — **rewritten**: localStorage demo replaced with `/api/messages/` (conversations list, thread with `after_id` polling every 4s, send, mark-read on open, unread badge, 15s conversation refresh, offline/error toasts). Supports `?u=username` compose mode — first message creates the conversation. All rendering via `textContent`/`createElement`.
- [messages.html](messages.html), [home.html](home.html), [listings.html](listings.html), [listing-detail.html](listing-detail.html), [adverts.html](adverts.html), [post.html](post.html), [settings.html](settings.html), [preferences.html](preferences.html) — added `shared-header.js`.
- [listing-detail.html](listing-detail.html) — **"Message Seller" stub removed**: now calls `api/messages/send.php` (creates/reuses conversation) and redirects into the thread. Share button uses Web Share/clipboard. Also fixed: all user data in the template is now escaped, URLs validated before use in `src`.
- [create-listing.html](create-listing.html) — drag-and-drop multi-image upload: dropzone (click/keyboard/drag), type+size validation, removable previews, XHR progress bar to `api/listings/upload-images.php`, returned URLs sent as `image_ids` with create.
- [api/profile/get.php](api/profile/get.php) — includes the active 24h profile note.
- [api/profile/note.php](api/profile/note.php) — new: save/delete the 60-char profile note (one per user, 24h expiry).
- [api/messages/send.php](api/messages/send.php) — also accepts `recipient` (username) to start a conversation.
- [api/messages/conversations.php](api/messages/conversations.php) — adds `other_id`, `other_avatar_url`.
- [auth_lib.php](auth_lib.php) — idempotent rebuild migration making `notes.listing_id` nullable (required for profile notes); safe on existing DBs.

## Token reuse note (per theming rule)
The pre-existing form-message styles in create-listing.html used hardcoded `#fee/#c33` etc. — left as-is (pre-existing). All **new** UI uses only project tokens; where a subtle background was needed (skeletons, overlays), `--border`/`--surface`/`--bg` were reused.

## Security
- No `innerHTML` with user data anywhere new; existing listing-detail.html interpolation converted to escaped values; all new rendering via `textContent`.
- All API calls send `Authorization: Bearer` from `sessionStorage['cc-auth-token']`; 401 → redirect to login.
- New API is token-based (not cookie-session), so it is not CSRF-exposed; no legacy cookie endpoints were extended.
- Focus trap + Escape in all modals, aria-labels on every icon button, roles on panels.

## Test checklist

### Desktop (≥1024px)
- [ ] Sidebar on every page; tooltips on hover; active state follows current page
- [ ] Messages badge shows live unread count; pill bottom-right opens drawer with conversations
- [ ] Search panel: type 2+ chars → users + listings; recent searches persist; offline → offline message
- [ ] Notifications panel: opens, lists, marks read (badge clears); menu popover: dark toggle + logout work
- [ ] Own profile: Edit profile + View archive buttons; other profile: Follow/Following, Message, ⋯
- [ ] Follow: count updates instantly; reverts on failure; followers/following modals search + View
- [ ] Note chip: save 60-char note, shows on profile; delete removes it; over 60 chars blocked
- [ ] Tabs: switch without reload, URL gets `?tab=saved`, back button restores tab; infinite scroll loads more
- [ ] Grid hover shows price/saves; tile click → quick view; "View details" → listing-detail.html
- [ ] Saved tab owner-only (other user's profile has no saved data); Sold tab after mark-sold; Reviews show stars
- [ ] Edit profile: avatar crop (drag + wheel zoom) uploads; username check debounces (✓/✗); bio counter live; save refreshes header
- [ ] Create listing: drag images → previews → progress bar → post → images visible on listing detail

### Mobile (390×844)
- [ ] Sidebar + pill hidden; bottom nav has safe-area padding; top bar has search + messages icons with badge
- [ ] Profile header stacks: avatar left, stats right; Edit/Archive full-width 36px
- [ ] Collections row swipes with scroll-snap; sticky tab bar; edge-to-edge grid, 2px gap
- [ ] Quick view closes on swipe-down; all targets ≥44px; no horizontal overflow (check with DevTools 390×844)
- [ ] Messages page: list → thread → send; polling brings in new messages; unread filter works
- [ ] listing-detail "Message Seller" opens the thread in messages.html

### Backend (after deploy)
- [ ] `notes` table accepts NULL `listing_id` (migration runs automatically on first request)
- [ ] `POST api/profile/note.php` saves/deletes; `profile/get.php` returns `note`
- [ ] `messages/send.php` with `{"recipient":"username"}` creates conversation

### Deploy
```bash
scp ~/Downloads/CCW/{shared-header.js,profile-api.js,messages.js,style.css,auth_lib.php} \
    ~/Downloads/CCW/{profile.html,messages.html,home.html,listings.html,listing-detail.html,adverts.html,post.html,settings.html,preferences.html,create-listing.html} \
    root@129.121.149.158:/var/www/campusconnect/
scp ~/Downloads/CCW/api/profile/note.php root@129.121.149.158:/var/www/campusconnect/api/profile/
scp ~/Downloads/CCW/api/messages/{send.php,conversations.php} root@129.121.149.158:/var/www/campusconnect/api/messages/
ssh root@129.121.149.158 'chown -R www-data:www-data /var/www/campusconnect'
```
