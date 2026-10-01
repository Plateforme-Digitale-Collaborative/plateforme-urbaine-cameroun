##
## --------------------------------------------------------------------------------
##  🎵  Symfony commands
## --------------------------------------------------------------------------------

sf:			## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc:			## Clear the cache
	@$(SYMFONY) c:c

init-jwt-keypair:	## Generate the JWT keypair only if it does not exist yet
	@$(SYMFONY) lexik:jwt:generate-keypair --skip-if-exists

regenerate-jwt-keypair:	## Force a new keypair (logs everyone out)
	@$(SYMFONY) lexik:jwt:generate-keypair --overwrite

##
## --------------------------------------------------------------------------------
##  🧙  Composer
## --------------------------------------------------------------------------------

composer:		## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

composer-install:	## Run composer install
	@$(COMPOSER) install

composer-update:	## Run composer update
	@$(COMPOSER) update

composer-dump-env:	## Run composer dump-env
ifdef APP_ENV
	@$(COMPOSER) dump-env ${APP_ENV}:
else
	@$(COMPOSER) dump-env dev:
endif

vendor:			## Install vendors according to the current composer.lock file
	@$(COMPOSER) install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction


##
## --------------------------------------------------------------------------------
##  🛢️  Database - Doctrine
## --------------------------------------------------------------------------------

recreate-database: restart-db drop-database create-database configure-database	## Recreate the database

drop-database:		## Drop the database
	@$(SYMFONY) do:da:dr -f --if-exists

create-database:	## Create the database
	@$(SYMFONY) do:da:cr --if-not-exists

run-migration:		## Run migrations
	@$(SYMFONY) do:mi:mi -n --allow-no-migration

run-fixtures: run-alice-fixtures run-divercity-fixtures	## Run all fixtures (Alice YAML + Doctrine/DiverCity)

run-alice-fixtures:	## Run only the Alice (YAML) fixtures - WARNING: purges the whole database
	@$(SYMFONY) ha:fi:lo -n -vvv

run-divercity-fixtures:	## Run only the Doctrine fixtures (DiverCity), without purging
	@$(SYMFONY) do:fi:lo -n --append

make-migration:		## Create a new migration
	@$(SYMFONY) make:migration

make-entity:		## Create a new entity
	@$(SYMFONY) make:entity

regenerate-entity:	## Regenerate entities
	@$(SYMFONY) make:entity --regenerate

enable-postgis:		## Enable postgis
	@$(SYMFONY) dbal:run-sql 'CREATE extension postgis;'

enable-hstore:		## Enable hstore
	@$(SYMFONY) dbal:run-sql 'CREATE extension hstore;'

make-run-migration: make-migration run-migration	## Create and run a new migration

reset-database: recreate-database run-migration		## Reset the database (recreate and run migrations)

rerun-database: reset-database run-fixtures		## Reset the database (recreate, run migrations and run fixtures)

configure-database: enable-postgis enable-hstore	## Configure the database


create-short-migration: reset-database make-migration run-migration run-fixtures ## Create a small migration

update-database: composer-install composer-dump-env cc run-migration	## Update database without clearing data

import-admin-boundaries:	## Import admin boundaries into postgis
	@$(DOCKER_EXEC_PHP) php bin/console app:import:admin-boundaries

##
## --------------------------------------------------------------------------------
##  🛠️  Tools
## --------------------------------------------------------------------------------

run-php-cs-fixer:	## Run php-cs-fixer
	@$(DOCKER_EXEC_PHP) php vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php