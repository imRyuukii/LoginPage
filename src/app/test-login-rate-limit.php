<?php
// Quick test to see if login rate limiting works
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/services/RateLimiter.php';

$limiter = new RateLimiter();

echo "<h2>Rate Limit Status for Login</h2>";
echo "<p>Your IP: " . ($limiter->getClientIp() ?? 'Unknown') . "</p>";

if ($limiter->isBlocked('login')) {
    $timeRemaining = $limiter->getBlockedTimeRemaining('login');
    echo "<p style='color: red;'><strong>BLOCKED!</strong> Try again in " . RateLimiter::formatTimeRemaining($timeRemaining) . "</p>";
} else {
    $remaining = $limiter->getRemainingAttempts('login');
    echo "<p style='color: green;'>Status: <strong>Allowed</strong></p>";
    echo "<p>Attempts remaining: <strong>{$remaining['remaining']}</strong> out of 5</p>";
}

// Show recent attempts from database
global $db;
$stmt = $db->query(
    "SELECT ip_address, attempts, last_attempt, blocked_until,
     TIMESTAMPDIFF(MINUTE, NOW(), blocked_until) as minutes_left
     FROM rate_limits 
     WHERE action='login' 
     ORDER BY last_attempt DESC 
     LIMIT 5"
);

echo "<h3>Recent Login Attempts (Database)</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>IP</th><th>Attempts</th><th>Last Attempt</th><th>Blocked Until</th><th>Minutes Left</th></tr>";

while ($row = $stmt->fetch()) {
    echo "<tr>";
    echo "<td>{$row['ip_address']}</td>";
    echo "<td>{$row['attempts']}</td>";
    echo "<td>{$row['last_attempt']}</td>";
    echo "<td>" . ($row['blocked_until'] ?? 'Not blocked') . "</td>";
    echo "<td>" . ($row['minutes_left'] ?? 'N/A') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><a href='../controllers/login.php'>Go to Login Page</a></p>";
?>
