<?php

/**
 * Plugin Name: Stars SMTP Mailer
 * Plugin URI: https://staging.myriadsolutionz.com/stars-smtp-mailer/
 * Description: Stars SMTP Mailer Plugin for sending emails through SMTP.
 * Version: 2.2.1
 * Author: Myriad Solutionz
 * Author URI: https://staging.myriadsolutionz.com/
 * Text Domain: stars-smtp-mailer
 * Domain Path: /languages
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */


/**
 * @author Myriad Solutionz
 * @copyright Myriad Solutionz, 2019, All Rights Reserved
 * This code is released under the GPL licence version 3 or later, available here
 * http://www.gnu.org/licenses/gpl.txt
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb, $stars_smtpm_data, $isAdmin;

//define constant
$stars_smtpm_plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
stars_smtpm_define('STARS_SMTPM_PLUGIN_VERSION', $stars_smtpm_plugin_data['Version']);
stars_smtpm_define('STARS_SMTPM_PLUGIN_URL', plugins_url());
stars_smtpm_define('STARS_SMTPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
stars_smtpm_define('STARS_SMTPM_SMTP_SETTINGS', $wpdb->prefix . 'stars_smtp_settings');
stars_smtpm_define('STARS_SMTPM_EMAILS_LOG', $wpdb->prefix . 'stars_emails_log');
stars_smtpm_define('STARS_SMTPM_MAILER_TABLE', $wpdb->prefix . 'stars_smtp_mailer');
stars_smtpm_define('STARS_SMTPM_AJAX_LOADER', STARS_SMTPM_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/assets/images/ajax-small-loader.gif');
stars_smtpm_define('STARS_SMTPM_PRO_LOGO', STARS_SMTPM_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/assets/images/smtp-pro-version.svg');
stars_smtpm_define('STARS_SMTPM_MYRIAD_LOGO', STARS_SMTPM_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/assets/images/ms.svg');

//Configuration files
include 'action/stars_function.php';

/**Create tables */
register_activation_hook(__FILE__, 'stars_smtpm_create_table');
function stars_smtpm_create_table()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    // Table 1: SMTP Settings
    $table_smtp_settings = esc_sql(STARS_SMTPM_SMTP_SETTINGS);
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_smtp_settings}'") !== $table_smtp_settings) {
        $sql1 = "CREATE TABLE {$table_smtp_settings} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            from_name varchar(150) NOT NULL,
            from_email varchar(255) NOT NULL,
            reply_to varchar(255) NOT NULL,
            cc varchar(255) DEFAULT NULL,
            bcc varchar(255) DEFAULT NULL,
            add_header varchar(1000) DEFAULT NULL,
            smtp_host varchar(50) NOT NULL,
            smtp_port varchar(50) NOT NULL,
            encryption varchar(50) NOT NULL,
            auth varchar(255) NOT NULL,
            username varchar(255) NOT NULL,
            pass varchar(255) NOT NULL,
            smtp_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status int(11) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql1);
    }

    // Table 2: Emails Log
    $table_emails_log = esc_sql(STARS_SMTPM_EMAILS_LOG);
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_emails_log}'") !== $table_emails_log) {
        $sql2 = "CREATE TABLE {$table_emails_log} (
            log_id int(11) NOT NULL AUTO_INCREMENT,
            from_name varchar(255) NOT NULL,
            from_email varchar(255) NOT NULL,
            reply_to varchar(255) NOT NULL,
            email_id varchar(255) NOT NULL,
            cc varchar(255) NOT NULL,
            bcc varchar(255) NOT NULL,
            sub text NOT NULL,
            mail_body text NOT NULL,
            status varchar(100) NOT NULL,
            response varchar(100) NOT NULL,
            debug_op text NOT NULL,
            mail_type varchar(10) NOT NULL,
            mail_date timestamp NOT NULL,
            PRIMARY KEY (log_id)
        ) $charset_collate;";
        dbDelta($sql2);
    }

    // Table 3: SMTP Mailer
    $table_mailer = esc_sql(STARS_SMTPM_MAILER_TABLE);
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_mailer}'") !== $table_mailer) {
        $sql3 = "CREATE TABLE {$table_mailer} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        subscribed_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email (email)
        ) $charset_collate;";
        dbDelta($sql3);
    }
}


