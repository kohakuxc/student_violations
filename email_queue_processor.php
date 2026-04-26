<?php
/**
 * Email Queue Processor
 * 
 * This script processes pending emails from the email queue.
 * Can be run via cron every 5 minutes to send queued emails.
 * 
 * Usage: php email_queue_processor.php
 * Or via cron (every 5 minutes): cron expression star-slash-5 * * * *
 */

require_once __DIR__ . '/config/db_connection.php';
require_once __DIR__ . '/helper/SmtpEmailHelper.php';

try {
    if (php_sapi_name() !== 'cli') {
        die("This script must be run from the command line\n");
    }

    echo "[" . date('Y-m-d H:i:s') . "] Starting email queue processor...\n";

    // Process queued emails
    SmtpEmailHelper::processQueue();
    
    echo "[" . date('Y-m-d H:i:s') . "] Email queue processor completed.\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
exit(0);
?>
