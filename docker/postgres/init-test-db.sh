#!/bin/sh
# Postgres entrypoint init script: provision the dedicated `bookly_test`
# database used by the Pest suite (phpunit.pgsql.xml). Running tests against
# `bookly_test` keeps RefreshDatabase from wiping the dev `bookly` database.
#
# Only runs on a fresh data volume (docker-entrypoint-initdb.d). To create it
# on an already-initialized volume, run once manually:
#   docker exec bookly-postgres psql -U bookly -d bookly -c "CREATE DATABASE bookly_test OWNER bookly;"
set -e

echo "Creating bookly_test database for the test suite..."

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE bookly_test OWNER ' || quote_literal('$POSTGRES_USER')
    WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'bookly_test')\gexec
EOSQL