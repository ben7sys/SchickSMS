#!/bin/bash
# Script to fix permissions for SchickSMS

# Ensure script is run as root
if [ "$EUID" -ne 0 ]; then
  echo "Please run as root (use sudo)"
  exit 1
fi

# Set variables
SCHICKSMS_DIR="/var/www/html/schicksms"
WEB_USER="www-data"
WEB_GROUP="www-data"

echo "Fixing permissions for SchickSMS..."

# Check if the directory exists
if [ ! -d "$SCHICKSMS_DIR" ]; then
  echo "Error: SchickSMS directory not found at $SCHICKSMS_DIR"
  exit 1
fi

# Create database directory if it doesn't exist
if [ ! -d "$SCHICKSMS_DIR/db" ]; then
  echo "Creating database directory..."
  mkdir -p "$SCHICKSMS_DIR/db"
fi

# Create logs directory if it doesn't exist
if [ ! -d "$SCHICKSMS_DIR/app/logs" ]; then
  echo "Creating logs directory..."
  mkdir -p "$SCHICKSMS_DIR/app/logs"
fi

# Create exports directory if it doesn't exist
if [ ! -d "$SCHICKSMS_DIR/app/exports" ]; then
  echo "Creating exports directory..."
  mkdir -p "$SCHICKSMS_DIR/app/exports"
fi

# Create backups directory if it doesn't exist
if [ ! -d "$SCHICKSMS_DIR/app/backups" ]; then
  echo "Creating backups directory..."
  mkdir -p "$SCHICKSMS_DIR/app/backups"
fi

echo "Setting ownership and permissions..."

# Set ownership for the entire SchickSMS directory
chown -R $WEB_USER:$WEB_GROUP "$SCHICKSMS_DIR"

# Set directory permissions
find "$SCHICKSMS_DIR" -type d -exec chmod 755 {} \;

# Set file permissions
find "$SCHICKSMS_DIR" -type f -exec chmod 644 {} \;

# Make sure the database directory and file are writable
chmod 775 "$SCHICKSMS_DIR/db"
if [ -f "$SCHICKSMS_DIR/db/schicksms.sqlite" ]; then
  chmod 664 "$SCHICKSMS_DIR/db/schicksms.sqlite"
else
  echo "Warning: Database file not found. It will be created when the application runs."
fi

# Make sure the logs, exports, and backups directories are writable
chmod 775 "$SCHICKSMS_DIR/app/logs"
chmod 775 "$SCHICKSMS_DIR/app/exports"
chmod 775 "$SCHICKSMS_DIR/app/backups"

echo "Checking database file..."
if [ -f "$SCHICKSMS_DIR/db/schicksms.sqlite" ]; then
  # Check if the database file is readable and writable by the web server
  if sudo -u $WEB_USER test -r "$SCHICKSMS_DIR/db/schicksms.sqlite" && sudo -u $WEB_USER test -w "$SCHICKSMS_DIR/db/schicksms.sqlite"; then
    echo "Database file permissions are correct."
  else
    echo "Error: Database file permissions are incorrect."
    echo "Current permissions: $(ls -l "$SCHICKSMS_DIR/db/schicksms.sqlite")"
    echo "Fixing database file permissions..."
    chown $WEB_USER:$WEB_GROUP "$SCHICKSMS_DIR/db/schicksms.sqlite"
    chmod 664 "$SCHICKSMS_DIR/db/schicksms.sqlite"
  fi
else
  echo "Database file does not exist. Creating an empty database file..."
  touch "$SCHICKSMS_DIR/db/schicksms.sqlite"
  chown $WEB_USER:$WEB_GROUP "$SCHICKSMS_DIR/db/schicksms.sqlite"
  chmod 664 "$SCHICKSMS_DIR/db/schicksms.sqlite"
  
  # Import schema if it exists
  if [ -f "$SCHICKSMS_DIR/db/schema.sql" ]; then
    echo "Importing database schema..."
    sqlite3 "$SCHICKSMS_DIR/db/schicksms.sqlite" < "$SCHICKSMS_DIR/db/schema.sql"
    if [ $? -eq 0 ]; then
      echo "Schema imported successfully."
    else
      echo "Error importing schema."
    fi
  else
    echo "Warning: Schema file not found at $SCHICKSMS_DIR/db/schema.sql"
  fi
fi

echo "Permissions fixed successfully."
echo "If you're still experiencing issues, please check the web server logs."
