#!/bin/bash
# ============================================
# TRANSCAM - Database Migration Script
# Supports PostgreSQL and MySQL
# ============================================

DRIVER=${DATABASE_DRIVER:-postgresql}

echo "============================================"
echo "Running migrations for: $DRIVER"
echo "============================================"

# Check current database connection
if [ "$DRIVER" = "mysql" ]; then
    echo "Using MySQL database"
    php bin/console doctrine:schema:update --force --env=dev
else
    echo "Using PostgreSQL database"
    php bin/console doctrine:migrations:migrate --no-interaction --env=dev
fi

echo "============================================"
echo "Migrations completed!"
echo "============================================"