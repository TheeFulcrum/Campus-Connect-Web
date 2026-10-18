<?php
// Listing urgency helpers: limited stock, priority and event dates, plus the alerts they trigger.
require_once __DIR__ . '/../auth_lib.php';
require_once __DIR__ . '/_rate_limit.php';

const CCW_LOW_STOCK_THRESHOLD = 3;
const CCW_MAX_PRIORITY_LISTINGS_PER_DAY = 3;
const CCW_CAMPUS_ALERT_RECIPIENT_CAP = 500;

/**
 * Reads the optional quantity / priority / event_at fields from a request body.
 * Keys that are absent stay absent in the result so updates leave them untouched.
 *
 * @return array ['fields' => [...], 'error' => string|null]
 */
function ccw_parse_listing_urgency(array $body): array {
    $fields = [];

    if (array_key_exists('quantity', $body)) {
        $q = $body['quantity'];
        if ($q === null || $q === '') {
            $fields['quantity'] = null;
        } elseif (is_numeric($q) && (int) $q == $q && (int) $q >= 0 && (int) $q <= 100000) {
            $fields['quantity'] = (int) $q;
        } else {
            return ['fields' => [], 'error' => 'Quantity must be a whole number of 0 or more.'];
        }
    }

    if (array_key_exists('priority', $body)) {
        $fields['priority'] = filter_var($body['priority'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    if (array_key_exists('event_at', $body)) {
        $e = $body['event_at'];
        if ($e === null || $e === '' || $e === 0) {
            $fields['event_at'] = null;
        } elseif (is_numeric($e) && (int) $e > time() - 86400 && (int) $e < time() + 2 * 365 * 86400) {
            $fields['event_at'] = (int) $e;
        } else {
            return ['fields' => [], 'error' => 'Event date must be a valid upcoming date.'];
        }
    }

    return ['fields' => $fields, 'error' => null];
}

/** Priority only means something for limited goods, and is capped per user per day. */
function ccw_effective_priority(?int $priority, ?int $quantity, int $userId): int {
    if (!$priority || $quantity === null || $quantity <= 0) return 0;
    return ccw_rate_limit('priority_listing', 'user:' . $userId, CCW_MAX_PRIORITY_LISTINGS_PER_DAY, 86400) ? 1 : 0;
}

/** Marks an alert as sent for a listing; returns false when it was already sent. */
function ccw_claim_listing_alert(int $listingId, string $kind): bool {
    $stmt = ccw_db()->prepare('INSERT OR IGNORE INTO listing_alerts (listing_id, kind, sent_at) VALUES (:l, :k, :t)');
    $stmt->execute(['l' => $listingId, 'k' => $kind, 't' => (new DateTime())->format('Y-m-d H:i:s')]);
    return $stmt->rowCount() === 1;
}

function ccw_stock_phrase(int $quantity): string {
    return $quantity === 1 ? 'Only 1 left' : 'Only ' . $quantity . ' left';
}

/** Tells everyone on the listing's campus about a new priority listing with limited stock. */
function ccw_alert_campus_limited_listing(int $listingId, int $ownerId, string $campus, string $title, int $quantity): void {
    if (!ccw_claim_listing_alert($listingId, 'priority_new')) return;
    $stmt = ccw_db()->prepare('SELECT id FROM users WHERE campus = :c AND id != :o LIMIT ' . CCW_CAMPUS_ALERT_RECIPIENT_CAP);
    $stmt->execute(['c' => $campus, 'o' => $ownerId]);
    $body = ccw_stock_phrase($quantity) . ': ' . $title;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        ccw_notify((int) $userId, 'listing_limited', $ownerId, $listingId, $body);
    }
}

/** Tells people who saved a listing that stock is nearly gone (sent once per listing). */
function ccw_alert_low_stock(int $listingId, int $ownerId, string $title, int $quantity): void {
    if ($quantity > CCW_LOW_STOCK_THRESHOLD || !ccw_claim_listing_alert($listingId, 'low_stock')) return;
    $stmt = ccw_db()->prepare('SELECT user_id FROM saves WHERE listing_id = :l AND user_id != :o');
    $stmt->execute(['l' => $listingId, 'o' => $ownerId]);
    $body = ($quantity <= 0 ? 'Sold out: ' : ccw_stock_phrase($quantity) . ': ') . $title;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        ccw_notify((int) $userId, 'low_stock', $ownerId, $listingId, $body);
    }
}

/** Lets the API respond first so slow push delivery never delays the client. */
function ccw_finish_response(): void {
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    @set_time_limit(120);
}
