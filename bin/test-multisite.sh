#!/usr/bin/env bash
#
# Run the PHPUnit suite against a multisite network inside wp-env.
#
# The network-only tests skip themselves on a single site run, so this is the
# only way they execute.
#
#   bin/test-multisite.sh            run the suite
#   bin/test-multisite.sh --filter X pass extra args straight to phpunit
set -euo pipefail

cd "$( dirname "${BASH_SOURCE[0]}" )/.."

if ! docker info >/dev/null 2>&1; then
	echo "Docker is not running. Start Docker Desktop and try again." >&2
	exit 1
fi

npx --yes @wordpress/env start

npx --yes @wordpress/env run tests-cli --env-cwd=wp-content/plugins/wp-postratings \
	composer install --no-interaction --no-progress

npx --yes @wordpress/env run tests-cli --env-cwd=wp-content/plugins/wp-postratings \
	vendor/bin/phpunit -c phpunit-multisite.xml.dist "$@"
