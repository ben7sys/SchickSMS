<?php
/**
 * SchickSMS Database Debug Script
 * 
 * This script helps diagnose database issues in SchickSMS.
 * Place this file in the root directory of SchickSMS and run it from the browser.
 */

// Set error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>SchickSMS Database Debug</h1>";

// Check if the script is in the correct directory
if (!file_exists('schicksms/config/config.php')) {
    die("<p style='color: red;'>Error: This script must be placed in the same directory as the 'schicksms' folder.</p>");
}

// Load the configuration
echo "<h2>Loading Configuration</h2>";
try {
    $config = require 'schicksms/config/config.php';
    echo "<p style='color: green;'>Configuration loaded successfully.</p>";
    
    // Display database path
    $dbPath = $config['database']['path'];
    echo "<p>Database path: " . htmlspecialchars($dbPath) . "</p>";
    
    // Resolve the path
    $resolvedPath = realpath(dirname($dbPath)) . '/' . basename($dbPath);
    echo "<p>Resolved path: " . htmlspecialchars($resolvedPath) . "</p>";
} catch (Exception $e) {
    die("<p style='color: red;'>Error loading configuration: " . htmlspecialchars($e->getMessage()) . "</p>");
}

// Check database directory
echo "<h2>Checking Database Directory</h2>";
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    echo "<p style='color: red;'>Database directory does not exist: " . htmlspecialchars($dbDir) . "</p>";
    echo "<p>Attempting to create directory...</p>";
    
    if (mkdir($dbDir, 0755, true)) {
        echo "<p style='color: green;'>Directory created successfully.</p>";
    } else {
        echo "<p style='color: red;'>Failed to create directory. Please check permissions.</p>";
    }
} else {
    echo "<p style='color: green;'>Database directory exists.</p>";
    
    // Check directory permissions
    $dirPerms = substr(sprintf('%o', fileperms($dbDir)), -4);
    echo "<p>Directory permissions: " . htmlspecialchars($dirPerms) . "</p>";
    
    if (!is_writable($dbDir)) {
        echo "<p style='color: red;'>Directory is not writable. Please check permissions.</p>";
    } else {
        echo "<p style='color: green;'>Directory is writable.</p>";
    }
}

// Check database file
echo "<h2>Checking Database File</h2>";
if (file_exists($dbPath)) {
    echo "<p style='color: green;'>Database file exists.</p>";
    
    // Check file permissions
    $filePerms = substr(sprintf('%o', fileperms($dbPath)), -4);
    echo "<p>File permissions: " . htmlspecialchars($filePerms) . "</p>";
    
    if (!is_readable($dbPath)) {
        echo "<p style='color: red;'>Database file is not readable. Please check permissions.</p>";
    } else {
        echo "<p style='color: green;'>Database file is readable.</p>";
    }
    
    if (!is_writable($dbPath)) {
        echo "<p style='color: red;'>Database file is not writable. Please check permissions.</p>";
    } else {
        echo "<p style='color: green;'>Database file is writable.</p>";
    }
    
    // Check file size
    $fileSize = filesize($dbPath);
    echo "<p>File size: " . htmlspecialchars($fileSize) . " bytes</p>";
    
    if ($fileSize === 0) {
        echo "<p style='color: red;'>Warning: Database file is empty.</p>";
    }
} else {
    echo "<p style='color: red;'>Database file does not exist: " . htmlspecialchars($dbPath) . "</p>";
}

