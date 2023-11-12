# vim: set ft=make :
set dotenv-load := true

export ENV_FILE := if path_exists('.env.local') == 'true' { '.env.local' } else { '.env' }
API-COMPOSE := 'docker compose -p ' + env_var('NAME') + ' -f config/docker/app.yml --env-file ' + ENV_FILE
API-COMPOSE-RUN := API-COMPOSE + ' run --rm'
API-COMPOSE-RUN-PHP := API-COMPOSE-RUN + ' --no-deps php'

# Print help message.
@help:
    printf "\nWelcome to another great Symfony app!\n\n"
    just -l --list-heading ''
    printf '\nFor more information, see README.md (if available).\n\n'

# Install the app.
@install: (compose 'build --pull') && (composer '--no-interaction install')

# Start the app (use `install` before).
@start *args: stop (compose 'up -d')
    {{API-COMPOSE-RUN-PHP}} bin/docker/wait-for-it.sh database:3306 -t 30
    just status

# Stop the app.
@stop: (compose 'down -v')

# Restart the app.
@restart: stop (compose 'up -d --force-recreate --build') status

# Show status of containers and entrypoint URL.
@status: (compose 'ps')
    printf '\nOpen %s to use this app.\n' $(just _base-url)

# Return base URL for this service.
@_base-url:
    echo "http://localhost:${API_PORT}"

# Run docker-compose commands.
@compose *args:
    {{API-COMPOSE}} {{args}}

# Enter the app.
@enter:
    {{API-COMPOSE}} exec php bash

# Run composer commands in app container.
@composer *args:
    {{API-COMPOSE-RUN}} -e COMPOSER_HOME=./.composer --no-deps php composer {{args}}
