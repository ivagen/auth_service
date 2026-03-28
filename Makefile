.PHONY: up down restart build logs shell migrate fresh passport passport-client cs-fix cs-check test bootstrap

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

passport:
	docker compose exec app php artisan passport:install

passport-client:
	docker compose exec app php artisan passport:client --personal --name="Laravel Personal Access Client" --no-interaction

cs-fix:
	docker compose exec app composer cs:fix

cs-check:
	docker compose exec app composer cs:check

test:
	docker compose exec app composer test

bootstrap:
	docker compose up -d --build
	docker compose exec app php artisan key:generate --ansi
	docker compose exec app php artisan migrate --force