/**STARS SMTP init */
add_action('init', 'STARS_SMTPM_init');
function STARS_SMTPM_init()
{
    if (!session_id()) {
        add_action('init', 'STARS_SMTPM_session', 1);
    }

    global $current_user, $isAdmin, $wpdb;

    wp_get_current_user();
    $isAdmin = current_user_can('administrator');

    $table_name = esc_sql(STARS_SMTPM_EMAILS_LOG);
    $smtp_check = $wpdb->get_var(
        $wpdb->prepare(
            "SHOW COLUMNS FROM `$table_name` LIKE %s",
            'attachment'
        )
    );

    if (!$smtp_check) {
        $wpdb->query("ALTER TABLE `$table_name` ADD `attachment` TEXT NOT NULL AFTER `mail_date`");
    }

    // Fix #1 & #2: one-time migration to re-encrypt all stored passwords with the new scheme
    stars_smtpm_migrate_passwords();

    add_action('all_admin_notices', 'stars_smtpm_activation_notice');

    if ((isset($_GET['action']) && $_GET['action'] === 'delete') || (isset($_POST['action']) && $_POST['action'] === 'delete')) {
        add_action('all_admin_notices', 'stars_smtpm_delete_success_note');
    }

    require_once ABSPATH . WPINC . '/class-phpmailer.php';
    require_once ABSPATH . WPINC . '/class-smtp.php';

    add_action('admin_menu', 'stars_smtpm_admin_menu');
    add_action('admin_enqueue_scripts', 'stars_smtpm_mailer_assets');
}

/**
 * One-time migration: re-encrypt any passwords still using the old hardcoded-key + zero-IV scheme.
 * Runs once after upgrade; sets a flag so it never re-runs.
 */
function stars_smtpm_migrate_passwords()
{
    if (get_option('stars_smtpm_passwords_migrated_v2')) {
        return;
    }

    global $wpdb;
    $table = STARS_SMTPM_SMTP_SETTINGS;

    $accounts = $wpdb->get_results("SELECT id, pass FROM {$table}", ARRAY_A);
    if (empty($accounts)) {
        update_option('stars_smtpm_passwords_migrated_v2', 1);
        return;
    }

    foreach ($accounts as $account) {
        $stored = $account['pass'];
        // Already migrated passwords start with 'v2:'
        if (strncmp($stored, 'v2:', 3) === 0) {
            continue;
        }
        // Decrypt with legacy scheme, re-encrypt with new scheme
        $plaintext = stars_smtpm_pass_enc_dec($stored, 'dec');
        if ($plaintext !== '') {
            $new_encrypted = stars_smtpm_pass_enc_dec($plaintext, 'enc');
            $wpdb->update($table, array('pass' => $new_encrypted), array('id' => $account['id']));
        }
    }

    update_option('stars_smtpm_passwords_migrated_v2', 1);
}


function STARS_SMTPM_session()
{
    session_start();
}

function stars_smtpm_define($name, $value)
{
    if (!defined($name)) {
        define($name, $value);
    }
}

function stars_smtpm_delete_success_note()
{
    echo '<div id="message" class="updated notice is-dismissible">';
    echo '<p>' . esc_html__('Data Deleted Successfully.', 'stars-smtp-mailer') . '</p>';
    echo '</div>';
}

function stars_smtpm_activation_notice()
{
    if (isset($_GET['page']) && $_GET['page'] == "stars-smtpm-test-mail" && isset($_GET['id']) && sanitize_key($_GET['id']) != "")
        $hide_notice = 1;
    if (!isset($hide_notice)) {
        $stars_activated_account = stars_smtpm_get_smtp_account();
        if (!count($stars_activated_account)) {
            echo '<div id="message" class="notice notice-warning is-dismissible">';
            printf(
                '<p>%s</p>',
                sprintf(
                    wp_kses(
                        // translators: %s: URL to the Stars SMTP Mailer accounts page.
                        __('No SMTP Accounts activated. Email wont be sent via <strong>Stars SMTP Mailer</strong>. Please add and/or activate account <a href="%s">here</a>', 'stars-smtp-mailer'),
                        [
                            'strong' => [],
                            'a' => [
                                'href' => [],
                            ],
                        ]
                    ),
                    esc_url(admin_url('admin.php?page=stars-smtpm-accounts'))
                )
            );
            echo '</div>';
        }
    }
}

