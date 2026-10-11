---
id: product:befroosh/contracts/sync-api
parent: product:befroosh/contracts
title: Guest Cart & Favorites Sync API Contract
level: product
kind: contract
parties: [guest-client, auth-service]
domains: [catalog, orders]
flows: [guest-sync]
keywords: [api, sync, guest, localstorage, cart, bookmarks]
implements: [flow:guest-sync]
code_paths: [routes_sync.php, routes_cart.php]
test_paths: [test_e2e_archetypes.php]
---

# Guest Cart & Favorites Sync API Contract

## Endpoints

| Method | Path | Request | Response |
|---|---|---|---|
| `POST` | `/api/sync-guest-data` | `{"cart": [...], "bookmarks": [...]}` | `{"status": "success", "synced_cart": 3, "synced_bookmarks": 2}` |

## Schemas

### Request Payload
```json
{
  "cart": [
    {"product_id": 101, "shop_id": 1, "quantity": 2}
  ],
  "bookmarks": [101, 104, 112]
}
```
