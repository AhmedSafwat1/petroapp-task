.PHONY: run test install swagger

install:
	composer install

run:
	./scripts/run.sh

test:
	php artisan test

swagger:
	composer swagger
