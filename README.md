# Auth Service

A Laravel-based authentication microservice. It exposes a small, versioned REST
API for user registration, login, profile retrieval, and logout, issuing
**Laravel Passport personal access tokens** (JWT bearer tokens).

## Why Passport (and not Sanctum)

This service currently uses only personal access tokens — functionally the same
as Sanctum. Passport is kept deliberately as a base for planned OAuth2 flows
(authorization code / client credentials for first- and third-party clients),
which is also why `laravel/socialite` is a dependency. If those flows are ever
dropped from the roadmap, migrating to Sanctum would remove the RSA keys, the
personal access client, and the `oauth_*` tables at no feature cost.

## API

Base URL: `http://localhost:8000/api/v1`

| Method | Endpoint    | Auth required | Description             |
|--------|-------------|---------------|-------------------------|
| POST   | `/register` | No            | Register, returns token |
| POST   | `/login`    | No            | Login, returns token    |
| GET    | `/user`     | Yes           | Current user profile    |
| POST   | `/logout`   | Yes           | Revoke current token    |

Protected endpoints require an `Authorization: Bearer <token>` header. The full
contract, including request/response schemas and error shapes, is in
[`docs/openapi.yaml`](docs/openapi.yaml).

### Success responses

Register/login return a token envelope:

```json
{
  "data": {
    "access_token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "Bearer",
    "expires_at": "2026-08-09T11:19:41.000000Z",
    "user": { "id": 1, "name": "John Doe", "email": "john@example.com", "created_at": "..." }
  }
}
```

### Error responses

Errors use Laravel's conventional shapes (stable, documented in the OpenAPI file):

- `422` validation: `{ "message": "...", "errors": { "field": ["..."] } }`
- `401` unauthenticated / invalid or revoked token: `{ "message": "Unauthenticated." }`
- `429` rate limited: `{ "message": "Too Many Attempts." }`

### Rate limits

- `login`: 5/min per email+IP, plus 30/min per IP.
- `register`: 10/min per IP.

### Token lifecycle

Personal access tokens expire after `PASSPORT_PERSONAL_ACCESS_TOKEN_TTL_DAYS`
(default 15). Logout revokes the current token; a revoked or expired token is
rejected with `401`. Each login issues a new token — multiple concurrent tokens
per user are allowed.

## Requirements

- Docker + Docker Compose
- Make

## Local setup

```bash
make bootstrap
```

From a clean clone this will, without `sudo`:

1. Create `www/.env` from `www/.env.example` and symlink it to the root `.env`
   (single source of config for both the app and Docker Compose).
2. Build and start the containers as your host user (so generated files stay
   owned by you — no permission juggling).
3. `composer install`, generate the app key, run migrations.
4. Run `php artisan passport:setup` — an **idempotent** command that ensures the
   Passport encryption keys and a personal access client both exist. This is the
   same command CI uses, so the local and CI setup paths are identical.

When it finishes the API is live at `http://localhost:8000/api/v1`.

### Try it

```bash
# Register
curl -X POST http://localhost:8000/api/v1/register \
  -H 'Accept: application/json' \
  --data-urlencode 'name=John Doe' \
  --data-urlencode 'email=john@example.com' \
  --data-urlencode 'password=password123' \
  --data-urlencode 'password_confirmation=password123'

# Login
curl -X POST http://localhost:8000/api/v1/login \
  -H 'Accept: application/json' \
  --data-urlencode 'email=john@example.com' \
  --data-urlencode 'password=password123'

# Profile (use the access_token from above)
curl http://localhost:8000/api/v1/user \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <token>'

# Logout
curl -X POST http://localhost:8000/api/v1/logout \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <token>'
```

An end-to-end version of this flow lives in [`scripts/smoke.sh`](scripts/smoke.sh).

## Make targets

| Command               | Description                                  |
|-----------------------|----------------------------------------------|
| `make up`             | Start containers                             |
| `make down`           | Stop containers                              |
| `make build`          | Rebuild and start containers                 |
| `make logs`           | Follow container logs                        |
| `make shell`          | Shell into the app container                 |
| `make migrate`        | Run migrations                               |
| `make fresh`          | Reset database and seed                      |
| `make passport-setup` | Ensure Passport keys + client (idempotent)   |
| `make test`           | Run the test suite                           |
| `make cs-fix`         | Fix code style (php-cs-fixer)                |
| `make cs-check`       | Check code style                             |

## Quality tooling

Run inside the container (or locally against `www/`):

```bash
composer test      # PHPUnit
composer cs:check  # php-cs-fixer (dry run)
composer stan      # PHPStan / Larastan (level 5)
composer audit     # dependency vulnerability audit
```

CI (`.github/workflows/tests.yml`) runs all of the above plus a **MySQL HTTP
smoke test** that boots the app the same way `make bootstrap` does and exercises
register → login → profile → logout, and a production Docker image build.

## Production

Use the production compose overlay, which builds a self-contained image (code +
dev-free autoloader baked in, runs as `www-data`, no bind mounts):

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Copy [`www/.env.production.example`](www/.env.production.example) to `.env` and
set real secrets, including `APP_KEY`, both Passport keys, and the database
passwords. The production overlay fails before startup when a required value is
missing. Ensure `APP_DEBUG=false` and `APP_ENV=production`.

The production app and nginx images are self-contained. The overlay explicitly
resets development bind mounts and does not read `www/.env`.

For a first deployment, prepare the database before serving traffic:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml run --rm app php artisan migrate --force
docker compose -f docker-compose.yml -f docker-compose.prod.yml run --rm app php artisan passport:setup
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

## Troubleshooting

- **`Personal access client not found` on register/login** — the personal access
  client is missing. Run `make passport-setup` (or `php artisan passport:setup`).
- **`RuntimeException: Key path ... does not exist`** — Passport keys are missing.
  `php artisan passport:setup` regenerates them if absent.
- **Reset everything** — `make down && docker volume rm auth_service_mysql_data`
  then `make bootstrap` for a clean database.
