# UniHUB System

UniHUB is a PHP/MySQL digital university platform for verified campus communication, events, marketplace sales, cart checkout, and admin monitoring.

## Implemented Features

- Domain-restricted signup using registered university email domains.
- OTP email verification flow for new and unverified users.
- Password hashing with PHP `password_hash`.
- Role-based member/admin access.
- University-specific dashboards, events, listings, carts, and messages.
- Admin event creation/deletion, user role management, and university domain registration.
- Marketplace listing search, image upload, seller-only edit/delete/sold controls, and seller security keys.
- Buyer cart with quantity update/removal, total calculation, and simulated mobile money payment processing.
- Listing-level private message thread.
- Responsive HTML/CSS interface for desktop and mobile.

## Local Setup

1. Install and start XAMPP or WAMP with Apache and MySQL.
2. Create/import the database by running `database/schema.sql` in phpMyAdmin or MySQL.
3. Confirm database settings in `config/config.php`.
4. Open the project through Apache, for example `http://localhost/UniHUB/`.

The app shows a setup message if the MySQL database is not reachable.

## Seed Accounts

All seeded accounts use password `password`.

- Admin: `admin@std.must.ac.ug`
- Member/seller: `seller@std.must.ac.ug`

New users can register with emails ending in `@std.must.ac.ug` or `@students.mak.ac.ug`.

For local development, the generated OTP is displayed on the verification screen. In production, replace the local OTP display in `app/bootstrap.php` with `mail()` or SMTP delivery.

## Project Structure

- `index.php` loads the application from the project root.
- `public/index.php` is the front controller and page/action router.
- `public/assets/styles.css` contains the responsive UI styles.
- `public/uploads/` stores listing images.
- `app/bootstrap.php` contains database connection, auth, CSRF, OTP, and shared helpers.
- `config/config.php` contains app and database configuration.
- `database/schema.sql` creates tables and seed data.

## Original Requirements Covered

UniHUB supports user authentication, university hub assignment, events with RSVP, marketplace buying/selling, seller-specific permissions, messaging, admin controls, mobile-responsive pages, and a MySQL schema designed for multiple universities.

## Original Project Requirements

UniHUB is a web-based digital platform designed to connect university students within a secure and verified environment. The platform allows users to join a university-specific hub using their institutional email, participate in events, and buy and sell items within their campus community.

The system enforces strict domain-based authentication to ensure that only verified users access their respective university hubs.

### Objectives

- Provide a secure digital environment for university communities.
- Enable easy buying and selling.
- Support event management, opportunities, and participation.
- Ensure fast performance and ease of use.
- Allow new users to learn and use the system within 3 minutes.

### Scope

- User authentication and role management.
- Event creation and participation.
- Marketplace buying and selling within the same university.
- Admin controls and monitoring.

### Functional Requirements

- Users create accounts using name, email, and password.
- Users type keywords and see matching results.
- Users add items, change quantities, and remove items.
- System calculates totals and processes mobile payments.

### Non-Functional Requirements

- Simple, intuitive, mobile-responsive interface.
- Clear navigation menus and buttons.
- Minimal clicks for common tasks.
- Initial load target under 8 seconds.
- Subsequent page load target under 3 seconds.
- Domain validation, hashed passwords, OTP verification, role permissions, and seller listing keys.
- Multi-university scalability.

### Required Modules

- User management and authentication.
- Events with admin creation/edit/delete and member RSVP.
- Marketplace listings with images, seller controls, and same-university visibility.
- Buyer/seller messaging per listing.
- Admin dashboard with users, events, listings, role management, listing moderation, and university domain management.

### Database Tables

- `users`
- `universities`
- `events`
- `listings`
- `messages`
- `rsvps`
- `cart_items`
- `mobile_payments`
