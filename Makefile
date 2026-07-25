.PHONY: up down restart build logs shell migrate fresh passport-setup cs-fix cs-check test bootstrap env

# Run the app container as the current host user so files created inside the
# container (vendor, storage, caches) stay owned by you — no sudo/chown needed.
export UID := $(shell id -u)
export GID := $(shell id -g)

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose up -d --build

logs:
	docker compose logs -f

shell:
	docker compose exec app bash

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh --seed

# Idempotent: ensures Passport keys + a personal access client exist.
passport-setup:
	docker compose exec app php artisan passport:setup

cs-fix:
	docker compose exec app composer cs:fix

cs-check:
	docker compose exec app composer cs:check

test:
	docker compose exec app composer test

env:
	@if [ ! -f www/.env ]; then cp www/.env.example www/.env; echo ".env created from .env.example"; else echo ".env already exists"; fi
	@ln -sf www/.env .env

bootstrap: env
	docker compose up -d --build
	docker compose exec app composer install
	docker compose exec app php artisan key:generate --ansi
	docker compose exec app php artisan migrate --force
	docker compose exec app php artisan passport:setup
	@echo ""
	@echo "Bootstrap complete. API available at http://localhost:8000/api/v1"
