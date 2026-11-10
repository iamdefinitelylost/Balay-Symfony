# Balay – Symfony + Docker

Same app as the XAMPP version (stay booking, customer accounts, admin panel with photo uploads), now on
Symfony 7.4, PHP 8.3, MySQL 8 and Docker Compose.

## Run it
1. Install and start **Docker Desktop**.
2. In this folder:
   ```bash
   docker compose up -d --build
   docker compose logs -f php      # first start runs `composer install`; wait for "ready to handle connections"
   ```
3. Open http://localhost:8080
4. phpMyAdmin: http://localhost:8081 (root / root). MySQL from your PC: `127.0.0.1:3307`.

Logins (change these passwords after the first login):

| Role | Email | Password | Lands on |
|---|---|---|---|
| Admin | `admin@balay.test` | `Admin123!` | `/admin` |
| Staff | `staff@balay.test` | `Staff123!` | `/records` |
| Customer | sign up on the site | | `/` |

The staff/admin login page is http://localhost:8080/login (the Log in button on the home page works for everyone too).

## Everyday commands
```bash
docker compose exec php php bin/console cache:clear
docker compose exec php composer require <package>
docker compose down            # stop (data kept)
docker compose down -v         # stop AND wipe the database (schema.sql re-imports next start)
```

## Where things moved
| XAMPP version              | Symfony version                                   |
|----------------------------|---------------------------------------------------|
| `config.php` (db, session) | `config/services.yaml`, `.env`, `config/packages/framework.yaml` |
| `index.php`, `account.php`, `admin/index.php` | `src/Controller/PageController.php` + `templates/*.twig` |
| `api/auth.php`             | `src/Controller/Api/AuthController.php` (`/api/auth/login`, `/register`, `/logout`, `/me`) |
| `api/listings.php`         | `ListingController.php` (`/api/listings`)         |
| `api/book.php`, `api/my_bookings.php` | `BookingController.php` (`/api/book`, `/api/my-bookings`) |
| `api/admin.php`            | `AdminController.php` (`/api/admin/<action>`)     |
| `api/upload.php`           | `UploadController.php` (`/api/admin/upload`)      |
| `attach_images()`, `json_out()` errors | `src/Service/ListingImages.php`, `src/EventListener/ApiExceptionListener.php` |
| `assets/`                  | `public/assets/` (JS now calls the new URLs)      |
| `uploads/` + `.htaccess`   | `public/uploads/` + the `/uploads/` rule in `docker/nginx/default.conf` |

## Roles and access control (Final Project rubric)

| Feature | Where |
|---|---|
| Login / logout | `config/packages/security.yaml` (`form_login`, `json_login`, `logout`), `SecurityController` |
| View own profile, change own password | `ProfileController` (`/profile`, `/profile/password`) - all roles |
| Admin: create / view / edit / reset password / delete / disable accounts | `AdminController` (`/admin/users...`); delete asks for confirmation on its own page |
| Admin dashboard (total users, staff, records) | `AdminController::dashboard` (`/admin`) |
| Records CRUD (stays), search and filter | `RecordController` (`/records`) |
| Staff edits/deletes **own** records only, admin edits/deletes **any** | `src/Security/RecordVoter.php` (`RECORD_MANAGE`) |
| Role rules | `security.yaml` `access_control` + `#[IsGranted]` on controllers + `is_granted()` in `templates/panel/base.html.twig` |
| Staff typing an admin URL | **403 Access Denied** page (`src/Security/AccessDeniedHandler.php`, `templates/errors/403.html.twig`) |
| Disabled accounts | `UserChecker` blocks login; `UserProvider::refreshUser` logs them out on their next request |
| CSRF protection | every POST form carries a `csrf_token()` that the controller checks |

Roles live in `users.role` (`customer`, `staff`, `admin`); `ROLE_ADMIN` inherits `ROLE_STAFF`.
