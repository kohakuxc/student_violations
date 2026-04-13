<?php
class EmailNotification {
    private $from_email = 'noreply@fairview-violations.edu.ph';
    private $from_name = 'STI Student Violations System';

    // Send appointment created email
    public function sendAppointmentCreatedEmail($student, $appointment) {
        try {
            $to = $student['email'] ?? null;
            if (!$to) return false;

            $subject = 'Appointment Request Received - STI Violations System';
            
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                    .header { background-color: #003366; color: white; padding: 20px; text-align: center; }
                    .content { background-color: white; padding: 20px; margin-top: 10px; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                    .info-row { margin-bottom: 10px; }
                    .label { font-weight: bold; color: #003366; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Appointment Request Confirmation</h2>
                    </div>
                    <div class='content'>
                        <p>Dear " . htmlspecialchars($student['first_name']) . ",</p>
                        <p>Your appointment request has been successfully submitted. The officer will review it and contact you shortly.</p>
                        
                        <h3>Appointment Details:</h3>
                        <div class='info-row'>
                            <span class='label'>Category:</span> " . htmlspecialchars($appointment['category_name']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Type:</span> " . htmlspecialchars($appointment['subcategory_name']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Scheduled Date:</span> " . date('F d, Y h:i A', strtotime($appointment['scheduled_date'])) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Status:</span> <span style='color: #ff9900;'>Pending Review</span>
                        </div>
                        
                        <p style='margin-top: 20px;'>You will receive another email once the officer has reviewed your request.</p>
                        <p>If you have any questions, please contact the office.</p>
                        
                        <p>Best regards,<br>STI Student Violations System</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 STI Education Services Group, Inc. All Rights Reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            return $this->sendEmail($to, $subject, $body);
        } catch (Exception $e) {
            error_log("Error sending appointment created email: " . $e->getMessage());
            return false;
        }
    }

    // Send appointment approved email
    public function sendAppointmentApprovedEmail($appointment) {
        try {
            $to = $appointment['email'] ?? null;
            if (!$to) return false;

            $subject = 'Your Appointment Has Been Approved - STI Violations System';
            
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                    .header { background-color: #003366; color: white; padding: 20px; text-align: center; }
                    .content { background-color: white; padding: 20px; margin-top: 10px; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                    .info-row { margin-bottom: 10px; }
                    .label { font-weight: bold; color: #003366; }
                    .status { color: #28a745; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Appointment Approved</h2>
                    </div>
                    <div class='content'>
                        <p>Dear " . htmlspecialchars($appointment['first_name']) . ",</p>
                        <p>Good news! Your appointment request has been <span class='status'>APPROVED</span>.</p>
                        
                        <h3>Appointment Details:</h3>
                        <div class='info-row'>
                            <span class='label'>Category:</span> " . htmlspecialchars($appointment['category_name']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Type:</span> " . htmlspecialchars($appointment['subcategory_name']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Scheduled Date & Time:</span> " . date('F d, Y h:i A', strtotime($appointment['scheduled_date'])) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Officer:</span> " . htmlspecialchars($appointment['officer_name']) . "
                        </div>
                        
                        <p style='margin-top: 20px;'>Please arrive on time. If you need to reschedule, please contact the office as soon as possible.</p>
                        
                        <p>Best regards,<br>STI Student Violations System</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 STI Education Services Group, Inc. All Rights Reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            return $this->sendEmail($to, $subject, $body);
        } catch (Exception $e) {
            error_log("Error sending appointment approved email: " . $e->getMessage());
            return false;
        }
    }

    // Send appointment rejected email
    public function sendAppointmentRejectedEmail($appointment, $reason) {
        try {
            $to = $appointment['email'] ?? null;
            if (!$to) return false;

            $subject = 'Your Appointment Request - Action Required - STI Violations System';
            
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                    .header { background-color: #003366; color: white; padding: 20px; text-align: center; }
                    .content { background-color: white; padding: 20px; margin-top: 10px; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                    .info-row { margin-bottom: 10px; }
                    .label { font-weight: bold; color: #003366; }
                    .reason-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Appointment Request Update</h2>
                    </div>
                    <div class='content'>
                        <p>Dear " . htmlspecialchars($appointment['first_name']) . ",</p>
                        <p>Your appointment request requires some adjustments. Please review the feedback below and resubmit your request.</p>
                        
                        <h3>Reason for Return:</h3>
                        <div class='reason-box'>
                            " . htmlspecialchars($reason) . "
                        </div>
                        
                        <h3>Original Request Details:</h3>
                        <div class='info-row'>
                            <span class='label'>Category:</span> " . htmlspecialchars($appointment['category_name']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Type:</span> " . htmlspecialchars($appointment['subcategory_name']) . "
                        </div>
                        
                        <p style='margin-top: 20px;'>Please log in to the system and resubmit your appointment with the requested changes.</p>
                        
                        <p>Best regards,<br>STI Student Violations System</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 STI Education Services Group, Inc. All Rights Reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            return $this->sendEmail($to, $subject, $body);
        } catch (Exception $e) {
            error_log("Error sending appointment rejected email: " . $e->getMessage());
            return false;
        }
    }

    // Send appointment rescheduled email
    public function sendAppointmentRescheduledEmail($appointment, $new_date) {
        try {
            $to = $appointment['email'] ?? null;
            if (!$to) return false;

            $subject = 'Your Appointment Has Been Rescheduled - STI Violations System';
            
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                    .header { background-color: #003366; color: white; padding: 20px; text-align: center; }
                    .content { background-color: white; padding: 20px; margin-top: 10px; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                    .info-row { margin-bottom: 10px; }
                    .label { font-weight: bold; color: #003366; }
                    .status { color: #17a2b8; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Appointment Rescheduled</h2>
                    </div>
                    <div class='content'>
                        <p>Dear " . htmlspecialchars($appointment['first_name']) . ",</p>
                        <p>Your appointment has been <span class='status'>RESCHEDULED</span> to a new date and time.</p>
                        
                        <h3>Updated Appointment Details:</h3>
                        <div class='info-row'>
                            <span class='label'>Category:</span> " . htmlspecialchars($appointment['category_name']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Type:</span> " . htmlspecialchars($appointment['subcategory_name']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>New Scheduled Date & Time:</span> " . date('F d, Y h:i A', strtotime($new_date)) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Officer:</span> " . htmlspecialchars($appointment['officer_name']) . "
                        </div>
                        
                        <p style='margin-top: 20px;'>Please mark your calendar with the new appointment date and time.</p>
                        
                        <p>Best regards,<br>STI Student Violations System</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 STI Education Services Group, Inc. All Rights Reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            return $this->sendEmail($to, $subject, $body);
        } catch (Exception $e) {
            error_log("Error sending appointment rescheduled email: " . $e->getMessage());
            return false;
        }
    }

    // Send appointment completed email
    public function sendAppointmentCompletedEmail($appointment) {
        try {
            $to = $appointment['email'] ?? null;
            if (!$to) return false;

            $subject = 'Your Appointment Has Been Completed - STI Violations System';
            
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                    .header { background-color: #003366; color: white; padding: 20px; text-align: center; }
                    .content { background-color: white; padding: 20px; margin-top: 10px; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                    .info-row { margin-bottom: 10px; }
                    .label { font-weight: bold; color: #003366; }
                    .status { color: #28a745; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Appointment Completed</h2>
                    </div>
                    <div class='content'>
                        <p>Dear " . htmlspecialchars($appointment['first_name']) . ",</p>
                        <p>Your appointment has been <span class='status'>COMPLETED</span>.</p>
                        
                        <h3>Appointment Details:</h3>
                        <div class='info-row'>
                            <span class='label'>Category:</span> " . htmlspecialchars($appointment['category_name']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Type:</span> " . htmlspecialchars($appointment['subcategory_name']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Completed Date:</span> " . date('F d, Y h:i A', strtotime($appointment['updated_at'])) . "
                        </div>
                        
                        <p style='margin-top: 20px;'>Thank you for meeting with us. If you have any further concerns, please don't hesitate to reach out.</p>
                        
                        <p>Best regards,<br>STI Student Violations System</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 STI Education Services Group, Inc. All Rights Reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            return $this->sendEmail($to, $subject, $body);
        } catch (Exception $e) {
            error_log("Error sending appointment completed email: " . $e->getMessage());
            return false;
        }
    }

    // Send appointment cancelled email
    public function sendAppointmentCancelledEmail($appointment, $reason) {
        try {
            $to = $appointment['email'] ?? null;
            if (!$to) return false;

            $subject = 'Your Appointment Has Been Cancelled - STI Violations System';
            
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                    .header { background-color: #003366; color: white; padding: 20px; text-align: center; }
                    .content { background-color: white; padding: 20px; margin-top: 10px; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                    .info-row { margin-bottom: 10px; }
                    .label { font-weight: bold; color: #003366; }
                    .status { color: #6c757d; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Appointment Cancelled</h2>
                    </div>
                    <div class='content'>
                        <p>Dear " . htmlspecialchars($appointment['first_name']) . ",</p>
                        <p>Your appointment has been <span class='status'>CANCELLED</span>.</p>
                        
                        <h3>Cancellation Details:</h3>
                        <div class='info-row'>
                            <span class='label'>Reason:</span> " . htmlspecialchars($reason) . "
                        </div>
                        
                        <p style='margin-top: 20px;'>If you need to schedule another appointment, you can submit a new request through the system.</p>
                        
                        <p>Best regards,<br>STI Student Violations System</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 STI Education Services Group, Inc. All Rights Reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            return $this->sendEmail($to, $subject, $body);
        } catch (Exception $e) {
            error_log("Error sending appointment cancelled email: " . $e->getMessage());
            return false;
        }
    }

    // Generic email sending function
    private function sendEmail($to, $subject, $body) {
        try {
            // Set headers
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . $this->from_name . " <" . $this->from_email . ">\r\n";
            $headers .= "Reply-To: " . $this->from_email . "\r\n";
            
            // Send email
            $result = mail($to, $subject, $body, $headers);
            
            if (!$result) {
                throw new Exception('Failed to send email');
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }
}
?>