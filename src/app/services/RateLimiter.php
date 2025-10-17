<?php
/**
 * RateLimiter Service
 *
 * Prevents brute force attacks and abuse by limiting actions per IP address.
 * Implements sliding window rate limiting with automatic cleanup.
 *
 * Usage:
 *   $limiter = new RateLimiter();
 *   if ($limiter->isBlocked('login')) {
 *       die('Too many attempts. Try again later.');
 *   }
 *
 *   // After failed login
 *   $limiter->recordAttempt('login');
 *
 *   // After successful login
 *   $limiter->clearAttempts('login');
 */

class RateLimiter
{
    private $db;
    private $ipAddress;

    // Rate limit configurations
    private $limits = [
        "login" => [
            "max_attempts" => 5,
            "window_minutes" => 15,
            "block_minutes" => 30,
        ],
        "register" => [
            "max_attempts" => 3,
            "window_minutes" => 60,
            "block_minutes" => 60,
        ],
        "password_reset" => [
            "max_attempts" => 3,
            "window_minutes" => 60,
            "block_minutes" => 60,
        ],
        "email_verification" => [
            "max_attempts" => 5,
            "window_minutes" => 60,
            "block_minutes" => 30,
        ],
    ];

    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->ipAddress = $this->getClientIp();
    }

    /**
     * Check if IP is currently rate limited for an action
     *
     * @param string $action The action to check (login, register, etc.)
     * @return bool True if blocked, false if allowed
     */
    public function isBlocked(string $action): bool
    {
        if (!isset($this->limits[$action])) {
            return false; // Unknown actions are not rate limited
        }

        $config = $this->limits[$action];

        try {
            // Check if currently blocked
            $stmt = $this->db->query(
                "SELECT blocked_until
                 FROM rate_limits
                 WHERE ip_address = ?
                 AND action = ?
                 AND blocked_until > NOW()
                 ORDER BY blocked_until DESC
                 LIMIT 1",
                [$this->ipAddress, $action],
            );

            $record = $stmt->fetch();

            if (!$record) {
                return false; // No record, not blocked
            }

            // Check if explicitly blocked
            if (
                $record["blocked_until"] &&
                strtotime($record["blocked_until"]) > time()
            ) {
                return true;
            }

            // Check if within window and exceeded attempts using MySQL's DATE_SUB
            $stmt = $this->db->query(
                "SELECT COALESCE(SUM(attempts), 0) as total_attempts
                 FROM rate_limits
                 WHERE ip_address = ?
                 AND action = ?
                 AND last_attempt >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                [$this->ipAddress, $action, $config["window_minutes"]],
            );

            $result = $stmt->fetch();
            $attemptCount = (int) ($result["total_attempts"] ?? 0);

            if ($attemptCount >= $config["max_attempts"]) {
                // Auto-block if exceeded
                $this->blockIp($action, $config["block_minutes"]);
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("RateLimiter::isBlocked error: " . $e->getMessage());
            return false; // Fail open for availability
        }
    }

    /**
     * Record an attempt for rate limiting
     *
     * @param string $action The action being attempted
     * @return void
     */
    public function recordAttempt(string $action): void
    {
        if (!isset($this->limits[$action])) {
            return;
        }

        try {
            // Check if recent record exists using MySQL's DATE_SUB
            $stmt = $this->db->query(
                "SELECT id, attempts FROM rate_limits
                 WHERE ip_address = ?
                 AND action = ?
                 AND last_attempt >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 ORDER BY last_attempt DESC
                 LIMIT 1",
                [
                    $this->ipAddress,
                    $action,
                    $this->limits[$action]["window_minutes"],
                ],
            );

            $record = $stmt->fetch();

            if ($record) {
                // Update existing record
                $this->db->query(
                    "UPDATE rate_limits
                     SET attempts = attempts + 1,
                         last_attempt = NOW()
                     WHERE id = ?",
                    [$record["id"]],
                );
            } else {
                // Create new record
                $this->db->query(
                    "INSERT INTO rate_limits (ip_address, action, attempts, first_attempt, last_attempt)
                     VALUES (?, ?, 1, NOW(), NOW())",
                    [$this->ipAddress, $action],
                );
            }

            // Check if should be blocked
            $newAttempts = ($record["attempts"] ?? 0) + 1;
            if ($newAttempts >= $this->limits[$action]["max_attempts"]) {
                $this->blockIp(
                    $action,
                    $this->limits[$action]["block_minutes"],
                );
            }
        } catch (Exception $e) {
            error_log("RateLimiter::recordAttempt error: " . $e->getMessage());
        }
    }

    /**
     * Clear attempts for an IP/action (call after successful action)
     *
     * @param string $action The action to clear
     * @return void
     */
    public function clearAttempts(string $action): void
    {
        try {
            $this->db->query(
                "DELETE FROM rate_limits
                 WHERE ip_address = ?
                 AND action = ?",
                [$this->ipAddress, $action],
            );
        } catch (Exception $e) {
            error_log("RateLimiter::clearAttempts error: " . $e->getMessage());
        }
    }

    /**
     * Block an IP for a specific action
     *
     * @param string $action The action to block
     * @param int $minutes Number of minutes to block
     * @return void
     */
    private function blockIp(string $action, int $minutes): void
    {
        try {
            // Use MySQL's DATE_ADD to avoid timezone issues
            $this->db->query(
                "UPDATE rate_limits
                 SET blocked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                 WHERE ip_address = ?
                 AND action = ?
                 AND (blocked_until IS NULL OR blocked_until < NOW())",
                [$minutes, $this->ipAddress, $action],
            );
        } catch (Exception $e) {
            error_log("RateLimiter::blockIp error: " . $e->getMessage());
        }
    }

    /**
     * Get remaining attempts before block
     *
     * @param string $action The action to check
     * @return array ['remaining' => int, 'reset_at' => string|null]
     */
    public function getRemainingAttempts(string $action): array
    {
        if (!isset($this->limits[$action])) {
            return ["remaining" => 999, "reset_at" => null];
        }

        $config = $this->limits[$action];

        try {
            $stmt = $this->db->query(
                "SELECT SUM(attempts) as total_attempts, MAX(last_attempt) as last_attempt
                 FROM rate_limits
                 WHERE ip_address = ?
                 AND action = ?
                 AND last_attempt >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                [$this->ipAddress, $action, $config["window_minutes"]],
            );

            $result = $stmt->fetch();
            $attempts = $result["total_attempts"] ?? 0;
            $lastAttempt = $result["last_attempt"];

            $remaining = max(0, $config["max_attempts"] - $attempts);
            $resetAt = $lastAttempt
                ? date(
                    "Y-m-d H:i:s",
                    strtotime(
                        $lastAttempt . " +{$config["window_minutes"]} minutes",
                    ),
                )
                : null;

            return [
                "remaining" => $remaining,
                "reset_at" => $resetAt,
                "blocked" => $remaining <= 0,
            ];
        } catch (Exception $e) {
            error_log(
                "RateLimiter::getRemainingAttempts error: " . $e->getMessage(),
            );
            return ["remaining" => 999, "reset_at" => null, "blocked" => false];
        }
    }

    /**
     * Get time until unblocked
     *
     * @param string $action The action to check
     * @return int|null Seconds until unblocked, null if not blocked
     */
    public function getBlockedTimeRemaining(string $action): ?int
    {
        try {
            // Use MySQL to calculate the time difference to avoid timezone issues
            $stmt = $this->db->query(
                "SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until) as seconds_remaining
                 FROM rate_limits
                 WHERE ip_address = ?
                 AND action = ?
                 AND blocked_until > NOW()
                 ORDER BY blocked_until DESC
                 LIMIT 1",
                [$this->ipAddress, $action],
            );

            $record = $stmt->fetch();

            if ($record && isset($record["seconds_remaining"])) {
                return max(0, (int) $record["seconds_remaining"]);
            }

            return null;
        } catch (Exception $e) {
            error_log(
                "RateLimiter::getBlockedTimeRemaining error: " .
                    $e->getMessage(),
            );
            return null;
        }
    }

    /**
     * Get client IP address (handles proxies)
     *
     * @return string IP address
     */
    public function getClientIp(): string
    {
        $ipKeys = [
            "HTTP_CF_CONNECTING_IP", // Cloudflare
            "HTTP_X_FORWARDED_FOR", // Standard proxy header
            "HTTP_X_REAL_IP", // Nginx proxy
            "REMOTE_ADDR", // Direct connection
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];

                // Handle comma-separated IPs (X-Forwarded-For can have multiple)
                if (strpos($ip, ",") !== false) {
                    $ips = explode(",", $ip);
                    $ip = trim($ips[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return "0.0.0.0"; // Fallback
    }

    /**
     * Format time remaining for display
     *
     * @param int $seconds Seconds remaining
     * @return string Human-readable time
     */
    public static function formatTimeRemaining(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . " second" . ($seconds !== 1 ? "s" : "");
        } elseif ($seconds < 3600) {
            $minutes = (int) round($seconds / 60);
            return $minutes . " minute" . ($minutes !== 1 ? "s" : "");
        } else {
            $hours = (int) round($seconds / 3600);
            return $hours . " hour" . ($hours !== 1 ? "s" : "");
        }
    }

    /**
     * Clean up old rate limit records (call periodically)
     *
     * @param int $daysOld Delete records older than this many days
     * @return int Number of records deleted
     */
    public static function cleanup(int $daysOld = 7): int
    {
        global $db;

        try {
            $stmt = $db->query(
                "DELETE FROM rate_limits
                 WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? DAY)
                 AND (blocked_until IS NULL OR blocked_until < NOW())",
                [$daysOld],
            );

            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("RateLimiter::cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}
