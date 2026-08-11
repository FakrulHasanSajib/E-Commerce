# 🛒 LaraShop — Full-Stack Enterprise E-Commerce Platform

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Nuxt](https://img.shields.io/badge/Nuxt-3.x-00DC82?style=for-the-badge&logo=nuxt&logoColor=white)](https://nuxt.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white)](https://vuejs.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.x-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/License-MIT-blue.style=for-the-badge)](#license)

**LaraShop** is a modern, high-performance enterprise e-commerce ecosystem built with **Laravel**, **Nuxt 3**, **Vue 3**, and **Tailwind CSS**. It delivers a seamless shopping experience for customers and a feature-rich admin panel for store owners, featuring zone-based shipping calculations, multi-courier API integrations (Pathao, Steadfast, Paperfly, RedX), and tokenized payment gateway drivers.

---

## 🌟 Key Architecture Highlights

- **Storefront (Customer App)**: Built with **Nuxt 3** & **Vue 3** for instant SSR/SPA rendering, dynamic theme colors, header topbar, mega menus, product search, cart, and guest/authenticated checkout.
- **Admin Control Panel**: Modern Single Page Application (SPA) providing real-time sales metrics, order fulfillment, flash sale management, coupon engines, RBAC permissions, and system health checks.
- **Backend API Engine**: Powered by **Laravel 12** with Sanctum token authentication, multi-layer failover caching (Redis + Database), 2FA TOTP security, and transactional database integrity.

---

## 🚀 Core Features

### 🛍️ Storefront Capabilities
- **Dynamic Header & Topbar**: Live phone, email, and 100% genuine guarantee banner synced from admin settings.
- **Vector Brand Footer**: Crisp SVG icons for Facebook, Instagram, Twitter/X, YouTube, and WhatsApp.
- **Dynamic Product Catalog**: Multi-attribute filtering, category trees, product reviews, and inventory tracking.
- **Flash Sale Engine**: Real-time countdown timers with per-item quantity lock and discount enforcement.
- **Flexible Checkout**: Guest and customer checkout with automated district/upazila zone shipping charge calculations.
- **Order Tracking**: Real-time order status tracking with courier tracking codes.

### 💼 Admin Panel Capabilities
- **Order Management Queue**: Filter, view, update status (Pending $\rightarrow$ Confirmed $\rightarrow$ Processing $\rightarrow$ Shipped $\rightarrow$ Delivered $\rightarrow$ Cancelled), and process partial/full refunds.
- **Direct Courier Logistics Integration**: One-click parcel dispatch via **Pathao**, **Steadfast**, **Paperfly**, and **RedX** merchant APIs with automated tracking code generation.
- **Shipping Zone Engine**: Configure country, division, district, and upazila level flat rate or weight-based shipping rules.
- **Flash Sale & Coupon Management**: Complete CRUD operations for flash sale campaigns and coupon codes.
- **RBAC Security & System Health**: Custom role definitions, permission matrix, active Redis/queue monitoring, and disk health metrics.

### 💳 Payment & Security Integration
- **Local & International Gateways**: Modular driver architecture supporting **bKash Tokenized API**, **Nagad DFS**, **SSLCommerz Hosted Gateway**, and **Cash on Delivery (COD)**.
- **Two-Factor Authentication (2FA)**: TOTP QR code & recovery code support for administrative accounts.
- **Data Protection**: Strict address uniqueness transactions and global Axios error toast interceptors.

---

## 🛠️ Technology Stack

| Component | Technology | Description |
|---|---|---|
| **Backend Framework** | Laravel 12 / PHP 8.5 | RESTful API, Eloquent, Queue Jobs, Artisan CLI |
| **Storefront Frontend** | Nuxt 3 / Vue 3 / Pinia | SSR/SPA Framework with reactive state stores |
| **Admin Panel** | Vue 3 / Vite / Pinia / Axios | Modern SPA Admin Dashboard |
| **Styling** | Vanilla CSS / Tailwind CSS | Responsive UI design with custom color palettes |
| **Database** | MySQL / PostgreSQL | Relational schema with index optimization |
| **Cache & Queue** | Redis (with Database Failover) | High-speed cache and queue worker dispatch |

---

## ⚙️ Quick Start Installation Guide

### Prerequisites
- **PHP** $\ge$ 8.2 (PHP 8.5 recommended)
- **Node.js** $\ge$ 18.x & **npm** $\ge$ 9.x
- **Composer** $\ge$ 2.x
- **MySQL / MariaDB**

---

### 1. Clone the Repository
```bash
git clone https://github.com/FakrulHasanSajib/E-Commerce.git
cd E-Commerce
```

---

### 2. Backend Setup (Laravel)
```bash
# Install PHP dependencies
composer install

# Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# Configure database credentials in .env, then run migrations and seeders
php artisan migrate --seed

# Start Laravel development server
php artisan serve
```

---

### 3. Admin Panel Setup (Vue 3 / Vite)
```bash
cd admin

# Install dependencies
npm install

# Run Vite development server (Default: http://localhost:3001)
npm run dev
```

---

### 4. Storefront Setup (Nuxt 3)
```bash
cd storefront

# Install dependencies
npm install

# Run Nuxt development server (Default: http://localhost:3000)
npm run dev
```

---

## 🔐 Environment Configuration (`.env`)

Key environment variables to configure in `.env`:

```ini
APP_NAME=LaraShop
APP_ENV=local
APP_URL=http://localhost:8000

# Database Credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce
DB_USERNAME=root
DB_PASSWORD=

# Cache & Queue Driver Configuration
CACHE_STORE=failover
QUEUE_CONNECTION=database
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Third-Party Payment Credentials (Optional for Production)
BKASH_APP_KEY=your_app_key
BKASH_APP_SECRET=your_app_secret
BKASH_USERNAME=your_username
BKASH_PASSWORD=your_password

SSLCOMMERZ_STORE_ID=your_store_id
SSLCOMMERZ_STORE_PASSWORD=your_store_password
```

---

## 🧪 Automated Testing

Run the feature test suite powered by **Pest / PHPUnit**:

```bash
# Run all tests
php artisan test

# Run tests in compact mode
php artisan test --compact
```

---

## 👨‍💻 Author & Maintainer

Developed and maintained by **Fakrul Hasan Sajib**.
- **GitHub**: [@FakrulHasanSajib](https://github.com/FakrulHasanSajib)
- **Repository**: [https://github.com/FakrulHasanSajib/E-Commerce](https://github.com/FakrulHasanSajib/E-Commerce)

---

## 📄 License

This project is open-source software licensed under the [MIT License](LICENSE).
