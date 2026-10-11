---
id: product:befroosh/operational/local-development-lerd
parent: product:befroosh/operational
title: Local Development with Lerd & Podman
level: product
kind: operational
domains: [multi-tenancy]
keywords: [lerd, podman, php84, sqlite, local-environment, test-runner]
code_paths: [.lerd.yaml, test.php]
test_paths: [test.php]
---

# Local Development with Lerd & Podman

## Runtime Specifications
- **PHP Version**: 8.4 (`lerd-php84-fpm` container).
- **Domain Routing**: `https://befroosh.test` routed via Nginx reverse proxy.
- **Database**: SQLite 3 located at `storage/database.sqlite` (WAL mode enabled).

## Verification Commands
```bash
# Run master test harness (153 tests)
php test.php

# Check Lerd site status
lerd site list
```