function stars_smtpm_admin_menu()
{
    // Fix #10: use 'manage_options' instead of 0 so only admins can access these pages
    $emaillog_menu    = add_menu_page('Stars SMTP Mailer', 'Stars SMTP Mailer', 'manage_options', 'stars-smtpm-email-log', 'stars_smtpm_email_log', 'dashicons-email-alt');
    $emaillog_menu    = add_submenu_page('stars-smtpm-email-log', 'Email Log',       'Email Log',       'manage_options', 'stars-smtpm-email-log',   'stars_smtpm_email_log');
    $smtpaddAcc_menu  = add_submenu_page('stars-smtpm-email-log', 'Add New Account', 'Add New Account', 'manage_options', 'stars-smtpm-new-account', 'stars_smtpm_new_account');
    $smtpaccount_menu = add_submenu_page('stars-smtpm-email-log', 'SMTP Accounts',   'SMTP Accounts',   'manage_options', 'stars-smtpm-accounts',    'stars_smtpm_smtp_account');
    $smtpTest_menu    = add_submenu_page('stars-smtpm-email-log', 'Test Email',       'Test Email',      'manage_options', 'stars-smtpm-test-mail',   'stars_smtpm_mail_test');

    add_action("load-$smtpaccount_menu", 'stars_smtpm_accounts_add_option'); //To add screen option
    add_action("load-$emaillog_menu", 'stars_smtpm_email_log_add_option'); //To add screen option
}

//Screen option for Email Logs
function stars_smtpm_email_log_add_option()
{
    $option = 'per_page';
    $args = array(
        'label' => 'Per Page',
        'default' => 10,
        'option' => 'email_log_per_page'
    );
    add_screen_option($option, $args);
}
add_filter('set-screen-option', 'stars_smtpm_email_log_set_option', 10, 3);
function stars_smtpm_email_log_set_option($status, $option, $value)
{
    if ('email_log_per_page' == $option)
        return $value;
    return $status;
}

//Screen option for smtp account list
function stars_smtpm_accounts_add_option()
{
    $option = 'per_page';
    $args = array(
        'label' => 'Per Page',
        'default' => 10,
        'option' => 'smtp_account_per_page'
    );
    add_screen_option($option, $args);
}
add_filter('set-screen-option', 'stars_smtpm_account_set_option', 10, 3);
function stars_smtpm_account_set_option($status, $option, $value)
{
    if ('smtp_account_per_page' == $option)
        return $value;
    return $status;
}

function stars_smtpm_mail_test()
{
    include_once("include/stars-test-email.php");
}
function stars_smtpm_new_account()
{
    include_once('include/stars-add-new-account.php');
}
function stars_smtpm_smtp_account()
{
    include_once('include/stars-smtp-accounts-list.php');
}
function stars_smtpm_email_log()
{
    include_once('include/stars-email-logs.php');
}

/**
 * Process the Add/Edit account form on admin_init — before any output —
 * so wp_redirect() can fire without "headers already sent" warnings.
 */
