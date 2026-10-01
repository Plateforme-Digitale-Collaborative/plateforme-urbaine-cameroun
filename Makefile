include symfony/Makefile
include docker/osm2pgsql/Makefile
include vue/Makefile

-include local.mk
-include .env
-include .env.local

ENV ?= prod
EXEC_FLAGS ?=

DOCKER_COMP = docker compose --env-file .env --env-file .env.$(ENV) --env-file .env.local

DOCKER_EXEC_VUE = $(DOCKER_COMP) exec $(EXEC_FLAGS) vue
DOCKER_EXEC_PHP = $(DOCKER_COMP) exec $(EXEC_FLAGS) frankenphp
DOCKER_EXEC_POSTGRES = $(DOCKER_COMP) exec $(EXEC_FLAGS) postgres

VUE = $(DOCKER_EXEC_VUE)
SYMFONY = $(DOCKER_EXEC_PHP) php bin/console
COMPOSER = $(DOCKER_EXEC_PHP) composer
POSTGRES = $(DOCKER_EXEC_POSTGRES)

help:
	@sed -E -n "/##/s/^(.*:[\s\t]*)?(##\s)(.*)/\1\3/p" $(MAKEFILE_LIST)

##
## --------------------------------------------------------------------------------
##  🚀 Main commands
## --------------------------------------------------------------------------------

init: build init-jwt-keypair init-hosts	import-admin-boundaries	create-osm-db	## Init the project

dev: up show-urls		## Up and show urls

build-dev: build-and-up create-osm-db show-urls 	## Build, up and show urls

deploy: build-and-up init-jwt-keypair cc		## Deploys the project
deploy-prod: build-and-up update-database init-jwt-keypair create-osm-db cc		## Deploys the project

