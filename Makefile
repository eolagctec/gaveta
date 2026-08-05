# Makefile - helper commands for local development
.PHONY: up down build migrate seed logs

up:
	docker-compose up -d --build

down:
	docker-compose down

build:
	docker-compose build --no-cache

migrate:
	# execute migrations.sql in the DB container
	docker-compose exec db sh -c "mysql -u root -p\"$${MYSQL_ROOT_PASSWORD}\" < /var/lib/mysql/migrations.sql" || echo "Upload migrations.sql to container or run locally"

seed:
	# import seed data (run after migrations)
	docker-compose exec db sh -c "mysql -u root -p\"$${MYSQL_ROOT_PASSWORD}\" ${MYSQL_DATABASE} < /var/www/html/seeds.sql" || echo "Seed import failed"

logs:
	docker-compose logs -f
