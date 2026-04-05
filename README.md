# AppDown - App Download Center

A self-hosted, fully configurable app download page with admin panel. Built with PHP + SQLite, zero external dependencies.

**Live Demo**: Deploy it in 5 minutes on any PHP server.

## Features

- **Dynamic Configuration** — All content managed through admin panel, no code editing needed
- **Multi-App Support** — Add unlimited apps with custom icons, theme colors, download buttons, and carousel screenshots
- **iOS Install Guide** — Auto-generated iOS enterprise certificate install page per app
- **Real-time Statistics** — Page views, download counts per app/platform, traffic source tracking, 7-day trends
- **Custom Code Injection** — Add analytics scripts, custom CSS/JS via admin panel
- **Font Management** — Upload custom fonts or use built-in system fonts
- **Feature Cards & Friend Links** — Drag-sortable content blocks
- **Responsive Design** — Mobile-first, works on all devices
- **Security** — CSRF protection, prepared statements, file upload validation, input sanitization

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Backend | PHP 8.0+ (no framework, no Composer) |
| Database | SQLite (single file, zero config) |
| Frontend | Vanilla JS + CSS (no build step) |
| Admin UI | Custom CSS + Chart.js (CDN) |
| Icons | Font Awesome 7.1.0 (local) |

## Project Structure

```
appdown/
├── index.html              # Main download page (loads config from API)
├── privacy.html            # Privacy policy
├── terms.html              # Terms of service
├── style.css               # Shared styles for policy pages
├── install.php             # One-time installer (delete after setup)
│
├── api/                    # Public APIs
│   ├── config.php          # GET: Full site config JSON (cached)
│   └── track.php           # POST: Visit/download event tracking
│
├── ios/
│   └── index.php           # Dynamic iOS install guide (/ios/?app=slug)
│
├── includes/               # PHP libraries (not web-accessible)
│   ├── db.php              # SQLite connection + schema
│   ├── auth.php            # Session authentication
│   ├── csrf.php            # CSRF token validation
│   ├── helpers.php         # Utility functions
│   ├── upload.php          # File upload handler
│   ├── init.php            # Bootstrap
│   └── layout.php          # Admin page layout
│
├── admin/                  # Admin panel
│   ├── login.php           # Login page
│   ├── dashboard.php       # Statistics dashboard
│   ├── apps.php            # App list management
│   ├── app-edit.php        # Single app editor (downloads + images + iOS config)
│   ├── settings.php        # Site settings
│   ├── features.php        # Feature cards
│   ├── links.php           # Friend links
│   ├── fonts.php           # Font management
│   ├── custom-code.php     # Custom CSS/JS injection
│   ├── api/                # Admin AJAX endpoints
│   └── assets/             # Admin CSS/JS
│
├── static/                 # Static assets
│   └── fontawesome-free-7.1.0-web/
│
├── data/                   # SQLite database (auto-created)
│   └── app.db
│
└── uploads/                # User-uploaded files
    ├── images/
    ├── fonts/
    └── apps/
```

## Quick Start

### Requirements

- PHP 8.0+ with `pdo_sqlite` and `fileinfo` extensions
- Nginx or Apache
- No MySQL, no Composer, no Node.js needed

### Installation

1. **Upload** the project to your web server root

2. **Create directories** (if not exists):
   ```bash
   mkdir -p data uploads/images uploads/fonts uploads/apps
   chmod 755 data uploads
   ```

3. **Run installer** — visit `https://yourdomain.com/install.php` in your browser
   - Creates the database and seeds default data
   - Generates an admin account (username: `admin`)
   - **Save the displayed password!**

4. **Delete install.php** immediately after setup:
   ```bash
   rm install.php
   ```

5. **Login** at `https://yourdomain.com/admin/`

### Nginx Security Rules

Add these to your Nginx server block:

```nginx
# Block access to database and includes
location ~* ^/(data|includes)/ {
    deny all;
    return 404;
}

# Block hidden files
location ~ /\. {
    deny all;
    return 404;
}

# Prevent PHP execution in uploads
location ~* ^/uploads/.*\.php$ {
    deny all;
    return 404;
}
```

### BT Panel (宝塔面板) Users

See [DEPLOY.md](DEPLOY.md) for step-by-step Chinese instructions.

## Admin Panel

| Page | Function |
|------|----------|
| Dashboard | Today's visits/downloads, 7-day trend chart, traffic sources |
| Apps | Add/edit/delete/sort apps, manage download buttons and carousel images per app |
| App Edit | Configure downloads, screenshots, and iOS install page settings |
| Settings | Site title, logo, notice, stats display numbers, carousel interval |
| Features | Manage feature highlight cards (drag-sortable) |
| Links | Manage footer friend links (drag-sortable) |
| Fonts | Upload custom fonts or select built-in system fonts |
| Custom Code | Inject custom CSS/JS in head or footer (e.g., analytics scripts) |

## API Reference

### Public

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/config.php` | Returns full site configuration JSON |
| POST | `/api/track.php` | Records visit/download events |

### Admin (requires login + CSRF token)

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/admin/api/apps.php` | GET/POST/PUT/DELETE | App CRUD |
| `/admin/api/downloads.php` | GET/POST/PUT/DELETE | Download button CRUD |
| `/admin/api/images.php` | GET/POST/DELETE | Carousel image CRUD |
| `/admin/api/settings.php` | GET/POST | Site settings read/write |
| `/admin/api/features.php` | GET/POST/PUT/DELETE | Feature card CRUD |
| `/admin/api/links.php` | GET/POST/PUT/DELETE | Friend link CRUD |
| `/admin/api/custom-code.php` | GET/POST | Custom code read/write |
| `/admin/api/upload.php` | POST | File upload (image/font/app) |
| `/admin/api/reorder.php` | POST | Drag-sort ordering |
| `/admin/api/dashboard.php` | GET | Dashboard statistics |

## Local Development

Since the project uses PHP, you need a local PHP environment:

**Option 1: VS Code + PHP Server extension**
1. Install [PHP](https://www.php.net/downloads) on your system
2. Install the [PHP Server](https://marketplace.visualstudio.com/items?itemName=brapifra.phpserver) VS Code extension
3. Right-click `index.html` → "PHP Server: Serve Project"

**Option 2: PHP built-in server** (recommended)
```bash
cd /path/to/appdown
php -S localhost:8000
```
Then visit `http://localhost:8000`

**Option 3: Docker**
```bash
docker run -d -p 8080:80 -v $(pwd):/var/www/html php:8.2-apache
```

## Upload Limits

| Category | Max Size | Allowed Types |
|----------|----------|---------------|
| Image | 5 MB | jpg, jpeg, png, gif, webp, svg, ico |
| Font | 10 MB | ttf, woff, woff2, otf |
| App | 200 MB | apk, ipa, exe, dmg, zip |

## License

[MIT](LICENSE)
