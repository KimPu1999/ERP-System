#!/usr/bin/env bash
set -Eeuo pipefail

: "${PGHOST:?PGHOST is required}"
: "${PGPORT:=5432}"
: "${PGDATABASE:?PGDATABASE is required}"
: "${PGUSER:?PGUSER is required}"
: "${AGE_RECIPIENT:?AGE_RECIPIENT is required}"
: "${BACKUP_DIR:=/var/backups/enterprise-erp}"

command -v pg_dump >/dev/null || { echo 'pg_dump is required' >&2; exit 1; }
command -v age >/dev/null || { echo 'age is required for encrypted backups' >&2; exit 1; }
mkdir -p "$BACKUP_DIR"
file="$BACKUP_DIR/${PGDATABASE}-$(date -u +%Y%m%dT%H%M%SZ).dump.age"
umask 077
pg_dump --format=custom --no-owner --no-acl | age --recipient "$AGE_RECIPIENT" --output "$file"
test -s "$file"
find "$BACKUP_DIR" -type f -name '*.dump.age' -mtime +30 -delete
echo "Encrypted backup written to $file"
