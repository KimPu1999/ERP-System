#!/usr/bin/env bash
set -Eeuo pipefail

: "${BACKUP_FILE:?BACKUP_FILE is required}"
: "${AGE_IDENTITY:?AGE_IDENTITY is required}"
: "${PGHOST:?PGHOST is required}"
: "${PGPORT:=5432}"
: "${PGDATABASE:?PGDATABASE is required}"
: "${PGUSER:?PGUSER is required}"

command -v pg_restore >/dev/null || { echo 'pg_restore is required' >&2; exit 1; }
command -v age >/dev/null || { echo 'age is required for encrypted restores' >&2; exit 1; }
test -r "$BACKUP_FILE"
age --decrypt --identity "$AGE_IDENTITY" "$BACKUP_FILE" | pg_restore --clean --if-exists --no-owner --no-acl --dbname="$PGDATABASE"
echo "Restore completed from $BACKUP_FILE; run migrations and smoke tests before service cutover."
