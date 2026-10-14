# nginx config for uploads

Uploads are stored **outside the web root** (`/var/www/campusconnect-data/uploads`) and served only through `api/uploads/serve.php`. This means nginx never maps a URL directly onto the files, and PHP never executes inside the upload directory.

## 1. Create the directory on the server
```bash
sudo mkdir -p /var/www/campusconnect-data/uploads
sudo chown -R www-data:www-data /var/www/campusconnect-data
sudo chmod 750 /var/www/campusconnect-data /var/www/campusconnect-data/uploads
```

## 2. `.env` on the server
```
CCW_UPLOAD_DIR=/var/www/campusconnect-data/uploads
```

## 3. Server-block hardening (add to the existing campusconnect server block)

```nginx
# Never execute PHP or scripts from any uploads-ish path (defense in depth —
# our files live outside the web root, but this guards against regressions).
location ~* /(uploads?|campusconnect-data)/ {
    deny all;
}

# Cap upload body size to match the API limit (5MB images, up to 10 at once).
client_max_body_size 12M;
```

There is intentionally **no** `location /uploads/ { alias ... }` block: files are streamed by `api/uploads/serve.php`, which validates the path and re-sniffs the real MIME type before serving. If you ever decide to serve them statically instead, use this and nothing else:

```nginx
location ^~ /uploads/ {
    alias /var/www/campusconnect-data/uploads/;
    autoindex off;
    add_header X-Content-Type-Options nosniff;
    location ~ \.(php|phtml|phar|cgi|pl|py|sh)$ { deny all; }
}
```

## 4. Reload
```bash
sudo nginx -t && sudo systemctl reload nginx
```
