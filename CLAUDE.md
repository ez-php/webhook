# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` (each `-`-separated word upper-cased) unless `--namespace=`
overrides it. Existing exceptions the guess gets wrong: `bignum` → `BigNum`,
`dataloader` → `DataLoader`, `dotenv` → `Env`, `graphql` → `GraphQL`, `oauth` → `OAuth`,
`opcache` → `OPCache`, `swagger-ui` → `SwaggerUI`, `webauthn` → `WebAuthn` and
`websocket` → `WebSocket`; `websocket-client` → `WebsocketClient`, `websocket-tls` → `WebsocketTls`,
`webauthn-metadata` → `WebauthnMetadata` and `metrics-statsd` → `MetricsStatsd` are
intentional lower-case-word namespaces, and `testing-application` shares `EzPhp\Testing\`
with `testing`).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4` **and** the shared
`autoload-dev` `Tests\` directory list), `phpstan.neon`, `phpunit.xml` (test suite
**and** coverage source), and `packages.sh` (alphabetical position) — in both
generated and `--repo` mode.

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — claim the "next free" row by
  editing the table in `CODING_GUIDELINES.md` (never in a `CLAUDE.md` copy) and run
  `composer guidelines:sync` in the same change. Editing it drifts every `CLAUDE.md`
  until the sync runs, which is why the generator only reminds you instead of doing
  it. Skipping the edit leaves "next free" stale, so the next module collides.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`:

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| **next free** | **3312** | **6384** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT`, `HEALTH_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. Mailpit is the one other service with published host ports: SMTP `1025` and web UI `8025`. `ez-php/mail` maps them through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above, documented in `modules/mail/.env.example`); the root project and the `ez-php/` template each run their own Mailpit on the same defaults (`MAIL_PORT`/`MAIL_WEB_PORT`), so **these three stacks cannot run at the same time** without overriding those variables. It isn't a table column because no module beyond those three runs Mailpit — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/webhook

Outgoing webhooks — HMAC-signed, delivered as a retrying `ez-php/queue` job —
plus middleware to verify incoming webhook signatures. No subscription UI or
storage.

---

## Source Structure

```
src/
├── WebhookException.php                       — Base exception for signing/verification/delivery failures
├── WebhookSigner.php                           — Stateless HMAC-SHA256 sign()/verify(); hex-encoded, constant-time comparison
├── WebhookDispatcher.php                       — Entry point: builds a DeliverWebhookJob and pushes it onto QueueInterface
├── WebhookServiceProvider.php                  — Binds WebhookSigner, WebhookDispatcher, VerifyWebhookSignatureMiddleware from config/webhook.php
├── Job/
│   └── DeliverWebhookJob.php                   — ez-php/queue Job: signs the JSON payload and POSTs it via ez-php/http-client's Http facade; throws on non-2xx to trigger retry
└── Middleware/
    └── VerifyWebhookSignatureMiddleware.php    — MiddlewareInterface; verifies an incoming request's signature header, 401 on missing/invalid

tests/
├── TestCase.php                                 — plain PHPUnit base (identical in every package); WebhookServiceProviderTest extends EzPhp\Testing\ApplicationTestCase directly
├── WebhookSignerTest.php                        — sign/verify: correctness, wrong secret, tampered payload, garbage signature
├── WebhookDispatcherTest.php                    — Pushes onto default/configured queue, using ez-php/queue's InMemoryDriver
├── WebhookServiceProviderTest.php               — Smoke test: all three bindings resolve
├── Job/
│   └── DeliverWebhookJobTest.php                — handle(): signed POST via Http::fake(), custom header, non-2xx throws, retry config (maxTries/queue)
└── Middleware/
    └── VerifyWebhookSignatureMiddlewareTest.php — Missing header, invalid signature, valid signature, custom header name
```

---

## Key Classes and Responsibilities

### WebhookSigner (`src/WebhookSigner.php`)

