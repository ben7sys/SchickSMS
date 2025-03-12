#!/bin/bash

# SchickSMS Installation Script
# This script automates the installation process for SchickSMS
# It should be run with sudo privileges

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Log file
LOGFILE="schicksms_install.log"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOGFILE"
}

# Function to display error and exit
error_exit() {
    log_message "${RED}ERROR: $1${NC}"
    exit 1
}

# Function to display success message
success_message() {
    log_message "${GREEN}SUCCESS: $1${NC}"
}

# Function to display info message
info_message() {
    log_message "${BLUE}INFO: $1${NC}"
}

# Function to display warning message
warning_message() {
    log_message "${YELLOW}WARNING: $1${NC}"
}

# Check if script is run with sudo
if [ "$EUID" -ne 0 ]; then
    error_exit "This script must be run with sudo privileges. Please run: sudo ./install.sh"
fi

# Clear log file if it exists
> "$LOGFILE"

# Welcome message
echo "========================================================"
echo "          SchickSMS Installation Script"
echo "========================================================"
echo ""
log_message "Starting SchickSMS installation"

# Check if running on Debian/Ubuntu
if [ ! -f /etc/debian_version ]; then
    warning_message "This script is designed for Debian/Ubuntu systems."
    read -p "Continue anyway? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        error_exit "Installation aborted by user."
    fi
fi

# Step 1: System Update and Package Installation
info_message "Step 1: Updating system and installing required packages..."

# Update package lists and upgrade system
apt update && apt upgrade -y || error_exit "Failed to update system packages."
log_message "System packages updated successfully."

# Install Gammu and related packages
apt install -y gammu gammu-smsd || error_exit "Failed to install Gammu packages."
log_message "Gammu packages installed successfully."

# Install Apache, PHP, and required extensions
apt install -y apache2 php php-sqlite3 php-cli php-gd php-curl php-mbstring php-xml php-common php-bcmath || error_exit "Failed to install Apache and PHP packages."
log_message "Apache and PHP packages installed successfully."

# Step 2: User and Group Configuration
info_message "Step 2: Configuring users and groups..."

# Get the user who executed sudo
SUDO_USER=${SUDO_USER:-$(whoami)}

# Add current user to dialout and gammu groups
usermod -a -G dialout "$SUDO_USER" || warning_message "Failed to add user to dialout group."
usermod -a -G gammu "$SUDO_USER" 2>/dev/null || warning_message "Failed to add user to gammu group. This might be normal if the gammu group doesn't exist yet."

# Add www-data to gammu group
usermod -a -G gammu www-data 2>/dev/null || warning_message "Failed to add www-data to gammu group. This might be normal if the gammu group doesn't exist yet."

log_message "User and group configuration completed."

# Step 3: Gammu Configuration
info_message "Step 3: Configuring Gammu..."

# Create Gammu directories if they don't exist
mkdir -p /var/spool/gammu/{inbox,outbox,sent,error} || error_exit "Failed to create Gammu directories."

# Set ownership and permissions for Gammu directories
chown -R gammu:gammu /var/spool/gammu 2>/dev/null || warning_message "Failed to set ownership for Gammu directories. This might be normal if the gammu user doesn't exist."
chmod -R 774 /var/spool/gammu || error_exit "Failed to set permissions for Gammu directories."

# Create Gammu configuration file
cat > /etc/gammurc << EOF
[gammu]
device = /dev/ttyUSB0
connection = at115200
EOF
log_message "Created /etc/gammurc configuration file."

# Create Gammu-SMSD configuration file
INSTALL_DIR="/var/www/html/schicksms"
cat > /etc/gammu-smsdrc << EOF
[gammu]
device = /dev/ttyUSB0
connection = at115200
model = at

[smsd]
RunOnReceive = ${INSTALL_DIR}/scripts/daemon.sh
use_locking = 1
service = files
logfile = syslog
debuglevel = 255
phoneid = TeltonikaG10
inboxpath = /var/spool/gammu/inbox/
outboxpath = /var/spool/gammu/outbox/
sentsmspath = /var/spool/gammu/sent/
errorsmspath = /var/spool/gammu/error/
EOF
log_message "Created /etc/gammu-smsdrc configuration file."

