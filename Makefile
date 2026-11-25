.PHONY: up down restart logs art ng migrate seed fresh test build prod-key prod-up prod-down prod-logs prod-seed prod-ps prod-backup

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

# ---------------- Produção (docker-compose.prod.yml) ----------------
PROD = docker compose -f docker-compose.prod.yml

prod-key:
	@docker run --rm php:8.4-cli-alpine php -r 'echo "APP_KEY=base64:".base64_encode(random_bytes(32)).PHP_EOL;'

prod-up:
	$(PROD) up -d --build

prod-down:
	$(PROD) down

prod-logs:
	$(PROD) logs -f --tail=200

prod-seed:
	$(PROD) exec sage-backend php artisan db:seed --force

prod-ps:
	$(PROD) ps

# Cópia do banco MySQL em backups/sage-AAAAMMDD-HHMM.sql.gz
prod-backup:
	@mkdir -p backups
	$(PROD) exec -T sage-db sh -c 'exec mysqldump -u root -p"$$MYSQL_ROOT_PASSWORD" --single-transaction --routines --databases "$$MYSQL_DATABASE"' | gzip > backups/sage-$$(date +%Y%m%d-%H%M).sql.gz
	@ls -lh backups | tail -1
