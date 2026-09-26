.PHONY: up down build logs shell install config-export config-import reset ngrok ngrok-stop

-include .env
PORT_NGROK_WEB ?= 4040

COMPOSE = docker compose
PHP     = $(COMPOSE) exec app

up:            ## Start the stack (Drupal at http://localhost:8080)
	$(COMPOSE) up -d

down:          ## Stop the stack
	$(COMPOSE) down

build:         ## Rebuild the PHP image
	$(COMPOSE) build app

logs:          ## Tail all container logs
	$(COMPOSE) logs -f

shell:         ## Shell into the PHP container
	$(PHP) sh

install:       ## Fresh Drupal install from config/sync (drops existing DB!)
	$(PHP) composer install
	$(PHP) drush site:install --existing-config --account-name=admin --account-pass=admin -y
	$(PHP) drush s3fs:refresh-cache
	$(PHP) drush cache:rebuild
	$(PHP) drush recipe:apply /var/www/html/recipes/elaintehtaat_example_content
	$(PHP) drush pathauto:aliases-generate all canonical_entities:node -y
	$(PHP) drush uli

config-export: ## Export active config to config/sync
	$(PHP) drush config:export -y

config-import: ## Import config/sync into the active site
	$(PHP) drush config:import -y

reset:         ## Destroy containers and volumes, then start fresh
	$(COMPOSE) down -v
	$(COMPOSE) up -d --build

ngrok:         ## Start an ngrok tunnel to nginx
	@missing=""; \
	if [ -z "$(NGROK_AUTHTOKEN)" ]; then missing="$$missing NGROK_AUTHTOKEN"; fi; \
	if [ -z "$(NGROK_DOMAIN)" ]; then missing="$$missing NGROK_DOMAIN"; fi; \
	if [ -n "$$missing" ]; then \
		printf "\nngrok is not configured — missing:$$missing\n"; \
		printf "Set these in .env (see .env.example):\n"; \
		printf "  NGROK_AUTHTOKEN  token from https://dashboard.ngrok.com/get-started/your-authtoken\n"; \
		printf "  NGROK_DOMAIN     a reserved domain, e.g. your-name.ngrok-free.app\n\n"; \
		exit 1; \
	fi
	$(COMPOSE) --profile ngrok up --force-recreate -d ngrok
	@echo 'Tunnel:    https://$(NGROK_DOMAIN)  ->  nginx:80'
	@echo 'Inspector: http://localhost:$(PORT_NGROK_WEB)'
	@echo 'If the app container started before NGROK_DOMAIN was set, run `docker compose up -d app` so Drupal trusts the host.'

ngrok-stop:    ## Stop the ngrok tunnel
	$(COMPOSE) stop ngrok