add_action('admin_init', 'stars_smtpm_process_account_form');
function stars_smtpm_process_account_form()
{
    // Only run on our page
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'stars-smtpm-new-account' ) {
        return;
    }
    if ( ! isset( $_POST['form-action'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'stars_smtpm-add_edit_account' ) ) {
        // Let the include file show the nonce error
        return;
    }

    global $wpdb;

    $edit_id    = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
    $from_name  = sanitize_text_field( $_POST['from_name'] );
    $from_email = sanitize_email( $_POST['from_email'] );
    $reply_to   = sanitize_email( $_POST['reply_to'] );
    $cc         = sanitize_email( $_POST['cc'] );
    $bcc        = sanitize_email( $_POST['bcc'] );
    $add_header = sanitize_text_field( $_POST['add_header'] );
    $smtp_host  = sanitize_text_field( $_POST['smtp_host'] );
    $encryption = sanitize_text_field( $_POST['encryption'] );
    $smtp_port  = absint( $_POST['smtp_port'] );
    $auth       = absint( $_POST['auth'] );
    $username   = sanitize_text_field( $_POST['username'] );
    $pass       = $_POST['pass'];

    if ( $from_name === '' || $from_email === '' || $smtp_host === '' || $smtp_port === 0 || $username === '' ) {
        // Missing required fields — let the include handle the error display
        return;
    }

    $smtp_date = gmdate( 'Y-m-d H:i:s', time() );

    $getdata = array(
        'from_name'  => $from_name,
        'from_email' => $from_email,
        'reply_to'   => $reply_to,
        'cc'         => $cc,
        'bcc'        => $bcc,
        'add_header' => $add_header,
        'smtp_host'  => $smtp_host,
        'encryption' => $encryption,
        'smtp_port'  => $smtp_port,
        'auth'       => $auth,
        'username'   => $username,
        'pass'       => $pass,
        'smtp_date'  => $smtp_date,
    );

    if ( isset( $_POST['add_new'] ) ) {
        // New account
        if ( $getdata['pass'] !== '' ) {
            $getdata['pass'] = stars_smtpm_pass_enc_dec( $getdata['pass'], 'enc' );
        }
        $data = stars_smtpm_config_insert_data( $getdata );
        if ( $data ) {
            $_SESSION['acc_msg'] = sprintf(
                /* translators: %s is a link to the test email page. */
                esc_html__( 'Account Successfully Saved. %s ', 'stars-smtp-mailer' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=stars-smtpm-test-mail&id=' . $data ) ) . '" class="button button-primary">' . esc_html__( 'Send Test Mail', 'stars-smtp-mailer' ) . '</a>'
            );
        } else {
            $_SESSION['acc_err'] = esc_html__( 'Oops !!! You can not Add more than 3 accounts.', 'stars-smtp-mailer' );
        }
        wp_redirect( admin_url( 'admin.php?page=stars-smtpm-accounts' ) );
        exit;

    } elseif ( isset( $_POST['update'] ) ) {
        // Edit account
        $existing = stars_smtpm_get_account_data( $edit_id );
        if ( $getdata['pass'] === '' ) {
            $getdata['pass'] = $existing['pass'];
        } else {
            $getdata['pass'] = stars_smtpm_pass_enc_dec( $getdata['pass'], 'enc' );
        }
        $result = stars_smtpm_config_update_data( $getdata, $edit_id );
        if ( $result == 1 ) {
            $_SESSION['acc_msg'] = esc_html__( 'Account Successfully Edited', 'stars-smtp-mailer' );
        } else {
            $_SESSION['acc_err'] = esc_html__( 'Something went wrong please try again !!', 'stars-smtp-mailer' );
        }
        wp_redirect( admin_url( 'admin.php?page=stars-smtpm-new-account&action=edit&id=' . $edit_id ) );
        exit;
    }
}

// Stars smtp mailer{Js& Css}
function stars_smtpm_mailer_assets($hook)
{

    $style = STARS_SMTPM_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/assets/css/stars_style.css';
    wp_enqueue_style('stars_style', $style);

    $custom = STARS_SMTPM_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/assets/js/stars_smtpm_custom.js';
    wp_enqueue_script('stars_smtpm_custom', $custom);
    wp_localize_script('stars_smtpm_custom', 'starsSmtpNotify', array(
        'ajax_url'            => admin_url('admin-ajax.php'),
        'nonce'               => wp_create_nonce('stars_smtp_notify_nonce'),
        'status_change_nonce' => wp_create_nonce('stars_smtpm_change_status_nonce'),
        'check_host_nonce'    => wp_create_nonce('stars_smtpm_check_host_nonce'),
        'check_user_nonce'    => wp_create_nonce('stars_smtpm_check_user_nonce'),
    ));


    if ($hook == "stars-smtp-mailer_page_stars-smtpm-test-mail" || $hook == "stars-smtp-mailer_page_stars-smtpm-new-account")
        wp_enqueue_script("stars_jquery_validation", STARS_SMTPM_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/assets/js/jquery.validate.js');

    if ($hook == "toplevel_page_stars-smtpm-email-log" || $hook == "stars-smtp-mailer_page_stars-smtpm-accounts") {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-tooltip');
        wp_enqueue_script('jquery-ui-dialog');

        wp_enqueue_style("stars_jquery_ui_css", STARS_SMTPM_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/assets/css/jquery-ui.css');

        wp_enqueue_style('thickbox');
        wp_enqueue_script('thickbox');
    }
}

/**SMTP accounts - activate / deactivate. */
add_action('wp_ajax_stars_smtpm_change_status', 'stars_smtpm_change_status');
function stars_smtpm_change_status()
{
    // Fix #4: capability check
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions', 403);
    }

    // Fix #5: verify CSRF nonce
    check_ajax_referer('stars_smtpm_change_status_nonce', 'nonce');

    if (!isset($_POST['id'], $_POST['status'])) {
        wp_send_json_error('Missing parameters');
    }

    global $wpdb;

    $table_name = esc_sql(STARS_SMTPM_SMTP_SETTINGS);

    $id     = intval($_POST['id']);
    $status = intval($_POST['status']);

    $wpdb->query("UPDATE `$table_name` SET `status` = 0");

    if ($status === 1) {
        $wpdb->update(
            $table_name,
            array('status' => 1),
            array('id'     => $id),
            array('%d'),
            array('%d')
        );
    }

    wp_send_json_success('Status updated');
}

/**stars dashboard widget */
add_action('wp_dashboard_setup', 'stars_smtpm_add_stars_dashboard_widget');
function stars_smtpm_add_stars_dashboard_widget()
{
    wp_add_dashboard_widget('stars_smtpm_dashboard_widget', 'Stars SMTP Mailer', 'stars_smtpm_dashboard_widget');
}
function stars_smtpm_dashboard_widget()
{
    global $wpdb;

    $email_logs = $wpdb->get_results("SELECT mail_date, status FROM " . STARS_SMTPM_EMAILS_LOG, ARRAY_A);
    $stats = array("today_emails" => 0, "unsent" => 0, "sent" => 0);

    $today = gmdate("Y-m-d");

    foreach ($email_logs as $el_data) {
        if (gmdate("Y-m-d", strtotime($el_data['mail_date'])) === $today)
            $stats['today_emails']++;

        if ($el_data['status'] === "Sent")
            $stats['sent']++;
        else if ($el_data['status'] === "Unsent")
            $stats['unsent']++;
    }

    $active_account = $acc_id = "";
    $stars_active_account = stars_smtpm_get_smtp_account();

    if ($stars_active_account) {
        $active_account = esc_html($stars_active_account['from_email']);
        $acc_id = '<a class="button-link community-events-toggle-location" title="Manage Account" aria-expanded="false" aria-hidden="false" href="' . esc_url(admin_url("/admin.php?page=stars-smtpm-new-account&action=edit&id=" . intval($stars_active_account['id']))) . '"><span class="dashicons dashicons-edit"></span></a>';
    }

    $stars_statistics  = '<table border="1" style="border-collapse:collapse;width:100%;" cellpadding="7">';
    $stars_statistics .= '<tr><td>' . esc_html__('Active SMTP Account', 'stars-smtp-mailer') . ' : </td><td>' . ($active_account !== '' || $acc_id !== '' ? esc_html($active_account) . ' ' . $acc_id : '-') . '</td></tr>';
    $stars_statistics .= '<tr><td>' . esc_html__("Today's Emails", 'stars-smtp-mailer') . ' : </td><td>' . intval($stats['today_emails']) . '</td></tr>';
    $stars_statistics .= '<tr><td>' . esc_html__('Total Sent Emails', 'stars-smtp-mailer') . ' : </td><td>' . intval($stats['sent']) . '</td></tr>';
    $stars_statistics .= '<tr><td>' . esc_html__('Total Unsent Emails', 'stars-smtp-mailer') . ' : </td><td>' . intval($stats['unsent']) . '</td></tr>';
    $stars_statistics .= '</table>';

    // $acc_id contains a safe, pre-built anchor with esc_url/intval — output is intentional HTML
    echo $stars_statistics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}



function stars_smtp_mailer_load_textdomain()
{
    $locale = get_locale();
    $textdomain = 'stars-smtp-mailer';
    $plugin_dir = plugin_dir_path(__FILE__);


    $mofile = $plugin_dir . 'languages/' . $textdomain . '-' . $locale . '.mo';

    if (file_exists($mofile)) {
        load_textdomain($textdomain, $mofile);
    }
}
add_action('plugins_loaded', 'stars_smtp_mailer_load_textdomain');



add_action('wp_ajax_stars_smtp_save_mailer_email', 'stars_smtp_save_mailer_email');
// Fix #8: removed wp_ajax_nopriv — restrict to logged-in users only to prevent unauthenticated spam
// add_action('wp_ajax_nopriv_stars_smtp_save_mailer_email', 'stars_smtp_save_mailer_email');

function stars_smtp_save_mailer_email()
{
    check_ajax_referer('stars_smtp_notify_nonce', 'nonce');

    // Fix #8: require login
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('Authentication required.', 'stars-smtp-mailer')]);
    }

    // Fix #8: simple rate limiting — one submission per user per hour
    $user_id       = get_current_user_id();
    $rate_key      = 'stars_smtp_notify_rate_' . $user_id;
    $rate_limit    = get_transient($rate_key);
    if ($rate_limit) {
        wp_send_json_error(['message' => __('Please wait before subscribing again.', 'stars-smtp-mailer')]);
    }
    set_transient($rate_key, 1, HOUR_IN_SECONDS);

    $email = isset($_POST['email']) ? sanitize_email(trim($_POST['email'])) : '';

    if (!is_email($email)) {
        wp_send_json_error(['message' => __('Invalid email.', 'stars-smtp-mailer')]);
    }

    global $wpdb;
    $table = esc_sql(STARS_SMTPM_MAILER_TABLE);

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));
    if ($exists) {
        wp_send_json_success(['message' => __('Already subscribed.', 'stars-smtp-mailer')]);
    }

    $inserted = $wpdb->insert($table, ['email' => $email]);

    if ($inserted) {
        wp_send_json_success(['message' => __('Successfully subscribed.', 'stars-smtp-mailer')]);
    } else {
        wp_send_json_error(['message' => __('Something went wrong.', 'stars-smtp-mailer')]);
    }
}



