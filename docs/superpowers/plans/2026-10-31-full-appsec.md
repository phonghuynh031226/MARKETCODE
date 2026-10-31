# Full-project AppSec implementation plan

**Goal:** Apply the user's PHP 8.2/source-and-SQL security requirements throughout the existing marketplace while preserving account and transaction history.

**Architecture:** Keep internal numeric foreign keys; migrate exposed identities to UUIDv4 with unique indexes. Centralize password, output, upload, input, and money primitives; integrate them into every existing handler and template. Run migration explicitly via CLI, never DDL from web requests.

**Constraints:** strict_types; PDO native prepares + utf8mb4; strict identifier whitelists; literal LIKE escaping; transaction + row locks for mutable balances/counters; HMAC pepper + Argon2id; sensitive token constant-time comparison; CSRF for browser mutations; signed server callbacks; HTML context escaping; JSON only; actual MIME + randomized names + image reconstruction. Environment-only sensitive configuration. No server configuration changes in this phase.

## Tasks

- [x] Password helpers and legacy bcrypt-to-Argon2id upgrade under row lock; account/session/reset integration; missing-key and legacy tests.
- [x] Exact money arithmetic and locked purchase/provider/admin financial flows; simultaneous-request integration tests.
- [x] Repeatable UUID/soft-delete/audit SQL migration and least-privilege SQL; update all exposed forms, URLs and APIs without losing ownership checks.
- [x] Central scalar validation, prepared LIKE literals, strict dynamic SQL whitelists and CSRF coverage, including logout and OAuth starts.
- [x] Escape HTML and JavaScript contexts, constrain URL schemes and stored HTML, remove unsafe demo fallbacks and DOM injection.
- [x] Reconstruct uploads with GD/Imagick, remove metadata, random 16-byte filenames; reject if encoder unavailable; enforce private download containment.
- [x] PHP strict types pass, independent review, all test suites, code/SQL audit report and release archive without secrets or user SQL dump.

## Review focus

UUID is not authorization; wrong-owner access must fail. Existing account upgrades must not use a missing/different pepper. Callback repeats and simultaneous admin/provider decisions must credit once. Encoded quotes/script delimiters must survive safely in HTML/JS contexts. Upload validation must fail closed when GD/Imagick is unavailable. Migration may stop on inconsistent data and must be rerunnable without rewriting existing identifiers.

## Validation

Helper tests; HTTP malformed-input/CSRF/UUID/IDOR/XSS tests; isolated MariaDB migration and concurrent payment/account tests. No live provider or production-server guarantees.
