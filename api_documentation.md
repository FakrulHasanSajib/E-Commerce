# LaraShop E-Commerce Backend — Complete API Documentation (Modules 1 – 20)

Welcome to the official API reference for **LaraShop E-Commerce Platform**. This documentation covers all RESTful API endpoints across all 20 modules, detailing request headers, body parameters, rate limits, authentication rules, and sample JSON responses.

---

## 1. Global Specifications & Headers

### Base URL
- **Local / Dev**: `http://localhost:8000/api`
- **Production**: `https://api.larashop.com/api`

### Common Request Headers
| Header Name | Type | Description | Required |
| :--- | :--- | :--- | :--- |
| `Accept` | `string` | Must be `application/json` | Yes |
| `Content-Type` | `string` | Must be `application/json` | Yes (for POST/PUT/PATCH) |
| `Authorization` | `string` | `Bearer {sanctum_token}` | Yes (for authenticated endpoints) |
| `X-2FA-Code` | `string` | 6-digit TOTP code or recovery code | Required if 2FA is active on Admin routes |
| `X-Device-Fingerprint` | `string` | Unique client browser/device hash | Recommended for fraud tracking |

### Standard Error Responses
- **401 Unauthorized**: Missing or invalid Sanctum token.
- **403 Forbidden**: Insufficient Spatie RBAC permissions or missing 2FA challenge.
- **422 Unprocessable Entity**: Validation failure (returns `errors` object).
- **429 Too Many Requests**: Rate limit exceeded (Throttle headers present).
- **503 Service Unavailable**: Core infrastructure health check failure.

---

## 2. Authentication & User Account (Modules 2, 3, 12, 19)

### 2.1 Login
- **Endpoint**: `POST /api/auth/login`
- **Rate Limit**: 5 attempts/minute
- **Request Body**:
  ```json
  {
    "email": "customer@larashop.test",
    "password": "password123",
    "device_fingerprint": "hash_abc123"
  }
  ```
- **Standard Customer Response (`200 OK`)**:
  ```json
  {
    "message": "Login successful",
    "token": "1|sanctum_token_string_here",
    "user": {
      "id": 1,
      "name": "Customer User",
      "email": "customer@larashop.test",
      "roles": ["customer"]
    }
  }
  ```
- **Admin Multi-Step 2FA Challenge Response (`403 Forbidden` / `202 Accepted`)**:
  ```json
  {
    "message": "Two-factor authentication code required.",
    "requires_2fa": true,
    "user_id": 1
  }
  ```
  *Admin Step 2: Send TOTP code to `POST /api/admin/2fa/verify` or include header `X-2FA-Code: 123456` on subsequent requests.*

### 2.2 Register
- **Endpoint**: `POST /api/auth/register`
- **Request Body**:
  ```json
  {
    "name": "New User",
    "email": "newuser@larashop.test",
    "password": "password123",
    "password_confirmation": "password123",
    "referral_code": "REF-ABC12345"
  }
  ```
- **Response (`201 Created`)**:
  ```json
  {
    "message": "Registration successful",
    "token": "2|sanctum_token_string_here",
    "user": { "id": 2, "name": "New User", "email": "newuser@larashop.test" }
  }
  ```

### 2.3 Admin 2FA Setup
- **Endpoint**: `POST /api/admin/2fa/enable` (Auth: Sanctum Admin)
- **Response (`200 OK`)**:
  ```json
  {
    "message": "2FA initialized. Please scan QR code and confirm 6-digit code.",
    "data": {
      "secret": "JBSWY3DPEHPK3PXP",
      "qr_uri": "otpauth://totp/LaraShop:admin@larashop.test?secret=JBSWY3DPEHPK3PXP&issuer=LaraShop",
      "recovery_codes": ["abc12-def34", "ghi56-jkl78", "..."]
    }
  }
  ```

### 2.4 Admin 2FA Confirm
- **Endpoint**: `POST /api/admin/2fa/confirm` (Auth: Sanctum Admin)
- **Request Body**: `{ "code": "123456" }`
- **Response (`200 OK`)**: `{ "message": "Two-factor authentication confirmed and activated successfully." }`

---

## 3. Product Catalog & Search (Modules 4, 5, 14, 16)

### 3.1 List Products
- **Endpoint**: `GET /api/products`
- **Query Parameters**:
  - `category`: Filter by category slug.
  - `brand`: Filter by brand slug.
  - `price_min`, `price_max`: Price range boundaries.
  - `sort`: `price_asc`, `price_desc`, `rating_desc`, `latest`.
  - `page`, `per_page`: Pagination (default: 15).
- **Response (`200 OK`)**:
  ```json
  {
    "data": [
      {
        "id": 10,
        "name": "Wireless Noise-Canceling Headphones",
        "slug": "wireless-noise-canceling-headphones",
        "sku": "SKU-HEADPHONE-01",
        "price": "4500.00",
        "sale_price": "3999.00",
        "stock_quantity": 45,
        "average_rating": "4.80",
        "reviews_count": 24,
        "category": { "name": "Electronics", "slug": "electronics" },
        "brand": { "name": "AudioTech", "slug": "audiotech" }
      }
    ],
    "meta": { "current_page": 1, "last_page": 4, "total": 60 }
  }
  ```

