---
id: product:befroosh/contracts/catalog-api
parent: product:befroosh/contracts
title: Catalog REST API Contract
level: product
kind: contract
parties: [storefront-frontend, catalog-service]
domains: [catalog]
flows: [guest-sync]
keywords: [api, catalog, products, search, autocomplete, json]
implements: [flow:guest-sync]
code_paths: [src/Http/Controllers/CatalogApiController.php, routes_api.php]
test_paths: [test_e2e_archetypes.php]
---

# Catalog REST API Contract

## Endpoints

| Method | Path | Request | Response |
|---|---|---|---|
| `GET` | `/api/v1/store/{slug}/products` | Query: `q`, `category_id`, `branch_id`, `page` | `{"data": [...], "meta": {...}}` |
| `GET` | `/api/v1/store/{slug}/products/{id}` | Path: `id` (product ID or slug) | `{"data": {...}}` |
| `GET` | `/api/v1/store/{slug}/branches` | None | `{"data": [{"id": 1, "name": "...", "slug": "...", "catalog_mode": "..."}]}` |

## Schemas

### Product Object
```json
{
  "id": 101,
  "title": "گوشی سامسونگ A54",
  "slug": "samsung-a54",
  "price": 18500000,
  "cost_price": 16200000,
  "stock": 14,
  "reserved_stock": 2,
  "available_stock": 12,
  "min_stock_alert": 3,
  "min_order_qty": 1,
  "max_per_order": 2,
  "max_per_month": 3,
  "tax_rate": 0.09,
  "sku": "SAM-A54-BLK",
  "barcode": "6260123456789",
  "unit": "دستگاه",
  "image_url": "/storage/products/a54.jpg"
}
```
