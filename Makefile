.PHONY: up down restart logs art ng migrate seed fresh test build

up:
	@docker info >/dev/null 2>&1 || { echo "Docker não está rodando. Abra o Docker Desktop e tente de novo."; exit 1; }
	docker compose up -d

down:
	docker compose down

restart: down up

logs:
	docker compose logs -f

build:
	docker compose build

art:
	docker compose exec sage-backend php artisan $(filter-out $@,$(MAKECMDGOALS))

ng:
	docker compose exec sage-frontend npx ng $(filter-out $@,$(MAKECMDGOALS))

migrate:
	docker compose exec sage-backend php artisan migrate

seed:
	docker compose exec sage-backend php artisan db:seed

fresh:
	docker compose exec sage-backend php artisan migrate:fresh --seed

test:
	docker compose exec sage-backend php artisan test

%:
	@:
