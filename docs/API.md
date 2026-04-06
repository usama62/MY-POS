# POS API (v1)

Base URL: `/api/v1`

## Authentication

This API uses Bearer tokens via Laravel Sanctum.

- `POST /auth/register`
- `POST /auth/login`
- `GET /auth/me` (auth required)
- `POST /auth/logout` (auth required)

For protected routes, send:

`Authorization: Bearer <token>`

Roles:

- `admin`: full access
- `cashier`: sales + zakat endpoints

## Health

- `GET /health`

## Dashboard

- `GET /dashboard`

## Products

- `GET /products?search=&per_page=`
- `POST /products`
- `GET /products/{id}`
- `PUT /products/{id}`
- `DELETE /products/{id}`
- `POST /products-import` (multipart form-data, key: `file`)

Product payload:

```json
{
  "name": "Sugar 1kg",
  "sku": "SKU-1001",
  "price": 10.50,
  "stock": 100,
  "category": "Grocery",
  "is_active": true
}
```

## Customers

- `GET /customers?search=&per_page=`
- `POST /customers`
- `GET /customers/{id}`
- `PUT /customers/{id}`
- `DELETE /customers/{id}`

Customer payload:

```json
{
  "name": "Ahmad Ali",
  "phone": "123456789",
  "email": "ahmad@example.com",
  "address": "Riyadh"
}
```

## Sales

- `GET /sales?per_page=`
- `POST /sales`
- `GET /sales/{id}`

Sale create payload:

```json
{
  "customer_id": 1,
  "discount": 5,
  "tax": 2.5,
  "paid_amount": 100,
  "payment_method": "cash",
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 2, "quantity": 1 }
  ]
}
```

## Zakat

- `POST /zakat/calculate`

Payload:

```json
{
  "cash": 10000,
  "inventory_value": 5000,
  "receivables": 2000,
  "liabilities": 3000
}
```

Response includes `net_zakatable_amount` and `zakat_due`.

## Notes

- Most endpoints are protected by `auth:sanctum`.
- Role middleware is enabled (`role:admin` and `role:admin,cashier`).
