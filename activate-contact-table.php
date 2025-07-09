<?php
/**
 * Activation script for Prospergenics Contact Form Database Table
 * 
 * This script creates the database table for storing contact form submissions.
 * Run this script once to set up the database table.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress context, try to include WordPress
    $wp_load = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load)) {
        require_once($wp_load);
    } else {
        die('WordPress not found. Please run this script from within WordPress.');
    }
}

// Check if user has admin privileges
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Create the table
function prospergenics_create_contact_table_manual() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'prospergenics_contact_submissions';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        message text NOT NULL,
        ip_address varchar(45),
        user_agent text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);
    
    return $result;
}

// Check if table exists
function prospergenics_check_table_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'prospergenics_contact_submissions';
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
}

// Run the activation
if (isset($_POST['activate_contact_table'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'activate_contact_table')) {
        wp_die('Security check failed.');
    }
    
    $result = prospergenics_create_contact_table_manual();
    $table_exists = prospergenics_check_table_exists();
    
    if ($table_exists) {
        $message = '<div class="notice notice-success"><p>Contact form submissions table created successfully!</p></div>';
    } else {
        $message = '<div class="notice notice-error"><p>Error creating table. Please check your database permissions.</p></div>';
    }
} else {
    $table_exists = prospergenics_check_table_exists();
    if ($table_exists) {
        $message = '<div class="notice notice-info"><p>Contact form submissions table already exists.</p></div>';
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Prospergenics Contact Form Table Activation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .container { max-width: 600px; margin: 0 auto; }
        .notice { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .notice-success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .notice-error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .notice-info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .btn { background-color: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background-color: #005a87; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Prospergenics Contact Form Database Setup</h1>
        
        <?php if (isset($message)) echo $message; ?>
        
        <p>This script will create the database table needed to store contact form submissions.</p>
        
        <form method="post">
            <?php wp_nonce_field('activate_contact_table'); ?>
            <button type="submit" name="activate_contact_table" class="btn">
                <?php echo $table_exists ? 'Recreate Table' : 'Create Table'; ?>
            </button>
        </form>
        
        <p><strong>Note:</strong> After running this script, you can access contact form submissions in your WordPress admin under "Contact Submissions".</p>
        
        <p><a href="<?php echo admin_url(); ?>">← Back to WordPress Admin</a></p>
    </div>
</body>
</html> 