// 1. Add custom cron schedule
add_filter('cron_schedules', 'starssmtpmailer_add_weekly_cron_interval');
function starssmtpmailer_add_weekly_cron_interval($schedules) {
    $schedules['weekly'] = array(
        'interval' => 604800, // 7 days
        'display'  => __('Once Weekly')
    );
    return $schedules;
}

// 2. Schedule event on plugin activation
register_activation_hook(__FILE__, 'starssmtpmailer_schedule_weekly_email');
function starssmtpmailer_schedule_weekly_email() {
    if (!wp_next_scheduled('starssmtpmailer_send_weekly_email')) {
        wp_schedule_event(time(), 'weekly', 'starssmtpmailer_send_weekly_email');
    }
}

// 3. Clear scheduled event on plugin deactivation
register_deactivation_hook(__FILE__, 'starssmtpmailer_clear_weekly_email_schedule');
function starssmtpmailer_clear_weekly_email_schedule() {
    wp_clear_scheduled_hook('starssmtpmailer_send_weekly_email');
}

// 4. Email content generator with stats
function starssmtpm_generate_email_statistics_html() {
    global $wpdb;

    $email_logs = $wpdb->get_results("SELECT mail_date, status FROM " . STARS_SMTPM_EMAILS_LOG, ARRAY_A);

    $today = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $this_monday = $today->modify('monday this week')->format('Y-m-d');
    $last_monday = $today->modify('monday last week')->format('Y-m-d');
    $last_sunday = $today->modify('sunday last week')->format('Y-m-d');

    $this_week_sent = 0;
    $last_week_sent = 0;

    foreach ($email_logs as $log) {
        $log_date = gmdate("Y-m-d", strtotime($log['mail_date']));
        if ($log_date >= $this_monday) {
            if ($log['status'] === 'Sent') $this_week_sent++;
        } elseif ($log_date >= $last_monday && $log_date <= $last_sunday) {
            if ($log['status'] === 'Sent') $last_week_sent++;
        }
    }

    $percent_change = 0;
    if ($last_week_sent > 0) {
        $percent_change = (($this_week_sent - $last_week_sent) / $last_week_sent) * 100;
    } elseif ($this_week_sent > 0) {
        $percent_change = 100;
    }

    $percent_change = round($percent_change);
    $arrow = $percent_change >= 0 ? '↑' : '↓';
    $percent_color = $percent_change >= 0 ? '#28a745' : '#dc3545';

    // Email content as pure table
    $html = '
    <table width="100%" cellpadding="0" cellspacing="0" style="font-family:sans-serif; background-color:#f6f7f9; padding:40px;">
        <tr>
            <td align="center">
                <img src="https://staging.myriadsolutionz.com/wp-content/uploads/2025/07/smtp-mailer-1.png" alt="Stars SMTP Mailer" height="60" />
            </td>
        </tr>
        <tr><td height="30"></td></tr>
        <tr>
            <td align="center" style="font-size:22px; font-weight:bold; color:#333;">Hi there,</td>
        </tr>
        <tr>
            <td align="center" style="font-size:16px; color:#555;">Let’s see how many emails you’ve sent with Stars SMTP Mailer.</td>
        </tr>
        <tr><td height="40"></td></tr>
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" style="margin:auto;">
                    <tr>
                        <td style="background:#ffffff; border-radius:8px; padding:20px 30px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.05);">
                            <table>
                                <tr><td align="center" style="font-size:28px;">📩</td></tr>
                                <tr><td align="center" style="font-size:13px; color:#888888;">Total Emails</td></tr>
                                <tr><td align="center" style="font-size:28px; font-weight:bold; color:#333333;">' . intval($this_week_sent) . '</td></tr>
                                <tr><td align="center" style="font-size:13px; color:' . $percent_color . ';">' . $arrow . ' ' . abs($percent_change) . '%</td></tr>
                            </table>
                        </td>
                        <td width="40"></td>
                        <td style="background:#ffffff; border-radius:8px; padding:20px 30px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.05);">
                            <table>
                                <tr><td align="center" style="font-size:28px;">✅</td></tr>
                                <tr><td align="center" style="font-size:13px; color:#888888;">Last Week</td></tr>
                                <tr><td align="center" style="font-size:28px; font-weight:bold; color:#333333;">' . intval($last_week_sent) . '</td></tr>
                                <tr><td align="center" style="font-size:13px; color:#dddddd;">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>';

    return $html;
}


