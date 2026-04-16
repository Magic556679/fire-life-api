# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Fire Life API** — A Laravel 11 e-commerce API for a second-hand books/e-books marketplace. Supports physical products (convenience store pickup via ECPay logistics) and digital products (e-books). Multi-language: English and Traditional Chinese (zh-TW).

## Common Commands

```bash
# Start all dev services concurrently (server + queue + vite + logs)
composer dev

# Individual services
php artisan serve          # API server at http://localhost:8000
php artisan queue:listen   # Background job processor
npm run dev                # Vite dev server

# Database
php artisan migrate
php artisan tinker

# Testing
vendor/bin/phpunit                          # Run all tests
vendor/bin/phpunit --filter TestName        # Run single test
vendor/bin/phpunit tests/Feature/CartTest   # Run specific test file

# Code style (lint + fix)
vendor/bin/pint

# Production build
npm run build
```

## Architecture

### Request Flow

All API routes are under `/api` prefix (defined in `bootstrap/app.php`). The `SetLocale` middleware reads the `Accept-Language` header to set app locale (`en` or `zh-TW`).

### Route Structure (`routes/api.php`)

- **Public routes**: auth (register/login/forgot/reset), products, posts, cart, order creation, ECPay callback
- **Authenticated routes** (`auth:sanctum`): logout, `/me`, change-password, file upload, admin product management
- **Admin routes**: nested under `/admin` prefix within the auth middleware group — no separate admin role/guard, just route organization

### Service Layer

Business logic lives in `app/Services/`:
- `CartService` — cart resolution (user vs guest via `guest_token`), item management
- `EcpayService` — ECPay payment form generation and MAC value verification

### Cart & Order Flow

1. Cart is identified by `user_id` (authenticated) or `guest_token` (cookie/header for guests)
2. `POST /api/order` creates an order from cart — snapshots cart items into `orders.items_snapshot` (JSON) and `order_items` table
3. `POST /api/orders/{order_no}/checkout` generates ECPay payment form HTML
4. ECPay posts back to `POST /api/payments/ecpay/callback` — `PaymentController` verifies MAC, updates order status and `paid_at`

### Response Format

All API responses follow:
```json
{ "success": true|false, "message": "...", "data": {...}, "errors": {...} }
```

Centralized exception handling in `bootstrap/app.php` returns JSON for API routes.

### Localization

Language files in `resources/lang/{en,zh-TW}/`. Response messages should use `__('key')` for translation support.

### File Storage

Two disks configured:
- `s3` — AWS S3
- `r2` — Cloudflare R2 (primary; public CDN at `https://cdn.firelifedev.com`)

### Key Models & Relationships

- `Product` → `hasMany` `ProductImage`
- `Cart` → `hasMany` `CartItem` → `belongsTo` `Product`
- `Order` → `hasMany` `OrderItem`; also stores `items_snapshot` JSON for historical accuracy
- `User` uses Sanctum personal access tokens (stateless, `Authorization: Bearer <token>`)

## Documentation

Project docs are in `docs/`:
- `docs/backend_sdd.md` — backend software design document (modules, data models, service layer, business flow)
- `docs/api_reference.md` — full API reference with request/response examples

## Environment Notes

- Development DB: SQLite (`database/database.sqlite`) — zero setup
- Production DB: MySQL (`fire_life` database)
- ECPay uses staging credentials in `.env.example` — needs real credentials and a public callback URL (ngrok during dev) for payment flow testing
- `FRONTEND_URL` is used for CORS and password reset redirect links
