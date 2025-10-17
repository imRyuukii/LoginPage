<?php
// Test script for rate limiting
require_once './src/config/database.php';
require_once './src/app/services/RateLimiter.php';

echo "=== Rate Limiter Test ===\n\n";

$limiter = new RateLimiter();

// Simulate 5 failed login attempts
for ($i = 1; $i <= 6; $i++) {
    echo "Attempt #$i:\n";
    
    if ($limiter->isBlocked('login')) {
        $timeRemaining = $limiter->getBlockedTimeRemaining('login');
        echo "  ❌ BLOCKED! Time remaining: " . RateLimiter::formatTimeRemaining($timeRemaining) . "\n";
    } else {
        echo "  ✓ Allowed\n";
        $limiter->recordAttempt('login');
        
        $remaining = $limiter->getRemainingAttempts('login');
        echo "  Attempts remaining: {$remaining['remaining']}\n";
    }
    echo "\n";
}

// Check database
global $db;
$stmt = $db->query("SELECT ip_address, action, attempts, blocked_until, 
                    TIMESTAMPDIFF(MINUTE, NOW(), blocked_until) as minutes_until_unblock 
                    FROM rate_limits WHERE action='login' ORDER BY last_attempt DESC LIMIT 1");
$record = $stmt->fetch();

echo "=== Database Record ===\n";
echo "IP: {$record['ip_address']}\n";
echo "Attempts: {$record['attempts']}\n";
echo "Blocked until: {$record['blocked_until']}\n";
echo "Minutes until unblock: {$record['minutes_until_unblock']}\n";

echo "\n=== Test Complete ===\n";
