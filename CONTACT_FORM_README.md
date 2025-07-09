# Prospergenics Contact Form System

This document explains how the contact form system works in the Prospergenics WordPress theme.

## Features

- **Database Storage**: All contact form submissions are stored in a custom database table
- **Email Notifications**: Admins receive email notifications for new submissions
- **Admin Interface**: View and manage submissions from the WordPress admin panel
- **Enhanced Form**: Includes name, email, phone, subject, and message fields
- **Security**: CSRF protection and input sanitization
- **Success Feedback**: Users see a success message after form submission

## Database Table

The contact form creates a table called `{prefix}_prospergenics_contact_submissions` with the following structure:

- `id` - Auto-incrementing primary key
- `name` - User's name (required)
- `email` - User's email (required)
- `message` - User's message (required)
- `ip_address` - User's IP address
- `user_agent` - User's browser information
- `created_at` - Timestamp of submission

## Setup Instructions

### 1. Automatic Setup (Recommended)

The database table will be created automatically when:
- The theme is activated
- The website loads (if the table doesn't exist)

### 2. Manual Setup

If the automatic setup doesn't work, you can manually create the table:

1. Navigate to: `your-site.com/wp-content/themes/prospergenics-theme/activate-contact-table.php`
2. Click "Create Table" button
3. The table will be created in your database

### 3. Verify Setup

After setup, you should see a "Contact Submissions" menu item in your WordPress admin panel.

## How to Use

### For Users

1. Click the "Join the Movement" button on the homepage
2. Fill out the contact form with your information
3. Submit the form
4. You'll see a success message confirming your submission

### For Administrators

1. **View Submissions**: Go to WordPress Admin → Contact Submissions
2. **View Details**: Click "View Full Message" to see the complete message
3. **Delete Submissions**: Click "Delete" to remove submissions
4. **Email Notifications**: You'll receive email notifications for new submissions

## Form Fields

- **Name** (required): User's full name
- **Email** (required): User's email address
- **Phone** (optional): User's phone number
- **Subject** (optional): Subject of the message
- **Message** (required): User's message content

## Security Features

- **CSRF Protection**: Uses WordPress nonces to prevent cross-site request forgery
- **Input Sanitization**: All user input is properly sanitized
- **Email Validation**: Email addresses are validated before processing
- **IP Tracking**: User IP addresses are logged for security purposes

## Customization

### Modifying the Form

The contact form is located in `index.php` around line 122. You can modify the form fields, styling, or layout as needed.

### Changing Email Settings

The email notification settings are in `functions.php` in the `prospergenics_send_contact_form()` function. You can modify:
- Email recipient (currently uses admin email)
- Email subject line
- Email body format

### Styling the Form

The form uses inline styles. You can modify the styling by editing the `style` attributes in the form HTML.

## Troubleshooting

### Form Not Working

1. Check that the database table exists
2. Verify WordPress permalinks are set correctly
3. Check for JavaScript errors in browser console
4. Ensure the theme is properly activated

### No Email Notifications

1. Check your WordPress email settings
2. Verify the admin email is set correctly
3. Check if your hosting provider allows PHP mail() function
4. Consider using an SMTP plugin for better email delivery

### Database Table Issues

1. Run the manual activation script
2. Check database permissions
3. Verify WordPress database connection
4. Check for any database errors in WordPress debug log

## File Structure

```
prospergenics-theme/
├── functions.php (contact form handler)
├── index.php (contact form HTML)
├── activate-contact-table.php (manual setup script)
└── CONTACT_FORM_README.md (this file)
```

## Support

If you encounter any issues with the contact form system, please check:
1. WordPress error logs
2. Browser console for JavaScript errors
3. Database connection and permissions
4. PHP error logs

For additional support, refer to the WordPress documentation or contact your hosting provider. 