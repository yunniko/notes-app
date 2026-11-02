#!/bin/sh
# Bring the schema up to date, then hand over to PHP-FPM. Migrations are idempotent.
set -e
php bin/migrate.php
exec "$@"
