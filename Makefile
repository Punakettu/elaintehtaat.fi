.PHONY: up down build logs shell install config-export config-import reset varnish-purge

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
	$(PHP) drush uli

config-export: ## Export active config to config/sync
	$(PHP) drush config:export -y

config-import: ## Import config/sync into the active site
	$(PHP) drush config:import -y

reset:         ## Destroy containers and volumes, then start fresh
	$(COMPOSE) down -v
	$(COMPOSE) up -d --build

varnish-purge: ## Flush the entire Varnish cache
	$(COMPOSE) exec varnish varnishadm "ban req.url ~ ."