// Try to connect to the database
echo "<h2>Testing Database Connection</h2>";
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>Database connection successful.</p>";
    
    // Check if tables exist
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tableList = $tables->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tableList) > 0) {
        echo "<p style='color: green;'>Tables found: " . htmlspecialchars(implode(', ', $tableList)) . "</p>";
        
        // Check if required tables exist
        $requiredTables = ['contacts', 'sms_history'];
        $missingTables = array_diff($requiredTables, $tableList);
        
        if (count($missingTables) > 0) {
            echo "<p style='color: red;'>Missing required tables: " . htmlspecialchars(implode(', ', $missingTables)) . "</p>";
        } else {
            echo "<p style='color: green;'>All required tables exist.</p>";
        }
        
        // Check table structure
        echo "<h3>Table Structure</h3>";
        foreach ($tableList as $table) {
            $columns = $db->query("PRAGMA table_info($table)");
            $columnList = $columns->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h4>Table: " . htmlspecialchars($table) . "</h4>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Name</th><th>Type</th><th>Not Null</th><th>Default</th><th>Primary Key</th></tr>";
            
            foreach ($columnList as $column) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($column['name']) . "</td>";
                echo "<td>" . htmlspecialchars($column['type']) . "</td>";
                echo "<td>" . htmlspecialchars($column['notnull']) . "</td>";
                echo "<td>" . htmlspecialchars($column['dflt_value'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($column['pk']) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
        
        // Count records
        echo "<h3>Record Counts</h3>";
        foreach ($tableList as $table) {
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p>Table " . htmlspecialchars($table) . ": " . htmlspecialchars($count) . " records</p>";
        }
    } else {
        echo "<p style='color: red;'>No tables found in the database.</p>";
        
        // Check if schema file exists
        $schemaFile = 'schicksms/db/schema.sql';
        if (file_exists($schemaFile)) {
            echo "<p>Schema file exists. You may need to import it.</p>";
            echo "<pre>" . htmlspecialchars(file_get_contents($schemaFile)) . "</pre>";
        } else {
            echo "<p style='color: red;'>Schema file not found: " . htmlspecialchars($schemaFile) . "</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check web server user
echo "<h2>Web Server Information</h2>";
echo "<p>Current user: " . htmlspecialchars(get_current_user()) . "</p>";
echo "<p>PHP version: " . htmlspecialchars(PHP_VERSION) . "</p>";
echo "<p>Server software: " . htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";

// Check SQLite version
echo "<h2>SQLite Information</h2>";
if (class_exists('SQLite3')) {
    $version = SQLite3::version();
    echo "<p>SQLite version: " . htmlspecialchars($version['versionString']) . "</p>";
} else {
    echo "<p style='color: red;'>SQLite3 class not found. Please check if SQLite is installed.</p>";
}

// Check PDO drivers
echo "<p>PDO drivers: " . htmlspecialchars(implode(', ', PDO::getAvailableDrivers())) . "</p>";

// Recommendations
echo "<h2>Recommendations</h2>";
echo "<ul>";

if (!file_exists($dbPath)) {
    echo "<li>Create the database file: <code>touch " . htmlspecialchars($dbPath) . "</code></li>";
    echo "<li>Set correct permissions: <code>chown www-data:www-data " . htmlspecialchars($dbPath) . "</code></li>";
    echo "<li>Set correct permissions: <code>chmod 664 " . htmlspecialchars($dbPath) . "</code></li>";
    echo "<li>Import the schema: <code>sqlite3 " . htmlspecialchars($dbPath) . " < schicksms/db/schema.sql</code></li>";
} elseif (file_exists($dbPath) && (!is_readable($dbPath) || !is_writable($dbPath))) {
    echo "<li>Fix database file permissions: <code>chown www-data:www-data " . htmlspecialchars($dbPath) . "</code></li>";
    echo "<li>Fix database file permissions: <code>chmod 664 " . htmlspecialchars($dbPath) . "</code></li>";
}

if (is_dir($dbDir) && !is_writable($dbDir)) {
    echo "<li>Fix database directory permissions: <code>chown www-data:www-data " . htmlspecialchars($dbDir) . "</code></li>";
    echo "<li>Fix database directory permissions: <code>chmod 775 " . htmlspecialchars($dbDir) . "</code></li>";
}

echo "<li>Run the fix-permissions.sh script: <code>sudo bash fix-permissions.sh</code></li>";
echo "</ul>";

echo "<p>Debug completed at: " . date('Y-m-d H:i:s') . "</p>";