# Step 4: Apache Configuration
info_message "Step 4: Configuring Apache web server..."

# Create Apache virtual host configuration
cat > /etc/apache2/sites-available/schicksms.conf << EOF
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot ${INSTALL_DIR}
    ServerName localhost

    <Directory ${INSTALL_DIR}>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/schicksms_error.log
    CustomLog \${APACHE_LOG_DIR}/schicksms_access.log combined
</VirtualHost>
EOF
log_message "Created Apache virtual host configuration."

# Enable the site and required modules
a2ensite schicksms || error_exit "Failed to enable SchickSMS site in Apache."
a2enmod rewrite || error_exit "Failed to enable rewrite module in Apache."

# Optionally disable the default site
read -p "Disable default Apache site (000-default.conf)? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    a2dissite 000-default.conf || warning_message "Failed to disable default Apache site."
    log_message "Disabled default Apache site."
fi

# Step 5: SchickSMS Configuration
info_message "Step 5: Configuring SchickSMS..."

# Check if SchickSMS is already installed
if [ ! -d "$INSTALL_DIR" ]; then
    # Create installation directory
    mkdir -p "$INSTALL_DIR" || error_exit "Failed to create installation directory."
    
    # Copy files from current directory to installation directory
    cp -r ./* "$INSTALL_DIR/" || error_exit "Failed to copy SchickSMS files to installation directory."
    log_message "Copied SchickSMS files to installation directory."
else
    warning_message "SchickSMS directory already exists. Skipping file copy."
fi

# Create scripts directory if it doesn't exist
mkdir -p "$INSTALL_DIR/scripts" || warning_message "Failed to create scripts directory."

# Create daemon.sh script if it doesn't exist
if [ ! -f "$INSTALL_DIR/scripts/daemon.sh" ]; then
    cat > "$INSTALL_DIR/scripts/daemon.sh" << 'EOF'
#!/bin/bash
# This script is executed when a new SMS is received
# It can be customized according to your needs

# Log the event
echo "$(date): SMS received" >> /var/log/gammu-received.log

# Process the SMS (example)
# You can add your custom processing logic here
EOF
    chmod +x "$INSTALL_DIR/scripts/daemon.sh" || warning_message "Failed to set executable permission for daemon.sh."
    log_message "Created daemon.sh script."
fi

# Generate password hash for admin user
read -s -p "Enter admin password for SchickSMS (or press Enter to use default 'admin'): " ADMIN_PASSWORD
echo
ADMIN_PASSWORD=${ADMIN_PASSWORD:-admin}
PASSWORD_HASH=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_BCRYPT);")

# Configure IP whitelist
IP_WHITELIST="'127.0.0.1'"
read -p "Add additional IP addresses to whitelist? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    while true; do
        read -p "Enter IP address to whitelist (or press Enter to finish): " IP_ADDR
        if [ -z "$IP_ADDR" ]; then
            break
        fi
        IP_WHITELIST="$IP_WHITELIST, '$IP_ADDR'"
    done
fi

# Create or update config.php
CONFIG_DIR="$INSTALL_DIR/config"
mkdir -p "$CONFIG_DIR" || error_exit "Failed to create config directory."

if [ -f "$CONFIG_DIR/config.php" ]; then
    # Backup existing config
    cp "$CONFIG_DIR/config.php" "$CONFIG_DIR/config.php.bak" || warning_message "Failed to backup existing config.php."
    log_message "Backed up existing config.php."
fi

cat > "$CONFIG_DIR/config.php" << EOF
<?php
return [
    'password_hash' => '$PASSWORD_HASH',
    'ip_whitelist' => [
        $IP_WHITELIST
    ],
    'db_path' => __DIR__ . '/../db/schicksms.sqlite',
    'log_path' => __DIR__ . '/../logs/app.log',
];
EOF
log_message "Created/updated config.php with custom settings."

# Step 6: SQLite Database Setup
info_message "Step 6: Setting up SQLite database..."

# Create database directory
DB_DIR="$INSTALL_DIR/db"
mkdir -p "$DB_DIR" || error_exit "Failed to create database directory."

# Check if schema.sql exists
if [ -f "$DB_DIR/schema.sql" ]; then
    # Import schema if database doesn't exist
    if [ ! -f "$DB_DIR/schicksms.sqlite" ]; then
        sqlite3 "$DB_DIR/schicksms.sqlite" < "$DB_DIR/schema.sql" || error_exit "Failed to import database schema."
        log_message "Imported database schema."
    else
        warning_message "Database already exists. Skipping schema import."
    fi
else
    error_exit "Schema file (schema.sql) not found in $DB_DIR."
fi

# Step 7: Set Permissions
info_message "Step 7: Setting permissions..."

# Create logs directory
mkdir -p "$INSTALL_DIR/logs" || warning_message "Failed to create logs directory."

# Set ownership and permissions
chown -R www-data:www-data "$INSTALL_DIR" || error_exit "Failed to set ownership for SchickSMS directory."
chmod -R 755 "$INSTALL_DIR" || error_exit "Failed to set permissions for SchickSMS directory."
chmod -R 774 "$INSTALL_DIR/logs" || warning_message "Failed to set permissions for logs directory."
chmod -R 774 "$DB_DIR" || warning_message "Failed to set permissions for database directory."

# Set specific permissions for database file
if [ -f "$DB_DIR/schicksms.sqlite" ]; then
    chown www-data:www-data "$DB_DIR/schicksms.sqlite" || warning_message "Failed to set ownership for database file."
    chmod 664 "$DB_DIR/schicksms.sqlite" || warning_message "Failed to set permissions for database file."
fi

log_message "Set permissions for SchickSMS files and directories."

# Step 8: Start Services
info_message "Step 8: Starting services..."

# Restart Apache
systemctl restart apache2 || error_exit "Failed to restart Apache."
log_message "Restarted Apache web server."

# Restart Gammu-SMSD
systemctl restart gammu-smsd || warning_message "Failed to restart Gammu-SMSD. This might be normal if the service is not yet enabled."

# Enable Gammu-SMSD service to start on boot
systemctl enable gammu-smsd || warning_message "Failed to enable Gammu-SMSD service."

log_message "Started and enabled services."

# Step 9: Installation Complete
echo ""
echo "========================================================"
echo "          SchickSMS Installation Complete!"
echo "========================================================"
echo ""
success_message "SchickSMS has been successfully installed!"
echo ""
echo "Access your SchickSMS installation at: http://localhost"
echo "or using your server's IP address/domain name."
echo ""
echo "Admin password: $ADMIN_PASSWORD"
echo ""
echo "Installation log saved to: $LOGFILE"
echo ""

# Modem detection and configuration tips
echo "========================================================"
echo "                  Next Steps"
echo "========================================================"
echo ""
echo "1. Connect your GSM modem if not already connected."
echo "2. Check modem detection with: lsusb"
echo "3. Verify modem device path (default is /dev/ttyUSB0):"
echo "   ls -l /dev/ttyUSB*"
echo ""
echo "If your modem uses a different device path, update it in:"
echo "- /etc/gammurc"
echo "- /etc/gammu-smsdrc"
echo ""
echo "4. Test Gammu configuration with: gammu identify"
echo "5. Check Gammu-SMSD status with: systemctl status gammu-smsd"
echo ""
echo "For troubleshooting, check the logs:"
echo "- Apache logs: tail -f /var/log/apache2/schicksms_error.log"
echo "- Gammu logs: journalctl -u gammu-smsd -f"
echo ""
echo "========================================================"

# Suggest a reboot
read -p "A system reboot is recommended. Reboot now? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log_message "User initiated reboot after installation."
    echo "Rebooting system..."
    reboot
else
    log_message "User skipped reboot after installation."
    echo "Remember to reboot your system later to ensure all changes take effect."
fi

exit 0
