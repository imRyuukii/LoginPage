<?php
require_once './src/config/database.php';
require_once './src/app/services/RateLimiter.php';

$limiter = new RateLimiter();

// Check if blocked
$isBlocked = $limiter->isBlocked('login');
echo "Is Blocked: " . ($isBlocked ? "YES" : "NO") . "\n\n";

if ($isBlocked) {
    $timeRemaining = $limiter->getBlockedTimeRemaining('login');
    echo "Time Remaining (raw): $timeRemaining seconds\n";
    echo "Formatted: " . RateLimiter::formatTimeRemaining($timeRemaining) . "\n\n";
    
    // Calculate manually
    $minutes = floor($timeRemaining / 60);
    $seconds = $timeRemaining % 60;
    echo "Manual calculation: {$minutes} minutes and {$seconds} seconds\n\n";
}

// Check database directly
global $db;
$stmt = $db->query(
    "SELECT blocked_until, 
     NOW() as current_time,
     TIMESTAMPDIFF(SECOND, NOW(), blocked_until) as seconds_left,
     TIMESTAMPDIFF(MINUTE, NOW(), blocked_until) as minutes_left
     FROM rate_limits 
     WHERE action='login' 
     AND blocked_until > NOW()
     LIMIT 1"
);

$record = $stmt->fetch();
if ($record) {
    echo "=== Database Info ===\n";
    echo "Current Time: {$record['current_time']}\n";
    echo "Blocked Until: {$record['blocked_until']}\n";
    echo "Seconds Left: {$record['seconds_left']}\n";
    echo "Minutes Left: {$record['minutes_left']}\n";
}
