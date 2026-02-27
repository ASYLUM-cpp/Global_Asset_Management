#!/bin/bash
set -e

# ─── Map environment variables to ckan.ini ───────────────────────────────────
CKAN_INI="${CKAN_INI:-/srv/app/ckan.ini}"

if [ -n "$CKAN_SQLALCHEMY_URL" ]; then
    ckan config-tool "$CKAN_INI" "sqlalchemy.url = $CKAN_SQLALCHEMY_URL"
fi
if [ -n "$CKAN_SITE_URL" ]; then
    ckan config-tool "$CKAN_INI" "ckan.site_url = $CKAN_SITE_URL"
fi
if [ -n "$CKAN_SOLR_URL" ]; then
    ckan config-tool "$CKAN_INI" "solr_url = $CKAN_SOLR_URL"
fi
if [ -n "$CKAN_REDIS_URL" ]; then
    ckan config-tool "$CKAN_INI" "ckan.redis.url = $CKAN_REDIS_URL"
fi
if [ -n "$CKAN_SYSADMIN_NAME" ]; then
    ckan config-tool "$CKAN_INI" "ckan.sysadmin_name = $CKAN_SYSADMIN_NAME"
fi
if [ -n "$CKAN_STORAGE_PATH" ]; then
    ckan config-tool "$CKAN_INI" "ckan.storage_path = $CKAN_STORAGE_PATH"
fi
if [ -n "$CKAN_DATASTORE_WRITE_URL" ]; then
    ckan config-tool "$CKAN_INI" "ckan.datastore.write_url = $CKAN_DATASTORE_WRITE_URL"
fi
if [ -n "$CKAN_DATASTORE_READ_URL" ]; then
    ckan config-tool "$CKAN_INI" "ckan.datastore.read_url = $CKAN_DATASTORE_READ_URL"
fi

echo "[setup-env] Environment variables applied to $CKAN_INI"

# ─── Now run the original start script ────────────────────────────────────────
exec /srv/app/start_ckan.sh
