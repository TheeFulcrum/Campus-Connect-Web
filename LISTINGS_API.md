# Campus Connect Listings API & Schema

## Database Schema

### listings table
```sql
CREATE TABLE listings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    campus VARCHAR(120) NOT NULL,
    price DECIMAL(10,2),
    status ENUM('active','reserved','sold','removed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_listings_user_id ON listings(user_id);
CREATE INDEX idx_listings_campus ON listings(campus);
CREATE INDEX idx_listings_category ON listings(category);
CREATE INDEX idx_listings_status ON listings(status);
```

### listing_images table
```sql
CREATE TABLE listing_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    listing_id INTEGER NOT NULL,
    image_url TEXT NOT NULL,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE INDEX idx_listing_images_listing_id ON listing_images(listing_id);
```

### categories table (optional, for filtering)
```sql
CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed data
INSERT INTO categories (name, icon) VALUES
('Textbooks', '📚'),
('Electronics', '💻'),
('Furniture', '🪑'),
('Clothing', '👕'),
('Services', '🛠️'),
('Housing', '🏠'),
('Rides', '🚗'),
('Other', '📦');
```

---

## API Endpoints

### 1. Create Listing
**POST** `/api/listings/create.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "action": "create_listing",
  "title": "Physics Textbook - Halliday & Resnick",
  "description": "Great condition, minimal markings. Perfect for PHYS 101.",
  "category": "Textbooks",
  "campus": "Main Campus",
  "price": 45.00
}
```

**Response (Success):**
```json
{
  "success": true,
  "listing": {
    "id": 1,
    "user_id": 42,
    "title": "Physics Textbook - Halliday & Resnick",
    "description": "Great condition, minimal markings. Perfect for PHYS 101.",
    "category": "Textbooks",
    "campus": "Main Campus",
    "price": 45.00,
    "status": "active",
    "created_at": "2026-10-03T18:30:00Z"
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "error": "Title is required."
}
```

---

### 2. Upload Listing Images
**POST** `/api/listings/upload-image.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request:**
```
listing_id: 1
image: <file binary>
display_order: 0
```

**Response:**
```json
{
  "success": true,
  "image": {
    "id": 5,
    "listing_id": 1,
    "image_url": "/assets/listings/1_0_abc123def.jpg",
    "display_order": 0
  }
}
```

---

### 3. Get Listings (List/Search)
**GET** `/api/listings/list.php?campus=Main+Campus&category=Textbooks&status=active&page=1`

**Response:**
```json
{
  "success": true,
  "listings": [
    {
      "id": 1,
      "user_id": 42,
      "username": "alice_smith",
      "title": "Physics Textbook - Halliday & Resnick",
      "description": "Great condition, minimal markings. Perfect for PHYS 101.",
      "category": "Textbooks",
      "campus": "Main Campus",
      "price": 45.00,
      "status": "active",
      "images": [
        {
          "id": 5,
          "image_url": "/assets/listings/1_0_abc123def.jpg"
        }
      ],
      "created_at": "2026-10-03T18:30:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 47
  }
}
```

---

### 4. Get Single Listing
**GET** `/api/listings/view.php?id=1`

**Response:**
```json
{
  "success": true,
  "listing": {
    "id": 1,
    "user_id": 42,
    "username": "alice_smith",
    "real_name": "Alice Smith",
    "avatar": "/assets/avatars/alice.jpg",
    "title": "Physics Textbook - Halliday & Resnick",
    "description": "Great condition, minimal markings. Perfect for PHYS 101.",
    "category": "Textbooks",
    "campus": "Main Campus",
    "price": 45.00,
    "status": "active",
    "images": [
      {
        "id": 5,
        "image_url": "/assets/listings/1_0_abc123def.jpg"
      }
    ],
    "created_at": "2026-10-03T18:30:00Z",
    "user_profile": {
      "username": "alice_smith",
      "real_name": "Alice Smith",
      "campus": "Main Campus",
      "rating": 4.8,
      "total_sales": 12
    }
  }
}
```

---

### 5. Update Listing
**POST** `/api/listings/update.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "action": "update_listing",
  "id": 1,
  "title": "Physics Textbook - Halliday & Resnick (Updated)",
  "description": "Great condition, minimal markings. Perfect for PHYS 101. Can negotiate.",
  "price": 40.00,
  "status": "active"
}
```

**Response:**
```json
{
  "success": true,
  "listing": {
    "id": 1,
    "title": "Physics Textbook - Halliday & Resnick (Updated)",
    "price": 40.00,
    "updated_at": "2026-10-03T19:00:00Z"
  }
}
```

---

### 6. Delete Listing
**POST** `/api/listings/delete.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "action": "delete_listing",
  "id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Listing deleted."
}
```

---

## Frontend Usage Examples

### Web (JavaScript)

**Create Listing:**
```javascript
const response = await fetch('/api/listings/create.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + sessionStorage.getItem('cc-auth-token')
  },
  body: JSON.stringify({
    action: 'create_listing',
    title: 'Physics Textbook',
    description: 'Great condition',
    category: 'Textbooks',
    campus: 'Main Campus',
    price: 45.00
  })
});
const result = await response.json();
if (result.success) {
  console.log('Listing created:', result.listing.id);
}
```

**List Listings:**
```javascript
const response = await fetch('/api/listings/list.php?campus=Main+Campus&status=active');
const result = await response.json();
result.listings.forEach(listing => {
  console.log(listing.title, listing.price);
});
```

---

### Android (Kotlin)

**Create Listing:**
```kotlin
val requestBody = JSONObject().apply {
    put("action", "create_listing")
    put("title", "Physics Textbook")
    put("description", "Great condition")
    put("category", "Textbooks")
    put("campus", "Main Campus")
    put("price", 45.00)
}

AuthApiClient.send(requestBody, token) { result, error ->
    if (error == null) {
        Log.d("Listings", "Created: ${result.listing.id}")
    } else {
        Log.e("Listings", error)
    }
}
```

---

## Implementation Checklist

- [ ] Create `listings` table in SQLite
- [ ] Create `listing_images` table
- [ ] Create `categories` table with seed data
- [ ] Add migration in `auth_lib.php` to create tables if missing
- [ ] Build `/api/listings/create.php` with file upload handler
- [ ] Build `/api/listings/list.php` with filtering & pagination
- [ ] Build `/api/listings/view.php` with seller profile
- [ ] Build `/api/listings/update.php` with permission check
- [ ] Build `/api/listings/delete.php` with permission check
- [ ] Create `/assets/listings/` directory (writable by www-data)
- [ ] Add image upload handler (size/type validation)
- [ ] Build web UI for listing creation (form + image upload)
- [ ] Build web UI for listing browse/search
- [ ] Build web UI for listing detail page
- [ ] Build Android UI screens
- [ ] Test end-to-end: create → view → update → delete

---

## Security Notes

1. **Always verify token** — Check user_id matches listing owner before update/delete
2. **Rate limit** — Max 20 new listings per day per user
3. **Image validation** — Only JPEG/PNG, max 5MB per image
4. **Sanitize input** — Use prepared statements for all queries
5. **Check campus** — User can only list in their own campus(es)
6. **Moderation** — Flag inappropriate listings for review

---

## Next Phase (After MVP)

- Add `reviews` table for buyer/seller ratings
- Add `messages` table for in-listing chat
- Add `saved_listings` (favorites/watchlist)
- Add `listing_views` for analytics
- Add admin dashboard for moderation
