<?php
require_once __DIR__ . '/../config/db_connection.php';

class SmtpEmailHelper
{
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_password;
    private $from_email;
    private $from_name;
    private $use_queue;

    public function __construct()
    {
        // Load SMTP settings from environment or config
        $this->smtp_host = getenv('SMTP_HOST') ?: 'smtp.mailtrap.io';
        $this->smtp_port = (int) (getenv('SMTP_PORT') ?: 465);
        $this->smtp_user = getenv('SMTP_USER') ?: '';
        $this->smtp_password = getenv('SMTP_PASSWORD') ?: '';
        $this->from_email = getenv('EMAIL_FROM_ADDRESS') ?: 'noreply@fairview-violations.edu.ph';
        $this->from_name = getenv('EMAIL_FROM_NAME') ?: 'STI Student Violations System';
        $this->use_queue = (bool) (getenv('EMAIL_USE_QUEUE') ?: true);
    }

    /**
     * Send email via SMTP or queue
     */
    public function sendEmail($to_email, $to_name, $subject, $body_html, $body_text = null, $email_type = 'generic', $related_id = null)
    {
        try {
            if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address: ' . $to_email);
            }

            // Queue email for async delivery if enabled
            if ($this->use_queue) {
                return $this->queueEmail($to_email, $to_name, $subject, $body_html, $body_text, $email_type, $related_id);
            }

            // Send immediately via SMTP
            return $this->sendViaSMTP($to_email, $to_name, $subject, $body_html, $body_text);
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue email for later delivery
     */
    private function queueEmail($to_email, $to_name, $subject, $body_html, $body_text, $email_type, $related_id)
    {
        try {
            global $conn;

            $query = "INSERT INTO email_queue (to_address, to_name, subject, body_html, body_text, email_type, related_appointment_id, status, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

            $stmt = $conn->prepare($query);
            return $stmt->execute([
                (string) $to_email,
                (string) ($to_name ?? ''),
                (string) $subject,
                (string) $body_html,
                (string) ($body_text ?? ''),
                (string) $email_type,
                (int) ($related_id ?? null)
            ]);
        } catch (Exception $e) {
            error_log("Error queueing email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email via SMTP
     */
    private function sendViaSMTP($to_email, $to_name, $subject, $body_html, $body_text = null)
    {
        try {
            // Build headers
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . $this->from_name . " <" . $this->from_email . ">\r\n";
            $headers .= "Reply-To: " . $this->from_email . "\r\n";

            // Fallback to PHP mail if SMTP not configured
            if (empty($this->smtp_user) || empty($this->smtp_password)) {
                return mail($to_email, $subject, $body_html, $headers);
            }

            // Use PHPMailer if available, otherwise attempt direct SMTP
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendViaPhpMailer($to_email, $to_name, $subject, $body_html, $body_text);
            }

            // Fallback to PHP mail
            return mail($to_email, $subject, $body_html, $headers);
        } catch (Exception $e) {
            error_log("SMTP error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send via PHPMailer (if available)
     */
    private function sendViaPhpMailer($to_email, $to_name, $subject, $body_html, $body_text)
    {
        try {
            // Check if PHPMailer is available
            if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                return false; // Fallback to mail()
            }

            // @phpstan-ignore-next-line
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            $mailer->isSMTP();
            $mailer->Host = $this->smtp_host;
            $mailer->SMTPAuth = true;
            $mailer->Username = $this->smtp_user;
            $mailer->Password = $this->smtp_password;
            $mailer->SMTPSecure = 'tls';
            $mailer->Port = $this->smtp_port;

            $mailer->setFrom($this->from_email, $this->from_name);
            $mailer->addAddress($to_email, $to_name ?? '');
            $mailer->Subject = $subject;
            $mailer->Body = $body_html;
            $mailer->AltBody = $body_text ?? strip_tags($body_html);
            $mailer->isHTML(true);

            return $mailer->send();
        } catch (Throwable $e) {
            error_log("PHPMailer error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process queued emails (run via cron or manual trigger)
     */
    public static function processQueue($limit = 10)
    {
        try {
            global $conn;

            $query = "SELECT email_id, to_address, to_name, subject, body_html, body_text, attempt_count, max_attempts
                      FROM email_queue
                      WHERE status = 'pending' AND attempt_count < max_attempts
                      ORDER BY created_at ASC
                      LIMIT ?";

            $stmt = $conn->prepare($query);
            $stmt->execute([(int) $limit]);
            $emails = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $mailer = new self();
            $processed = 0;

            foreach ($emails as $email) {
                $success = $mailer->sendViaSMTP(
                    $email['to_address'],
                    $email['to_name'],
                    $email['subject'],
                    $email['body_html'],
                    $email['body_text']
                );

                // Log attempt
                $log_query = "INSERT INTO email_delivery_log (email_id, attempt_number, status, attempt_at)
                              VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    (int) $email['email_id'],
                    (int) ($email['attempt_count'] + 1),
                    (string) ($success ? 'success' : 'temporary_failure')
                ]);

                if ($success) {
                    // Mark as sent
                    $update_query = "UPDATE email_queue 
                                     SET status = 'sent', sent_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                                     WHERE email_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->execute([(int) $email['email_id']]);
                } else {
                    // Increment attempt count
                    $update_query = "UPDATE email_queue 
                                     SET attempt_count = attempt_count + 1, updated_at = CURRENT_TIMESTAMP
                                     WHERE email_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->execute([(int) $email['email_id']]);
                }

                $processed++;
            }

            return $processed;
        } catch (Exception $e) {
            error_log("Error processing email queue: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Retry failed emails
     */
    public static function retryFailedEmails($max_age_hours = 72)
    {
        try {
            global $conn;

            $query = "UPDATE email_queue 
                      SET attempt_count = 0, status = 'pending', updated_at = CURRENT_TIMESTAMP
                      WHERE status IN ('failed', 'temporary_failure')
                      AND attempt_count < max_attempts
                      AND updated_at > CURRENT_TIMESTAMP - INTERVAL ? HOUR";

            $stmt = $conn->prepare($query);
            $stmt->execute([(int) $max_age_hours]);

            return self::processQueue(10);
        } catch (Exception $e) {
            error_log("Error retrying failed emails: " . $e->getMessage());
            return 0;
        }
    }
}
?>
