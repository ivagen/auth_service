.PHONY: up down restart build logs shell migrate fresh passport passport-client cs-fix cs-check test bootstrap env

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

env:
	@if [ ! -f .env ]; then cp .env.example .env; echo ".env created from .env.example"; else echo ".env already exists"; fi

bootstrap:
	@if [ ! -f .env ]; then cp .env.example .env; echo ".env created from .env.example"; fi
	docker compose up -d --build
	@if [ ! -d vendor ]; then mkdir vendor; echo "vendor folder created"; fi
	sudo chmod -R 775 vendor
	docker compose exec app composer install
	docker compose exec app php artisan key:generate --ansi
	docker compose exec app php artisan passport:keys --no-interaction 2>/dev/null || true
	docker compose exec app php artisan migrate --force
	sudo chmod -R 775 storage bootstrap/cache
	sudo chown -R www-data:www-data storage bootstrap/cache
	sudo chmod 660 /home/yevhenii/www/auth_service/storage/oauth-public.key
	sudo chmod 660 /home/yevhenii/www/auth_service/storage/oauth-private.key
