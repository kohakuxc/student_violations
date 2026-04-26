# System Setup and Deployment Guide

## Pre-Deployment Checklist

### 1. Database Migrations
Before the system can function, you must execute the three new migrations:

**PostgreSQL:**
```bash
psql -U your_user -d your_database -f config/migrations/007_notifications_system.sql
psql -U your_user -d your_database -f config/migrations/008_messaging_system.sql
psql -U your_user -d your_database -f config/migrations/009_email_queue_system.sql
```

**SQL Server (via SQLCMD):**
```bash
sqlcmd -S server_name -U username -P password -d database_name -i config/migrations/007_notifications_system.sql
sqlcmd -S server_name -U username -P password -d database_name -i config/migrations/008_messaging_system.sql
sqlcmd -S server_name -U username -P password -d database_name -i config/migrations/009_email_queue_system.sql
```

### 2. SMTP Configuration (Optional but Recommended)
Configure SMTP settings in the Settings page or directly in `config/system_settings.json`:

```json
{
    "smtp_enabled": true,
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 465,
    "smtp_user": "your-email@gmail.com",
    "smtp_password": "your-app-password",
    "email_use_queue": true
}
```

### 3. Cron Job Setup (For Email Queue Processing)
Add to your crontab to process email queue every 5 minutes:

```bash
*/5 * * * * /usr/bin/php /full/path/to/student_violations/email_queue_processor.php >> /full/path/to/logs/email_queue.log 2>&1
```

**For Windows (Task Scheduler):**
1. Create a new Basic Task
2. Set trigger to "Repeat: every 5 minutes"
3. Set action to: `C:\xampp\php\php.exe C:\xampp\htdocs\student_violations\email_queue_processor.php`
4. Optional: redirect output to log file

## Feature Overview

### 1. Notification System
- Real-time notifications with 30-second polling
- Read/unread status tracking
- Admin-only access to settings change notifications
- Appointment status change notifications (approved, rejected, rescheduled, completed)
- Relative time formatting

### 2. Messaging System
- Student ↔ Officer conversations
- Students can only message their assigned officer
- Officers can message any student in their appointments
- Unread count tracking
- Conversation archiving

### 3. Email Queue System
- Automatic email sending via SMTP or PHP mail()
- Failed email retry (up to 3 attempts)
- Delivery audit logging
- Cron-based processing

## API Endpoints Reference

### Notifications
- `GET api/notifications.php?action=getRecent&limit=12` - Fetch recent notifications
- `POST api/notifications.php?action=markAsRead` - Mark single notification as read
- `POST api/notifications.php?action=markAllAsRead` - Mark all notifications as read
- `GET api/notifications.php?action=getUnreadCount` - Get unread count for badge

### Messaging
- `GET api/messages.php?action=getConversations` - List user conversations
- `GET api/messages.php?action=getConversation&conversation_id=X` - Get conversation messages
- `POST api/messages.php?action=sendMessage` - Send a message
- `POST api/messages.php?action=markConversationAsRead` - Mark all messages as read
- `GET api/messages.php?action=getTotalUnreadCount` - Get total unread messages
- `POST api/messages.php?action=archiveConversation` - Archive a conversation

### Appointments (Enhanced)
- Approval, rejection, rescheduling, and completion now create notifications automatically
- Email sending integrated for each status change

## Troubleshooting

### Notifications not appearing
1. Verify database migrations were executed successfully
2. Check that NotificationModel.php is in `model/` directory
3. Ensure `api/notifications.php` is accessible
4. Check browser console for JavaScript errors

### Emails not sending
1. Verify SMTP settings are configured correctly
2. Check `email_queue_processor.php` is being executed (check logs)
3. Review email_delivery_log table for error messages
4. Test with `php email_queue_processor.php` from command line

### Messaging not working
1. Verify 008_messaging_system.sql migration was executed
2. Check MessageModel.php exists in `model/` directory
3. Ensure `api/messages.php` is accessible
4. Verify session contains `student_id` or `officer_id`

## Performance Considerations

### Notification Polling
- 30-second intervals provides good real-time feel while minimizing server load
- Only polls when notification modal is open
- Consider increasing interval to 60 seconds if server load is high

### Email Queue
- Processing via cron every 5 minutes (adjust as needed)
- Retry delay: Failed emails retry after 1, 5, and 30 minutes
- Monitor `email_delivery_log` table for delivery issues

### Database Indexing
All migrations include optimized indexes on:
- `notifications(officer_id, is_read, created_at DESC)`
- `messages(conversation_id, created_at DESC)`
- `email_queue(status, attempt_count, next_retry_at)`

## Data Cleanup

To clean up old/completed data (optional):

```sql
-- Archive old completed appointments (older than 6 months)
DELETE FROM notifications WHERE created_at < NOW() - INTERVAL '6 months';

-- Archive old messages (older than 1 year)
DELETE FROM messages WHERE created_at < NOW() - INTERVAL '1 year';

-- Archive old emails (after 30 days delivery logged)
DELETE FROM email_delivery_log WHERE created_at < NOW() - INTERVAL '30 days';
```

## Security Notes

- SMTP passwords stored in `system_settings.json` - ensure proper file permissions (644 or read-only)
- Use environment variables instead of JSON for sensitive credentials in production
- All API endpoints require session authentication
- Email addresses validated before queueing
- Database queries use prepared statements to prevent SQL injection

## Rollback Instructions

If you need to rollback these changes:

```sql
-- Drop new tables (execute in reverse order)
DROP TABLE IF EXISTS email_delivery_log CASCADE;
DROP TABLE IF EXISTS email_queue CASCADE;
DROP TABLE IF EXISTS conversation_participants CASCADE;
DROP TABLE IF EXISTS messages CASCADE;
DROP TABLE IF EXISTS conversations CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
```

Then remove/revert these files:
- Delete: `api/notifications.php`
- Delete: `api/messages.php`
- Delete: `model/NotificationModel.php`
- Delete: `model/MessageModel.php`
- Delete: `helper/SmtpEmailHelper.php`
- Delete: `email_queue_processor.php`
- Revert: `controller/SettingsController.php`
- Revert: `model/AppointmentModel.php`
- Revert: `api/appointments.php`
- Revert: `config/system_settings.php`
- Revert: `view/partials/layout_top.php`

## Support

For issues or questions:
1. Check system error logs: `/logs/` directory
2. Review email queue log: `/logs/email_queue.log`
3. Check browser console for JavaScript errors
4. Verify all files are in correct directories

## Version History

- v1.0 - Initial implementation
  - Database-backed notifications
  - Real-time polling (30 seconds)
  - Messaging system with role-based access
  - Email queue with retry logic
  - Integration with appointment status changes
