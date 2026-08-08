#!/usr/bin/env bash
#
# Run the PHPUnit suite against a real WordPress install, single site.
#
# Docker is the only prerequisite: wp-env brings up WordPress, MySQL and the
# WordPress test library. Dev dependencies are installed INSIDE the container,
# so vendor/ never appears in the repo.
#
#   bash bin/test.sh                 # whole suite
#   bash bin/test.sh --filter Escaping
#
# For the network run use bin/test-multisite.sh. Override the stack with
# WP_ENV_PHP_VERSION / WP_ENV_CORE, exactly as CI does.

set -euo pipefail

SLUG=wp-postratings
CONFIG="${PHPUNIT_CONFIG:-phpunit.xml.dist}"
CWD=wp-content/plugins/$SLUG

cd "$(dirname "$0")/.."

# The blocks register from build/, which is generated and not in the repository.
# tests/test-blocks.php asserts they register, so on a checkout that has never
# been built those tests fail for a reason that has nothing to do with the code
# under test.
echo "==> Building blocks"
bin/build

echo "==> Starting wp-env (PHP ${WP_ENV_PHP_VERSION:-default}, core ${WP_ENV_CORE:-default})"
npx --yes @wordpress/env start

echo "==> Installing dev dependencies inside the tests container"
npx --yes @wordpress/env run tests-cli --env-cwd="$CWD" \
	composer install --no-interaction --no-progress

echo "==> Running PHPUnit ($CONFIG)"
npx --yes @wordpress/env run tests-cli --env-cwd="$CWD" \
	vendor/bin/phpunit -c "$CONFIG" "$@"