Stateless. `sign(string $payload, string $secret): string` returns a lowercase
hex-encoded `hash_hmac('sha256', ...)`. `verify()` recomputes and compares with
`hash_equals()` for constant-time comparison. Callers must sign/verify against
identical bytes (the raw body string) — re-encoding a payload array can reorder
keys and break verification without the data actually changing.

### DeliverWebhookJob (`src/Job/DeliverWebhookJob.php`)

Extends `ez-php/queue`'s `Job`. Constructor takes `$url`, `$payload` (array,
JSON-encoded at delivery time), `$secret`, an optional `$signatureHeader`
(default `X-Webhook-Signature`), and an optional `$queue` name. `handle()`
signs the encoded body and POSTs it via `EzPhp\HttpClient\Http`'s static
facade — the same "static facade inside a Job" pattern `ez-php/mail`'s
`SendMailableJob` uses, since a `Job` is serialized between push and pop and
cannot carry an injected service instance across that boundary. A non-2xx
response throws `WebhookException`, which the `Worker` treats as a normal job
failure — re-queued per `$maxTries`/`$backoff` until exhausted, then recorded
via the driver's `failed()`. Defaults: `$maxTries = 5`,
`$backoff = [10, 30, 60, 300]` seconds.

### WebhookDispatcher (`src/WebhookDispatcher.php`)

The only constructor-injected entry point in the module. Takes a
`QueueInterface` and an optional queue name; `dispatch()` builds a
`DeliverWebhookJob` and pushes it. Application code depends on this class, not
on `DeliverWebhookJob` directly, so dispatch sites never construct or know
about the job's retry configuration.

### VerifyWebhookSignatureMiddleware (`src/Middleware/VerifyWebhookSignatureMiddleware.php`)

