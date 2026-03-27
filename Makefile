.PHONY: install run test swagger run-docker run-test-docker down-docker logs-docker ps-docker down-v-docker

install:
	composer install

run:
	./scripts/run.sh

test:
	php artisan test

swagger:
	composer swagger

run-docker:
	docker compose up -d --build

run-test-docker:
	docker compose run --rm app php artisan test

down-docker:
	docker compose down -v

logs-docker:
	docker compose logs -f

ps-docker:
	docker compose ps

down-v-docker:
	docker compose down -v
