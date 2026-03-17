# ePlayHD — Laravel 11 Conversion

A full-stack sports streaming platform converted from React/Supabase to **Laravel 11 + MySQL**.

---

## 🚀 Quick Setup

### 1. Requirements
- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js (optional, for asset compilation)

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials:

```env
APP_NAME="ePlayHD"
APP_URL=https://yourdomain.com

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eplayhd
DB_USERNAME=root
DB_PASSWORD=yourpassword

CACHE_STORE=database
SESSION_DRIVER=database
```

### 4. Create Database & Import Schema

```bash
mysql -u root -p -e "CREATE DATABASE eplayhd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p eplayhd < database/eplayhd_mysql.sql
```

The SQL file includes:
- All tables with proper indexes and foreign keys
- Default admin user: **admin@eplayhd.com / admin123**
- Default sports (Cricket, Football, Basketball, etc.)
- Default site settings

### 5. Set Permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 6. Configure Web Server

**Apache** — create a virtual host pointing to `/public`:
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /path/to/laravel-eplayhd/public
    <Directory /path/to/laravel-eplayhd/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/laravel-eplayhd/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 7. Start the App

```bash
php artisan serve
# Visit http://localhost:8000
# Admin: http://localhost:8000/admin
```

---

## 🔐 Default Login

| Field    | Value               |
|----------|---------------------|
| Email    | admin@eplayhd.com   |
| Password | admin123            |

**⚠ Change this immediately after first login via Admin → Users.**

---

## 📁 Project Structure

```
laravel-eplayhd/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # All admin CRUD controllers
│   │   │   └── *.php           # Public controllers
│   │   └── Middleware/
│   │       ├── AdminMiddleware.php
│   │       └── MaintenanceModeMiddleware.php
│   ├── Models/                 # 22 Eloquent models (all UUID PKs)
│   ├── Providers/
│   │   └── ViewServiceProvider.php  # Global view data
│   └── Services/
│       └── CricketApiService.php    # CricAPI score sync
├── bootstrap/app.php           # Middleware + provider registration
├── config/
│   ├── app.php
│   ├── database.php
│   ├── cache.php
│   └── session.php
├── database/
│   └── eplayhd_mysql.sql       # Complete schema + seed data
├── public/
│   ├── css/app.css             # Full custom CSS (light/dark themes)
│   └── js/app.js               # HLS player, slider, theme toggle
├── resources/views/
│   ├── layouts/
│   │   ├── app.blade.php       # Public layout (SEO, OG, GA, AdSense)
│   │   └── admin.blade.php     # Admin layout (sidebar nav)
│   ├── components/             # Reusable Blade components
│   ├── pages/                  # Public pages
│   └── admin/                  # All admin panel views
└── routes/web.php              # Complete route definitions
```

---

## ⚙️ Key Features

### Public Site
- **Home page** — Live / Upcoming / Completed matches with sport filter tabs
- **Match page** — Streaming servers (HLS/iFrame/YouTube), Playing XI, Innings, Points Table, API Scorecard
- **Tournament page** — All matches + points table
- **Channels** — TV channel live streams
- **Dynamic pages** — CMS pages (Privacy Policy, Terms, etc.)
- **Dark/Light theme** — Persisted in localStorage
- **Auto-refresh** — Live match scores poll every 30s
- **SEO** — Schema.org, OG tags, sitemap.xml, robots.txt

### Admin Panel (`/admin`)
| Section | Features |
|---------|----------|
| Matches | Create/edit/delete, status control, score update, streaming servers, playing XI, innings, points table, API sync |
| Tournaments | CRUD + points table management |
| Teams | CRUD with logo support |
| Sports | CRUD |
| Channels | CRUD + streaming servers |
| Banners | Hero slider management with drag-drop reorder |
| Pages | CMS pages (HTML/Markdown) |
| Menus | Custom navigation menus with dropdown support |
| Sponsor Notices | Global or match-specific notices |
| Saved Servers | Reusable server templates |
| Users | User management with role assignment |
| Settings | Full site configuration (SEO, Ads, API, Maintenance, Custom Code) |

### API Endpoints
```
GET  /api/matches
GET  /api/match/{id}/score
GET  /api/match/{id}/servers
GET  /api/match/{id}/innings
GET  /api/match/{id}/playing-xi
POST /api/match/{id}/report-not-working
POST /api/match/{id}/report-working
GET  /api/tournaments
GET  /api/tournament/{id}/points-table
GET  /api/channels
```

---

## 🏏 Cricket API Integration

1. Get a free API key from [CricAPI](https://cricapi.com/)
2. Go to **Admin → Settings → API** tab
3. Enter your API key and enable Cricket API
4. On each match, set the **CricAPI Match ID** in the Edit form
5. Use the **🔄 Sync API** button on the match page to pull live scores

---

## 🎨 Theme Customization

CSS variables in `public/css/app.css`:

```css
:root {
    --primary: hsl(20, 90%, 48%);  /* Orange */
    --background: hsl(0, 0%, 96%);
    --card: hsl(0, 0%, 98%);
    /* ... */
}
.dark {
    --background: hsl(0, 0%, 9%);
    /* ... */
}
```

---

## 📋 Notes

- All primary keys are **UUIDs** generated via MySQL `UUID()` (requires MySQL 8.0+)
- `matches` is a reserved PHP keyword — the Model class is `App\Models\Match` (works fine in Laravel namespace)
- Cache driver should be `database` (matches sessions) — tables are included in the SQL schema
- The `admin_slug` setting in Site Settings can change the admin URL prefix, but requires a web server rewrite rule to take effect
- Maintenance mode allows logged-in admins to bypass the maintenance page
- HLS.js is loaded from CDN on-demand only when a match page has video players

---

## 🔄 Scheduled Tasks (Optional)

Add to crontab for auto-sync:
```bash
* * * * * cd /path/to/laravel-eplayhd && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🗺 Sitemap

Auto-generated at `/sitemap.xml` with:
- Home, Channels
- All active match pages
- All tournament pages
- All channel pages
- All CMS pages

Ping search engines from **Admin → Settings → SEO → Ping Search Engines**.