Implements `MiddlewareInterface`. Reads the configured signature header via
`RequestInterface::header()`, verifies it against `RequestInterface::rawBody()`
with an injected `WebhookSigner`, and returns `401 Unauthorized` (via
`ez-php/http`'s `Response`) on a missing or invalid signature without calling
`$next`. On success, delegates to `$next` and returns its response unchanged.

### WebhookServiceProvider (`src/WebhookServiceProvider.php`)

Reads `config/webhook.php`. Binds `WebhookSigner` (stateless, no config
needed), `WebhookDispatcher` (resolves `QueueInterface` — must already be
bound, e.g. by `ez-php/queue`'s `QueueServiceProvider`, registered first — and
`webhook.queue`, default `'default'`; `webhook.timestamped`, default `false`), and
`VerifyWebhookSignatureMiddleware` (`webhook.secret`, `webhook.signature_header`,
`webhook.tolerance`, `0`/unset = plain signatures). All bindings are lazy
closures; `register()` never calls `make()` on another service.

---

## Design Decisions and Constraints

- **`ez-php/queue` and `ez-php/http-client` are hard `require` dependencies, unlike `ez-php/mail`'s soft-dependency treatment of `ez-php/queue`.** Queued, retrying delivery over HTTP is this module's entire purpose — `WebhookDispatcher` (the module's main entry point) references `DeliverWebhookJob`, which extends `ez-php/queue`'s `Job` and calls `ez-php/http-client`'s `Http` facade. There is no meaningful "webhook module without a queue or an HTTP client" configuration to keep optional, unlike `ez-php/mail`'s `SendMailableJob`, which is one convenience wrapper among several ways to send mail.
- **Delivery goes through `EzPhp\HttpClient\Http`'s static facade, not an injected client.** `Job` instances are PHP-serialized between `push()` and `pop()` (see `ez-php/queue` design notes), so a job cannot hold an injected `HttpClient` across that boundary. This mirrors `ez-php/mail`'s `SendMailableJob`, which calls `Mail::send()` the same way.
- **Signatures are hex-encoded HMAC-SHA256 over the raw body, not a JWT-style compact token.** Third-party webhook consumers (Stripe, GitHub, etc.) universally expect "signature header + raw body", not a bundled token — matching that convention is what makes `VerifyWebhookSignatureMiddleware` usable against real subscriber implementations, and what makes this module's own signing scheme easy for external subscribers to verify. `WebhookSigner` still reuses `ez-php/auth`'s `JwtManager` primitive (`hash_hmac('sha256', ...)` + `hash_equals()`), just without JWT's header/payload/claims envelope.
- **No dependency on `ez-php/auth`.** The signing primitive is duplicated (a few lines: `hash_hmac` + `hash_equals`) rather than importing `JwtManager`, because `JwtManager` is coupled to the JWT envelope (base64url header/payload/claims, `exp`/`iat`) that webhook signatures don't use. Depending on `ez-php/auth` for two stdlib function calls would pull in an unrelated module's full surface (`Auth`, `PersonalAccessTokenManager`, etc.) for no benefit.
- **No subscription storage or management UI.** Applications own their own list of webhook subscribers (URL + secret pairs) and pass them into `WebhookDispatcher::dispatch()` per call. Adding a `Subscriber` entity/repository here would require picking a persistence layer (ORM vs. plain PDO), which this module deliberately leaves to the application.
- **`VerifyWebhookSignatureMiddleware` is `final readonly`, constructed directly or resolved from the container.** Unlike `ThrottleMiddleware`'s single container-friendly configuration, applications commonly need *per-subscriber* secrets (different third parties signing with different keys) — the container binding only covers the single-secret case (`webhook.secret`); routes needing multiple secrets construct the middleware directly with `new VerifyWebhookSignatureMiddleware(...)`.

---

## Testing Approach

- **No external infrastructure required.** All tests run in-process: `WebhookSignerTest` and `VerifyWebhookSignatureMiddlewareTest` are pure unit tests; `DeliverWebhookJobTest` uses `ez-php/http-client`'s `Http::fake()`; `WebhookDispatcherTest` and `WebhookServiceProviderTest` use `ez-php/queue`'s `InMemoryDriver` — no real queue backend or network call anywhere.
- **`Http::resetClient()` in `tearDown()`** — required in `DeliverWebhookJobTest` to prevent the fake transport installed by `Http::fake()` from leaking into other test classes that use the same static facade.
- **`WebhookServiceProviderTest` binds a throwaway `InMemoryDriver` for `QueueInterface` in `configureApplication()`** before registering `WebhookServiceProvider`, since the provider's `WebhookDispatcher` binding resolves `QueueInterface` from the container and nothing else in this module provides one.
- Test classes live in the shared `Tests\` namespace but must be uniquely named
  across the whole monorepo — the root `phpunit.xml` loads every package in one
  process, so a duplicate name is a fatal error, not a test failure. Prefix with
  `Webhook` when the obvious name is already taken.

---

## What Does NOT Belong Here

**Replay protection is opt-in and symmetric.** `WebhookSigner::signWithTimestamp()`/`verifyWithTimestamp()` sign `"{timestamp}.{body}"`; `DeliverWebhookJob` (`$timestamped`) sends `X-Webhook-Timestamp` and the middleware (`$toleranceSeconds`) enforces the window. The default stays the plain body HMAC so existing integrations keep verifying. The timestamp is taken in `handle()`, not at dispatch, so queue delay and retries never produce a stale timestamp. It bounds replay, it does not deduplicate — event-id idempotency stays with the application.

| Concern | Where it belongs |
|---|---|
| Webhook subscriber storage / management UI | Application layer (own entity + persistence choice) |
| Webhook event catalog / typed event payloads | Application layer — `payload` is an arbitrary array |
| Delivery log / dashboard / replay UI | Application layer; inspect failures via `ez queue:failed` (`ez-php/queue`) |
| JWT issuance / validation | `ez-php/auth` (`Jwt/JwtManager`) |
| Outgoing HTTP request building | `ez-php/http-client` |
| Job retry/backoff mechanics | `ez-php/queue` (`Job`, `Worker`) |