<?php
require_once './src/config/database.php';

global $db;

echo "<h2>Current User Timestamps</h2>";
echo "<p>Server Time (PHP): " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Server Timezone: " . date_default_timezone_get() . "</p>";

$stmt = $db->query("SELECT username, last_active, last_activity, 
                    NOW() as mysql_now, 
                    UTC_TIMESTAMP() as mysql_utc,
                    TIMESTAMPDIFF(MINUTE, last_activity, UTC_TIMESTAMP()) as minutes_since_activity
                    FROM users");
$users = $stmt->fetchAll();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Username</th><th>last_active</th><th>last_activity</th><th>MySQL NOW()</th><th>MySQL UTC</th><th>Minutes Since Activity</th></tr>";

foreach ($users as $user) {
    echo "<tr>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['last_active']}</td>";
    echo "<td>{$user['last_activity']}</td>";
    echo "<td>{$user['mysql_now']}</td>";
    echo "<td>{$user['mysql_utc']}</td>";
    echo "<td>{$user['minutes_since_activity']} min</td>";
    echo "</tr>";
}

echo "</table>";

// Test the formatting function
require_once './src/app/models/user-functions-db.php';

echo "<h2>Formatted Status</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Username</th><th>Status</th></tr>";

foreach ($users as $user) {
    $status = getLastActiveFormatted($user['last_active'], $user['last_activity']);
    echo "<tr>";
    echo "<td>{$user['username']}</td>";
    echo "<td><strong>{$status}</strong></td>";
    echo "</tr>";
}

echo "</table>";