### 3.2 Product Reviews & Ratings
- **Endpoint**: `GET /api/products/{productId}/reviews`
- **Response (`200 OK`)**:
  ```json
  {
    "data": [
      {
        "id": 5,
        "rating": 5,
        "title": "Excellent Sound Quality!",
        "comment": "Deep bass and clear vocals. Very satisfied.",
        "is_purchased": true,
        "user_name": "Rahim Ahmed",
        "media_urls": ["https://cdn.larashop.com/reviews/img1.jpg"]
      }
    ]
  }
  ```

---

## 4. Shopping Cart & Checkout (Modules 7, 8, 9, 10, 11)

### 4.1 Get Cart
- **Endpoint**: `GET /api/cart`
- **Response (`200 OK`)**:
  ```json
  {
    "items": [
      {
        "id": 1,
        "product_id": 10,
        "product_name": "Wireless Noise-Canceling Headphones",
        "price": "3999.00",
        "quantity": 2,
        "total": "7998.00"
      }
    ],
    "subtotal": "7998.00",
    "discount": "0.00",
    "grand_total": "7998.00"
  }
  ```

### 4.2 Checkout Calculation
- **Endpoint**: `POST /api/checkout/calculate`
- **Request Body**:
  ```json
  {
    "shipping_method_id": 1,
    "coupon_code": "SUMMER10",
    "gift_card_code": "GC-9821-4512",
    "use_wallet_balance": true
  }
  ```
- **Response (`200 OK`)**:
  ```json
  {
    "subtotal": "7998.00",
    "discount": "799.80",
    "shipping_cost": "60.00",
    "tax_cost": "0.00",
    "gift_card_deducted": "1000.00",
    "wallet_debited": "500.00",
    "grand_total": "5758.20"
  }
  ```

### 4.3 Place Order
- **Endpoint**: `POST /api/checkout`
- **Request Body**:
  ```json
  {
    "customer_name": "Karim Hasan",
    "customer_email": "karim@test.com",
    "customer_phone": "01700000000",
    "shipping_address": "House 12, Road 5, Dhanmondi, Dhaka",
    "shipping_method_id": 1,
    "payment_method": "bkash",
    "coupon_code": "SUMMER10",
    "gift_card_code": "GC-9821-4512"
  }
  ```
- **Response (`201 Created`)**:
  ```json
  {
    "message": "Order placed successfully",
    "order_number": "ORD-20260809-9821",
    "status": "pending",
    "payment_status": "unpaid",
    "grand_total": "6758.20",
    "payment_url": "https://gateway.bkash.com/pay/exec?token=bk_9821"
  }
  ```

---

## 5. Marketing, Gift Cards & Affiliates (Module 17)

### 5.1 Apply Gift Card
- **Endpoint**: `POST /api/gift-cards/apply`
- **Rate Limit**: 10 attempts/minute
- **Request Body**: `{ "code": "GIFT-9821-4512" }`
- **Response (`200 OK`)**:
  ```json
  {
    "message": "Gift card applied successfully",
    "balance": "1000.00",
    "usable_amount": "1000.00"
  }
  ```

### 5.2 Product Feeds (Public)
- **Google Shopping XML**: `GET /feeds/google-shopping.xml`
- **Facebook Catalog CSV**: `GET /feeds/facebook-catalog.csv`

---

## 6. Infrastructure & System Operations (Modules 13, 18, 20)

### 6.1 System Health Check Endpoint
- **Endpoint**: `GET /health` or `GET /api/health`
- **Response (`200 OK` / `503 Service Unavailable`)**:
  ```json
  {
    "status": "healthy",
    "timestamp": "2026-08-09T10:59:48+06:00",
    "services": {
      "database": "ok",
      "disk": "ok",
      "redis": "ok",
      "queue": "ok"
    },
    "is_healthy": true
  }
  ```

### 6.2 Admin Dashboard Metrics
- **Endpoint**: `GET /api/admin/dashboard/metrics` (Auth: Sanctum Admin)
- **Response (`200 OK`)**:
  ```json
  {
    "total_revenue": "1254000.00",
    "total_orders": 342,
    "average_order_value": "3666.67",
    "total_customers": 890,
    "revenue_growth_percent": 14.5,
    "orders_growth_percent": 8.2
  }
  ```

---

## 7. Automated Backups & CLI Maintenance Commands

| Command | Purpose | Schedule / Usage |
| :--- | :--- | :--- |
| `php artisan db:backup` | Creates AES-256 encrypted DB dump, uploads to S3, purges archives > 7 days | Daily via Cron (`0 2 * * *`) |
| `php artisan db:restore-test {file}` | Decrypts and verifies database backup restore integrity | Monthly / On-Demand |
| `php artisan 404:purge-old-logs` | Deletes 404 log entries older than 30 days | Weekly via Cron |
| `php artisan landing-pages:publish-scheduled` | Transitions scheduled landing pages to published status | Minutely via Cron |

---

*Document generated for LaraShop E-Commerce Backend (Modules 1 - 20).*
