#!/usr/bin/env bash
#
# Run the PHPUnit suite inside wp-env.
#
# Requires Docker to be running. First run takes a few minutes while wp-env
# pulls the WordPress, MySQL and PHPUnit images.
#
#   bin/test.sh            run the suite
#   bin/test.sh --filter X pass extra args straight to phpunit
set -euo pipefail

cd "$( dirname "${BASH_SOURCE[0]}" )/.."

if ! docker info >/dev/null 2>&1; then
	echo "Docker is not running. Start Docker Desktop and try again." >&2
	exit 1
fi

# Bring the environment up (idempotent).
npx --yes @wordpress/env start

# Dev dependencies live inside the container, so nothing lands in the repo.
npx --yes @wordpress/env run tests-cli --env-cwd=wp-content/plugins/wp-postratings \
	composer install --no-interaction --no-progress

npx --yes @wordpress/env run tests-cli --env-cwd=wp-content/plugins/wp-postratings \
	vendor/bin/phpunit "$@"
