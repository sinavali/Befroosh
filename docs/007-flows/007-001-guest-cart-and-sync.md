---
id: product:befroosh/flows/guest-cart-and-sync
parent: product:befroosh/flows
title: Flow 1: Guest Discovery, Local Cart & Post-Registration Sync
level: product
kind: blueprint
domains: [catalog, orders]
flows: [guest-sync]
keywords: [flow, guest, sync, localstorage, cart, registration]
references: [product:befroosh/contracts/sync-api]
code_paths: [routes_sync.php, routes_cart.php]
test_paths: [test_e2e_archetypes.php]
---

# Flow 1: Guest Discovery, Local Cart & Post-Registration Sync

## Purpose
Enables friction-free browsing and cart construction for unauthenticated visitors, syncing client-side cart and wishlist state seamlessly upon registration.

## Participants
- `catalog`: Product cards, quick bag drawer, bookmarks
- `orders`: Shopping cart partitioning and guest data synchronization endpoint

## Steps
1. Unauthenticated guest browses `/b/{slug}` and adds products to bag; items are stored in client-side `localStorage`.
2. Guest proceeds to checkout and is prompted to authenticate or register.
3. Guest completes registration at `/register?type=customer`.
4. Client JavaScript immediately fires `POST /api/sync-guest-data` with cached cart items and bookmarks.
5. Server validates stock and creates persistent records in `cart_items` and `product_bookmarks`.

## Failure Modes
- If an item in local storage went out of stock during browsing, the sync response reports items that could not be added.
