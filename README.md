# Bright English Coaching — Booking & Coaching Website

A full-stack PHP website for a 1-on-1 coaching business: a public marketing site with an appointment booking flow, an email-gated contact form, a testimonials system with video support, and a custom admin dashboard for managing bookings, availability, testimonials, and site content — no third-party CMS or booking SaaS required.

> This repo is a sanitized portfolio copy. Business name, logo, contact details, and photos have been replaced with generic placeholders; the code and architecture are unchanged from the live implementation.

## Features

**Public site**
- Responsive marketing site (hero, program details, FAQ, testimonials) with light/dark mode
- Real-time appointment booking against admin-defined availability, with a minimum-notice buffer
- Contact form emailed via SMTP (PHPMailer), with autoresponder-friendly HTML + plaintext bodies
- Testimonial submission form (photo/video optional) that queues into an admin approval flow
- SEO: per-page meta tags, Open Graph/Twitter cards, and JSON-LD structured data (`ProfessionalService` + `FAQPage`)
- Google Analytics (GA4) and Google Ads conversion tracking hooks

**Admin dashboard** (session-authenticated, bcrypt password hashing)
- Manage upcoming/pending/completed/voided bookings
- Open/close availability by date and time slot, individually or in bulk ranges
- Approve, edit, or delete testimonials; attach video URLs to approved ones
- Edit site copy (hero headline, pricing, FAQ, etc.) and swap the about-me photo without touching code

## Tech stack

- **Backend:** PHP 8, PDO (MySQL/MariaDB), sessions for auth
- **Email:** [PHPMailer](https://github.com/PHPMailer/PHPMailer) over SMTP
- **Frontend:** Server-rendered HTML, Tailwind CDN, vanilla JS (no build step)
- **Config:** Environment variables via a lightweight `.env` loader (see below)

## Project structure

```
├── index.php                 # Public homepage
├── book.php                  # Booking flow
├── thank-you.php             # Post-booking confirmation + ad conversion tag
├── privacy.php / terms.php   # Legal pages
├── submit-review.php         # Public testimonial submission
├── admin_login.php           # Admin auth
├── admin_dashboard.php       # Booking management
├── availability_manager.php  # Slot management
├── testimonials_page.php     # Testimonial moderation
├── content_page.php          # Site copy / image editor
├── config.php                # DB connection + env loading
├── assets/                   # CSS, logos, uploaded images
├── PHPMailer/                # Vendored PHPMailer library
└── .env.example               # Template for required environment variables
```

## Getting started

### 1. Requirements
- PHP 8.0+
- MySQL or MariaDB
- An SMTP-capable email account (Gmail app password works fine)

### 2. Configure environment variables
Copy the template and fill in real values:
```bash
cp .env.example .env
```
```env
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password

EMAIL_USER=your_email@example.com
EMAIL_PASS=your_email_app_password

COACH_TIMEZONE=America/Boise
MIN_BOOKING_NOTICE_HOURS=12
```
`.env` is git-ignored — never commit real credentials.

### 3. Create the database tables
The app expects the following tables (reconstructed from the queries in the codebase; adjust types/constraints to taste):

```sql
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE available_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_date DATE NOT NULL,
    slot_time TIME NOT NULL
);

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT,
    requested_date DATE NOT NULL,
    requested_time TIME NOT NULL,
    status ENUM('pending','contacted','completed','voided') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    client_role VARCHAR(255),
    quote TEXT NOT NULL,
    rating TINYINT DEFAULT 5,
    video_url VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE site_content (
    section_key VARCHAR(100) PRIMARY KEY,
    content TEXT
);
```

Then create your first admin user (hash a password with `password_hash()` and insert it into `admins` directly — this project intentionally has no bootstrap script in the repo, to avoid shipping a hardcoded admin password).

### 4. Serve it
Point your web server's document root at this folder, or run PHP's built-in server for local testing:
```bash
php -S localhost:8000
```

## Security notes

- All secrets are loaded from environment variables, never hardcoded.
- Admin passwords are hashed with bcrypt (`password_hash` / `password_verify`).
- `.htaccess` blocks direct web access to `config.php`.
- Sensitive/admin-only PHP files are excluded from `robots.txt` indexing.

## License

This is a portfolio demonstration project. All rights to the original design and content belong to their respective owner.