// 5. Cron email sender
add_action('starssmtpmailer_send_weekly_email', 'starssmtpmailer_send_weekly_email_callback');

function starssmtpmailer_send_weekly_email_callback() {
    global $wpdb;

    // ✅ Check if there’s an active SMTP configuration
    $active_smtp = $wpdb->get_var("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}stars_smtp_settings
        WHERE status = 1
    ");

    if (!$active_smtp) {
        // No active SMTP — skip silently
        return;
    }

    // ✅ Get admin email fallback
    $admin_users = get_users([
        'role'    => 'administrator',
        'number'  => 1,
        'orderby' => 'ID',
        'order'   => 'ASC',
        'fields'  => ['user_email'],
    ]);

    $admin_email = !empty($admin_users) ? $admin_users[0]->user_email : get_option('admin_email');

    $to       = $admin_email;
    $subject  = 'Your Weekly SMTP Mail Report';
    $headers  = ['Content-Type: text/html; charset=UTF-8'];
    $message  = '<p>Hello,</p>';
    $message .= '<p>Here is your weekly SMTP mail report:</p>';
    $message .= starssmtpm_generate_email_statistics_html();

    $success = wp_mail($to, $subject, $message, $headers);

    // Intentionally no error_log here — failures are visible in the email log table
    unset($success);
}
