<?php
// Sends event reminders (about 24 hours and 1 hour before) to the owner and to everyone who saved the event.
// Run from cron every 10 minutes:  */10 * * * * www-data php /var/www/campusconnect/cron/send-reminders.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../auth_lib.php';
ccw_load_environment();
require_once __DIR__ . '/../api/_listing_alerts.php';

$pdo = ccw_db();
$now = time();
$timezone = new DateTimeZone(ccw_environment('CCW_TIMEZONE') ?: 'Africa/Harare');

$windows = [
    'event_1h' => [$now, $now + 3600, 'starts in about an hour'],
    'event_24h' => [$now + 3600, $now + 86400, 'is coming up within 24 hours'],
];

foreach ($windows as $kind => [$from, $to, $phrase]) {
    $stmt = $pdo->prepare("SELECT id, user_id, title, event_at FROM listings
        WHERE event_at IS NOT NULL AND event_at > :from AND event_at <= :to AND status = 'active' AND archived = 0");
    $stmt->execute(['from' => $from, 'to' => $to]);

    foreach ($stmt->fetchAll() as $event) {
        $listingId = (int) $event['id'];
        if (!ccw_claim_listing_alert($listingId, $kind)) continue;

        $savers = $pdo->prepare('SELECT user_id FROM saves WHERE listing_id = :l');
        $savers->execute(['l' => $listingId]);
        $recipients = array_unique(array_merge([(int) $event['user_id']], array_map('intval', $savers->fetchAll(PDO::FETCH_COLUMN))));

        $when = (new DateTime('@' . (int) $event['event_at']))->setTimezone($timezone)->format('D j M, H:i');
        $body = '"' . $event['title'] . '" ' . $phrase . ' (' . $when . ').';
        foreach ($recipients as $userId) {
            // Passing null as actor so the owner is reminded too
            ccw_notify($userId, 'event_reminder', null, $listingId, $body);
        }
    }
}
