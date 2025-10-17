# Rate Limiting Timezone Fix

## Problem
The rate limiting system was blocking users for 30 minutes, but the website displayed "try again in 3 hours" instead of the correct "30 minutes".

## Root Cause
The issue was caused by timezone mismatches between PHP and MySQL when calculating the remaining block time:

1. **Block Time Storage**: The `blocked_until` timestamp was correctly stored in MySQL using `DATE_ADD(NOW(), INTERVAL 30 MINUTE)`
2. **Time Retrieval**: When PHP retrieved this timestamp and converted it using `strtotime($record['blocked_until'])`, it interpreted the timestamp in PHP's timezone
3. **Time Comparison**: PHP then compared this to `time()` (current PHP time), which could be in a different timezone
4. **Result**: This timezone mismatch caused incorrect time calculations, showing hours instead of minutes

## Solution
Instead of using PHP to calculate the time difference, we now use MySQL's `TIMESTAMPDIFF()` function to compute the difference entirely within the database:

### Before (Incorrect):
```php
$stmt = $this->db->query(
    "SELECT blocked_until FROM rate_limits WHERE ..."
);
$blockedUntil = strtotime($record["blocked_until"]); // PHP timezone
$now = time(); // PHP timezone
return max(0, $blockedUntil - $now); // Potential mismatch
```

### After (Correct):
```php
$stmt = $this->db->query(
    "SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until) as seconds_remaining
     FROM rate_limits WHERE ..."
);
return max(0, (int) $record["seconds_remaining"]); // MySQL-calculated difference
```

## Changes Made

### 1. Fixed `getBlockedTimeRemaining()` Method
**File**: `src/app/services/RateLimiter.php`

Changed the SQL query to use `TIMESTAMPDIFF(SECOND, NOW(), blocked_until)` to calculate the time difference in MySQL rather than PHP. This ensures both the current time and blocked time are in the same timezone (MySQL's timezone).

### 2. Improved `formatTimeRemaining()` Method
**File**: `src/app/services/RateLimiter.php`

Changed from `ceil()` to `round()` for more accurate time display:
- **Before**: 1790 seconds → `ceil(1790/60)` → 30 minutes (acceptable)
- **Before**: 1800 seconds → `ceil(1800/3600)` → 1 hour (if it reached hours branch)
- **After**: Uses `round()` for more intuitive rounding

## Verification

### Test Results
Running `php test-rate-limit.php` now correctly shows:
```
Attempt #6:
  ❌ BLOCKED! Time remaining: 30 minutes

=== Database Record ===
Minutes until unblock: 30
```

### Key Points
- Login is blocked for exactly **30 minutes** (as configured)
- Registration is blocked for **60 minutes** (as configured)
- Password reset is blocked for **60 minutes** (as configured)
- Email verification is blocked for **30 minutes** (as configured)

## Technical Details

### Why MySQL Time Calculation?
1. **Single Source of Truth**: Both `NOW()` and `blocked_until` use MySQL's timezone
2. **Consistency**: No conversion between PHP and MySQL timezones
3. **Accuracy**: Direct calculation in the database using `TIMESTAMPDIFF()`
4. **No Drift**: Eliminates potential timezone configuration mismatches

### Rate Limit Configuration
Located in `RateLimiter.php`:
```php
private $limits = [
    "login" => [
        "max_attempts" => 5,
        "window_minutes" => 15,
        "block_minutes" => 30,  // ← Blocks for 30 minutes
    ],
    // ... other actions
];
```

## Files Modified
1. `src/app/services/RateLimiter.php`
   - `getBlockedTimeRemaining()` - Uses MySQL `TIMESTAMPDIFF()`
   - `formatTimeRemaining()` - Uses `round()` instead of `ceil()`

## Testing
To test the rate limiting:
1. **CLI Test**: `php test-rate-limit.php`
2. **Web Test**: Visit `src/app/test-login-rate-limit.php`
3. **Real Test**: Try logging in with wrong password 5 times

All tests should now show the correct blocking duration (30 minutes for login).

## Impact
- ✅ Displays accurate blocking time to users
- ✅ No timezone-related confusion
- ✅ More professional user experience
- ✅ Maintains security (still blocks for the correct duration)

## Future Enhancements
Consider these optional improvements:
1. Add a countdown timer in the UI showing remaining time
2. Implement per-user lockout (in addition to IP-based)
3. Add admin notifications for repeated lockouts
4. Create an admin dashboard to view rate limit events

---

**Date Fixed**: January 2025  
**Issue**: Timezone mismatch causing incorrect time display  
**Status**: ✅ Resolved