YELLOW=\033[1;33m
GREEN=\033[1;32m
BLUE=\033[0;34m
LIGHT_BLUE=\033[1;36m
NC=\033[0m # No Color

show-urls:	## Show project services urls
	@echo ""
	@printf "${BLUE}+-------------------------------------------------+\n"
	@printf "${BLUE}| Cameroon Urban Platform                         |\n"
	@printf "${BLUE}+-------------------------------------------------+\n"
	@printf "${BLUE}| ${BLUE}%-19s ${BLUE}| ${LIGHT_BLUE}%-27s${BLUE} |\n" "🚀  Main website" 	"https://puc.local"
	@printf "${BLUE}| ${BLUE}%-19s ${BLUE}| ${LIGHT_BLUE}%-27s${BLUE} |\n" "🔒  REST API Doc" 			"https://puc.local/api/docs"
	@printf "${BLUE}| ${BLUE}%-19s ${BLUE}| ${LIGHT_BLUE}%-27s${BLUE} |\n" "🔭  Nominatim API" 			"https://puc.local/nominatim"
	@printf "${BLUE}| ${BLUE}%-19s ${BLUE}| ${LIGHT_BLUE}%-27s${BLUE} |\n" "🌍  QGIS server" 	 	"https://qgis.puc.local"
	@printf "${BLUE}| ${BLUE}%-19s ${BLUE}| ${LIGHT_BLUE}%-27s${BLUE} |\n" "📨  SMTP server" 		"https://mail.puc.local"
	@printf "${BLUE}| ${BLUE}%-19s ${BLUE}| ${LIGHT_BLUE}%-27s${BLUE} |\n" "💡  Documentation" 	"https://docs.puc.local"
	@printf "${BLUE}+-------------------------------------------------+${NC}\n"
	@echo ""

HOST_ENTRIES = \
  "127.0.0.1     puc.local"\
  "127.0.0.1     qgis.puc.local"\
  "127.0.0.1     docs.puc.local"\
  "127.0.0.1     mail.puc.local"

init-hosts:	## Adds the puc.local and local subdomain entries to the hosts file
	@printf "${YELLOW}----------------------------------------------------${NC}\n"
	@printf "${YELLOW}  We will just add entries to your hosts file       ${NC}\n"
	@printf "${YELLOW}  Note : You might be asked to enter your password  ${NC}\n"
	@printf "${YELLOW}----------------------------------------------------${NC}\n"
	@echo "Detecting OS..."
	@OS=`uname | cut -d'_' -f1` ; \
	if [ "$$OS" = "Linux" ] || [ "$$OS" = "Darwin" ]; then \
		HOSTS_FILE="/etc/hosts"; \
		for ENTRY in $(HOST_ENTRIES); do \
			if ! grep -qF "$$ENTRY" "$$HOSTS_FILE"; then \
				echo "Adding entry '$$ENTRY'"; \
				echo "$$ENTRY" | sudo tee -a "$$HOSTS_FILE"; \
			else \
				echo "Entry '$$ENTRY' already exists"; \
			fi; \
		done; \
	elif [ "$$OS" = "MINGW32" -o "$$OS" = "MINGW64" -o "$$OS" = "MSYS" ]; then \
		HOSTS_FILE="C:\\Windows\\System32\\drivers\\etc\\hosts"; \
		for ENTRY in $(HOST_ENTRIES); do \
			powershell -Command "if (!(Select-String -Path '$$HOSTS_FILE' -Pattern '$$ENTRY' -Quiet)) { Start-Process cmd -Verb runAs -ArgumentList '/c echo $$ENTRY >> $$HOSTS_FILE' } else { Write-Output 'Entry already exists for Windows : $$ENTRY' }"; \
		done; \
	else \
		echo "Unsupported OS"; \
	fi

##
## --------------------------------------------------------------------------------
##  🐋  Docker commands
## --------------------------------------------------------------------------------

build:		## Builds all containers using current env files
	$(DOCKER_COMP) build

build-no-cache:	## Builds all containers without cache
	$(DOCKER_COMP) build --no-cache

up:		## Up all containers
	$(DOCKER_COMP) up -d --remove-orphans

build-and-up: build up	## Builds and up all containers using current env files

down:		## Down all containers
	$(DOCKER_COMP) down

docker-config:	## Show docker config
	$(DOCKER_COMP) config

ifeq (restart, $(firstword $(MAKECMDGOALS)))
  RUN_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
  $(eval $(RUN_ARGS):;@:)
endif

restart:	## Restarts all containers, or a specific one if provided
	$(DOCKER_COMP) restart $(RUN_ARGS)

restart-db:	## Restarts the postgres container
	make restart postgres

rm-vue-volume:	## Deletes the vue volume, useful if facing puc_vue_node_modules/_data': failed to mount local volume
	$(DOCKER_COMP) down vue -v

reload-caddy:	## To reload the caddy container
	$(DOCKER_COMP) down frankenphp
	$(DOCKER_COMP) build frankenphp
	$(DOCKER_COMP) up -d frankenphp

down-remove-all:## To remove all containers
	$(DOCKER_COMP) down --remove-orphans --rmi all -v

create-backup:
	@TAG=$$(git describe --tags --abbrev=0); \
	if [ -z "$$TAG" ]; then echo "No tags found!"; exit 1; fi; \
	tar -cvzf backup-$$TAG.tar.gz ./docker/postgres/data ./symfony/public/media ./docker/qgis/data && \
	mkdir -p /opt/backup && \
	mv backup-$$TAG.tar.gz /opt/backup/backup-$$TAG.tar.gz

pull-backup:
	@TAG=$(tag); \
	if [ -z "$$TAG" ]; then echo "Usage: make pull-backup tag=<tag>"; exit 1; fi; \
	if [ ! -f "/opt/backup/backup-$$TAG.tar.gz" ]; then echo "Backup file not found for tag $$TAG"; exit 1; fi; \
	echo "Extracting backup..."; \
	rm -rf ./docker/postgres/data ./symfony/public/media ./docker/qgis/data; \
	tar --same-owner -xvzf /opt/backup/backup-$$TAG.tar.gz -C ./; \
	echo "Backup $$TAG restored successfully!"
