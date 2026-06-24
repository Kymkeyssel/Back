#!/bin/bash
# ============================================
# TRANSCAM - Schema Sync Script
# Synchronizes schema between PostgreSQL and MySQL
# ============================================

echo "============================================"
echo "TRANSCAM Schema Sync Tool"
echo "============================================"

DRIVER=${DATABASE_DRIVER:-postgresql}

# Function to generate schema for current database
generate_schema() {
    echo "Generating schema for $DRIVER..."
    php bin/console doctrine:schema:update --dump-sql --env=dev
}

# Function to create MySQL-compatible migration
create_mysql_migration() {
    echo "Creating MySQL-compatible SQL dump..."
    
    # Export PostgreSQL schema as reference
    if [ "$DRIVER" = "postgresql" ]; then
        pg_dump --schema-only --no-owner transcam_dev > /tmp/schema_postgres.sql
        echo "PostgreSQL schema exported to /tmp/schema_postgres.sql"
    fi
}

# Function to sync to MySQL
sync_to_mysql() {
    echo "Syncing schema to MySQL..."
    
    # This would need to be run when DATABASE_DRIVER=mysql
    php bin/console doctrine:schema:update --force --env=dev
    
    echo "MySQL schema synchronized"
}

# Main execution
case "$1" in
    "postgres")
        DRIVER=postgresql
        export DATABASE_DRIVER=postgresql
        generate_schema
        ;;
    "mysql")
        DRIVER=mysql
        export DATABASE_DRIVER=mysql
        generate_schema
        ;;
    "sync")
        create_mysql_migration
        ;;
    *)
        echo "Usage: $0 {postgres|mysql|sync}"
        echo "  postgres - Show PostgreSQL schema changes"
        echo "  mysql    - Show MySQL schema changes"
        echo "  sync     - Create schema export for comparison"
        ;;
esac