#!/bin/bash
# Script to set up permissions for gammu-smsd-inject to be used without sudo

# Ensure script is run as root
if [ "$EUID" -ne 0 ]; then
  echo "Please run as root (use sudo)"
  exit 1
fi

echo "Setting up permissions for gammu-smsd-inject..."

# Add www-data user to dialout group for modem access
if groups www-data | grep -q dialout; then
  echo "www-data user is already in the dialout group."
else
  echo "Adding www-data user to dialout group..."
  usermod -a -G dialout www-data
  echo "www-data user added to dialout group."
fi

# Find the path to gammu-smsd-inject
GAMMU_PATH=$(which gammu-smsd-inject)

if [ -z "$GAMMU_PATH" ]; then
  echo "Error: gammu-smsd-inject not found. Please make sure Gammu is installed."
  exit 1
fi

echo "Found gammu-smsd-inject at: $GAMMU_PATH"

# Set the setuid bit on gammu-smsd-inject
echo "Setting setuid bit on gammu-smsd-inject..."
chmod +s "$GAMMU_PATH"

# Verify the permissions
echo "Verifying permissions..."
ls -la "$GAMMU_PATH"

echo "Checking if www-data can access the modem..."
# Check if /dev/ttyUSB0 exists (common path for USB modems)
if [ -e /dev/ttyUSB0 ]; then
  # Check if the device is readable by www-data
  if sudo -u www-data test -r /dev/ttyUSB0; then
    echo "www-data can read the modem device."
  else
    echo "Warning: www-data cannot read the modem device."
    echo "You may need to adjust permissions on the modem device or add www-data to additional groups."
    echo "Current permissions for modem device:"
    ls -la /dev/ttyUSB0
  fi
else
  echo "Note: /dev/ttyUSB0 not found. If your modem uses a different device path,"
  echo "make sure www-data has read/write access to it."
fi

echo "Setup complete. The www-data user should now be able to execute gammu-smsd-inject without sudo."
echo "You may need to restart Apache for the group changes to take effect:"
echo "sudo systemctl restart apache2"
