---
id: product:befroosh/cross-cutting/security-and-rate-limiting
parent: product:befroosh/cross-cutting
title: Security, Sessions & Rate Limiting
level: product
kind: cross-cutting
domains: [multi-tenancy, orders]
keywords: [security, csrf, sessions, rate-limit, brute-force, protection]
code_paths: [src/Infrastructure/Security/**, routes_auth.php]
test_paths: [test.php]
---

# Security, Sessions & Rate Limiting

1. **Persistent Sessions**: 30-day session lifetime via secure cookie (`BEFROOSH_SESS`) with `HttpOnly`, `SameSite=Lax`, and `Secure` attributes.
2. **CSRF Protection**: All state-mutating requests (`POST`, `PUT`, `DELETE`) require a cryptographically generated token verified by `CsrfAdapter`.
3. **Brute-Force Defense**: Failed login attempts are logged in `login_attempts`. 5 consecutive failures within 5 minutes locks the account for 5 minutes.
4. **API Rate Limiting**: Global in-memory/SQLite rate limiter (`RateLimiterAdapter`) restricting requests to 120 per minute per IP.
