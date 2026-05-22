# Splennet - Student UGC Marketplace MVP

Splennet is a platform connecting brands with university student creators for short-form video content.

## Setup Instructions

1.  **Database**:
    *   Create a MySQL database named `splennet`.
    *   Import the contents of `schema.sql` into your database.
2.  **Configuration**:
    *   Copy `.env.example` to `.env` and set `APP_URL`, `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` for your host.
    *   On Hostinger, use your hosting database credentials instead of the local XAMPP defaults.
3.  **Permissions**:
    *   Ensure the `assets/uploads/` and `uploads/` directories are writable by the web server.
    *   Confirm that `.htaccess` is enabled so upload folders cannot execute PHP files.
4.  **Production Security**:
    *   Remove all migration scripts, setup pages, and any backup files from the public webroot before deployment.
    *   Do not expose `create_admin.php` or any `migrate*.php` file publicly.
    *   Verify the site loads over HTTPS and set `APP_URL` to the final secure domain.

## Hostinger Deployment Checklist

1.  Upload the application files to the Hostinger public webroot.
2.  Create the production database and import `schema.sql`.
3.  Add a `.env` file using the values from `.env.example`.
4.  Confirm the upload folders contain `.htaccess` rules that disable execution and listing.
5.  Remove any leftover test files or setup scripts before going live.
6.  Test login, uploads, notifications, and admin review flows on the production domain.

## Features

### Role: Brand
*   Register and create a brand profile.
*   Post detailed campaign briefs with budget and usage rights.
*   Review student applications and "hire" creators.
*   Review video submissions (previews).
*   Approve videos to release payment (placeholder).

### Role: Creator
*   Register and create a creator profile.
*   Submit student verification (ID & Letter).
*   Browse open campaigns.
*   Apply to campaigns with portfolio links.
*   Submit video links for approved jobs.
*   Track earnings (MVP logic).

### Role: Admin
*   Overview of platform activity.
*   Verify student status for creators.
*   Manage users and disputes (placeholder).

## Technology Stack
*   **Language**: PHP 8.x
*   **Database**: MySQL (PDO with prepared statements)
*   **Frontend**: HTML5, CSS3 (Custom Design), Vanilla JS
*   **Authentication**: Session-based with `password_hash`
