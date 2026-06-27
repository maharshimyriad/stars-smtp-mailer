<?php
if (!defined('ABSPATH')) {
    exit;
}

$path = STARS_SMTPM_PLUGIN_DIR . '/action/stars-class-table-layout.php';
include($path);

$Show_List_Table->set_tablename(STARS_SMTPM_EMAILS_LOG);
$Show_List_Table->set_id('log_id');
$Show_List_Table->prepare_items();

global $isAdmin;

// Current filter values
$f_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
$f_type   = isset($_GET['filter_type'])   ? sanitize_text_field($_GET['filter_type'])   : '';
$f_search = isset($_POST['s'])            ? sanitize_text_field(wp_unslash($_POST['s'])) : ( isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '' );
$f_sdate  = isset($_POST['sdate'])        ? sanitize_text_field($_POST['sdate'])         : gmdate('d/m/Y');
$f_edate  = isset($_POST['edate'])        ? sanitize_text_field($_POST['edate'])         : gmdate('d/m/Y');
$has_filter = $f_status || $f_type || $f_search
    || ( $f_sdate && $f_sdate !== gmdate('d/m/Y') )
    || ( $f_edate && $f_edate !== gmdate('d/m/Y') );

$export_url = wp_nonce_url(
    admin_url('admin.php?page=stars-smtpm-email-log&stars_export_csv=1'),
    'stars_smtpm_export_csv'
);
$clear_url = wp_nonce_url(
    admin_url('admin.php?page=stars-smtpm-email-log&stars_clear_logs=1'),
    'stars_smtpm_clear_logs'
);
?>

<div id="wpbody" role="main">
    <div id="wpbody-content" aria-label="Main content" tabindex="0">
        <div class="wrap stars-email-logs">

            <h1><?php esc_html_e('Email Logs', 'stars-smtp-mailer'); ?></h1>

            <?php if ( isset($_GET['stars_cleared']) ) : ?>
                <div class="notice notice-success is-dismissible stars_save_msg">
                    <p><?php esc_html_e('All email logs have been cleared.', 'stars-smtp-mailer'); ?></p>
                </div>
            <?php endif; ?>

            <!-- ================================================
                 Unified filter bar — replaces default WP filters
            ================================================ -->
            <div class="stars-filter-bar">
                <form method="POST" action="<?php echo esc_url( admin_url('admin.php?page=stars-smtpm-email-log') ); ?>"
                      class="stars-filter-bar__form" id="stars-filter-form">
                    <input type="hidden" name="page" value="stars-smtpm-email-log">

                    <!-- Search -->
                    <div class="stars-filter-bar__group">
                        <div class="stars-filter-bar__input-wrap">
                            <svg class="stars-filter-bar__icon" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="search" name="s" id="stars-log-search"
                                value="<?php echo esc_attr($f_search); ?>"
                                placeholder="<?php esc_attr_e('Search subject, email, body…', 'stars-smtp-mailer'); ?>"
                                class="stars-filter-bar__search" />
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="stars-filter-bar__group">
                        <select name="filter_status" class="stars-filter-select">
                            <option value=""><?php esc_html_e('All statuses', 'stars-smtp-mailer'); ?></option>
                            <option value="Sent"   <?php selected($f_status, 'Sent'); ?>><?php esc_html_e('Accepted', 'stars-smtp-mailer'); ?></option>
                            <option value="Unsent" <?php selected($f_status, 'Unsent'); ?>><?php esc_html_e('Failed', 'stars-smtp-mailer'); ?></option>
                        </select>
                    </div>

                    <!-- Mail type -->
                    <div class="stars-filter-bar__group">
                        <select name="filter_type" class="stars-filter-select">
                            <option value=""><?php esc_html_e('All types', 'stars-smtp-mailer'); ?></option>
                            <option value="test"    <?php selected($f_type, 'test'); ?>><?php esc_html_e('Test', 'stars-smtp-mailer'); ?></option>
                            <option value="general" <?php selected($f_type, 'general'); ?>><?php esc_html_e('General', 'stars-smtp-mailer'); ?></option>
                        </select>
                    </div>

                    <!-- Date from -->
                    <div class="stars-filter-bar__group">
                        <div class="stars-filter-bar__input-wrap">
                            <svg class="stars-filter-bar__icon" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <input type="text" name="sdate" id="sdate"
                                value="<?php echo esc_attr($f_sdate); ?>"
                                placeholder="<?php esc_attr_e('From date', 'stars-smtp-mailer'); ?>"
                                class="stars-filter-bar__date" autocomplete="off" readonly />
                        </div>
                    </div>

                    <!-- Date to -->
                    <div class="stars-filter-bar__group">
                        <div class="stars-filter-bar__input-wrap">
                            <svg class="stars-filter-bar__icon" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <input type="text" name="edate" id="edate"
                                value="<?php echo esc_attr($f_edate); ?>"
                                placeholder="<?php esc_attr_e('To date', 'stars-smtp-mailer'); ?>"
                                class="stars-filter-bar__date" autocomplete="off" readonly />
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="stars-filter-bar__group stars-filter-bar__group--actions">
                        <button type="submit" class="button button-primary stars-filter-bar__apply">
                            <?php esc_html_e('Apply', 'stars-smtp-mailer'); ?>
                        </button>
                        <?php if ($has_filter) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-email-log')); ?>"
                               class="button stars-filter-bar__reset">
                                <?php esc_html_e('Reset', 'stars-smtp-mailer'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Export + Clear on the right -->
                <div class="stars-filter-bar__right">
                    <a href="<?php echo esc_url($export_url); ?>" class="button stars-export-csv-btn"
                       title="<?php esc_attr_e('Download logs as CSV', 'stars-smtp-mailer'); ?>">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export CSV', 'stars-smtp-mailer'); ?>
                    </a>
                    <a href="<?php echo esc_url($clear_url); ?>" class="button stars-clear-logs-btn"
                       id="stars-clear-logs"
                       title="<?php esc_attr_e('Delete all log entries', 'stars-smtp-mailer'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear Logs', 'stars-smtp-mailer'); ?>
                    </a>
                </div>
            </div><!-- /.stars-filter-bar -->

            <form method="POST" name="smtp_accounts_list" id="my-content-id">
                <?php $Show_List_Table->display(); ?>
            </form>

            <input type="hidden" id="check_admin" value="<?php echo $isAdmin ? '1' : '0'; ?>" />
        </div>
    </div>
</div>

<!-- Error details modal -->
<div id="stars-error-modal-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:99999;align-items:center;justify-content:center;">
    <div id="stars-error-modal" role="dialog" aria-modal="true" aria-labelledby="stars-error-modal-title"
         style="background:#fff;border-radius:10px;width:580px;max-width:95vw;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 16px 48px rgba(0,0,0,.22);overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px 0;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="background:#fce8e8;border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d63638" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </span>
                <span id="stars-error-modal-title" style="font-weight:700;font-size:15px;color:#1d2327;">
                    <?php esc_html_e('Delivery Failed', 'stars-smtp-mailer'); ?>
                </span>
            </div>
            <button id="stars-error-modal-close" type="button" aria-label="<?php esc_attr_e('Close', 'stars-smtp-mailer'); ?>"
                style="background:none;border:none;font-size:22px;cursor:pointer;color:#646970;line-height:1;padding:4px;">&times;</button>
        </div>
        <div style="display:flex;gap:0;padding:0 20px;margin-top:14px;border-bottom:2px solid #f0f0f1;">
            <button type="button" class="stars-err-tab" data-tab="plain"
                style="padding:8px 18px;font-size:13px;font-weight:600;background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;">
                <?php esc_html_e('What happened?', 'stars-smtp-mailer'); ?>
            </button>
            <button type="button" class="stars-err-tab" data-tab="technical"
                style="padding:8px 18px;font-size:13px;font-weight:600;background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;">
                <?php esc_html_e('Technical details', 'stars-smtp-mailer'); ?>
            </button>
        </div>
        <div id="stars-err-panel-plain" class="stars-err-panel" style="padding:22px 22px 18px;flex:1;overflow-y:auto;">
            <p id="stars-err-plain-text" style="font-size:14px;line-height:1.7;color:#1d2327;margin:0 0 18px;"></p>
            <div style="background:#f6f7f7;border-radius:6px;padding:14px 16px;border:1px solid #dcdcde;">
                <p style="margin:0 0 8px;font-size:12.5px;font-weight:700;color:#3c434a;text-transform:uppercase;letter-spacing:.5px;"><?php esc_html_e('Common fixes', 'stars-smtp-mailer'); ?></p>
                <ul id="stars-err-fixes" style="margin:0;padding-left:18px;font-size:13px;color:#3c434a;line-height:1.8;"></ul>
            </div>
        </div>
        <div id="stars-err-panel-technical" class="stars-err-panel" style="display:none;padding:18px 22px;flex:1;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12px;font-weight:600;color:#646970;text-transform:uppercase;letter-spacing:.5px;"><?php esc_html_e('SMTP debug log', 'stars-smtp-mailer'); ?></span>
                <button type="button" id="stars-err-copy-btn" style="font-size:12px;padding:4px 12px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;display:flex;align-items:center;gap:5px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <?php esc_html_e('Copy', 'stars-smtp-mailer'); ?>
                </button>
            </div>
            <pre id="stars-err-technical-text" style="background:#1e1e2e;color:#cdd6f4;border-radius:6px;padding:14px 16px;font-size:12px;line-height:1.6;overflow-x:auto;white-space:pre-wrap;word-break:break-all;max-height:320px;overflow-y:auto;margin:0;font-family:monospace;"></pre>
        </div>
        <div style="padding:12px 22px;border-top:1px solid #f0f0f1;display:flex;justify-content:flex-end;">
            <?php
            $active_acc = stars_smtpm_get_smtp_account();
            $edit_url   = $active_acc
                ? admin_url('admin.php?page=stars-smtpm-new-account&action=edit&id=' . intval($active_acc['id']))
                : admin_url('admin.php?page=stars-smtpm-accounts');
            ?>
            <a href="<?php echo esc_url($edit_url); ?>" style="font-size:13px;color:#2271b1;text-decoration:none;margin-right:auto;line-height:32px;">
                &#9998; <?php esc_html_e('Check account settings', 'stars-smtp-mailer'); ?>
            </a>
            <button type="button" id="stars-error-modal-close2" style="padding:6px 18px;font-size:13px;font-weight:600;background:#f6f7f7;border:1px solid #dcdcde;border-radius:5px;cursor:pointer;color:#1d2327;">
                <?php esc_html_e('Close', 'stars-smtp-mailer'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Email preview modal -->
<div id="stars-preview-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;width:700px;max-width:95vw;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #f0f0f1;">
            <strong style="font-size:14px;" id="stars-preview-subject"></strong>
            <button id="stars-preview-close" type="button" style="background:none;border:none;font-size:22px;cursor:pointer;color:#646970;line-height:1;" aria-label="<?php esc_attr_e('Close', 'stars-smtp-mailer'); ?>">&times;</button>
        </div>
        <div style="padding:6px 20px;font-size:12px;color:#646970;border-bottom:1px solid #f0f0f1;" id="stars-preview-meta"></div>
        <iframe id="stars-preview-iframe" sandbox="allow-same-origin" style="flex:1;border:none;min-height:420px;border-radius:0 0 8px 8px;" title="<?php esc_attr_e('Email preview', 'stars-smtp-mailer'); ?>"></iframe>
    </div>
</div>

<script type="text/javascript">
var Permission = <?php echo $isAdmin ? 'true' : 'false'; ?>;

jQuery(document).ready(function ($) {

    // Date pickers on the new filter bar
    $("#sdate").datepicker({
        dateFormat: "dd/mm/yy", changeMonth: true, changeYear: true,
        numberOfMonths: 1, maxDate: 0,
        onClose: function (d) { $("#edate").datepicker("option", "minDate", d); }
    }).on('click', function(){ $(this).removeAttr('readonly'); });
    $("#edate").datepicker({
        dateFormat: "dd/mm/yy", changeMonth: true, changeYear: true,
        numberOfMonths: 1, maxDate: 0,
        onClose: function (d) { $("#sdate").datepicker("option", "maxDate", d); }
    }).on('click', function(){ $(this).removeAttr('readonly'); });

    // Checkbox guard
    $(".stars-email-logs input[type='checkbox']").click(function () {
        if (!Permission) {
            $(this).prop("checked", false).change();
            OpenPopup('<?php echo esc_js(__('Access Restricted', 'stars-smtp-mailer')); ?>', '<?php echo esc_js(__('This feature is available in PRO version!', 'stars-smtp-mailer')); ?>');
        }
    });

    // Clear logs confirmation
    $('#stars-clear-logs').on('click', function (e) {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete ALL email logs? This cannot be undone.', 'stars-smtp-mailer')); ?>')) {
            e.preventDefault();
        }
    });

    // Email preview modal
    $(document).on('click', '.stars-email-logs a.thickbox', function (e) {
        e.preventDefault();
        var href  = $(this).attr('href');
        var match = href.match(/inlineId=my-content-(\d+)/);
        if (!match) return;
        var $wrap = $('#my-content-' + match[1]);
        if (!$wrap.length) return;
        var body    = $wrap.find('div').html();
        var subject = $(this).closest('tr').find('.column-sub').text().trim();
        var from    = $(this).closest('tr').find('.column-from').text().trim();
        var to      = $(this).closest('tr').find('.column-to').text().split('\n')[0].trim();
        $('#stars-preview-subject').text(subject || '(no subject)');
        $('#stars-preview-meta').html('<strong><?php echo esc_js(__('From', 'stars-smtp-mailer')); ?>:</strong> ' + $('<span>').text(from).html() + ' &nbsp;|&nbsp; <strong><?php echo esc_js(__('To', 'stars-smtp-mailer')); ?>:</strong> ' + $('<span>').text(to).html());
        var doc = document.getElementById('stars-preview-iframe').contentDocument || document.getElementById('stars-preview-iframe').contentWindow.document;
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:sans-serif;padding:16px;margin:0;font-size:13px;color:#1d2327;word-break:break-word}</style></head><body>' + (body || '<em>(empty body)</em>') + '</body></html>');
        doc.close();
        $('#stars-preview-overlay').css('display', 'flex');
    });
    $('#stars-preview-close, #stars-preview-overlay').on('click', function (e) { if (e.target === this) $('#stars-preview-overlay').hide(); });

    // Error modal
    var fixesByKeyword = {
        credentials: ['<?php echo esc_js(__('Go to SMTP Accounts → Edit your account', 'stars-smtp-mailer')); ?>', '<?php echo esc_js(__('Re-enter your password carefully — copy-paste to avoid typos', 'stars-smtp-mailer')); ?>', '<?php echo esc_js(__('For Gmail: use an App Password, not your Google account password', 'stars-smtp-mailer')); ?>'],
        ssl:         ['<?php echo esc_js(__('Port 587 → use TLS encryption', 'stars-smtp-mailer')); ?>', '<?php echo esc_js(__('Port 465 → use SSL encryption', 'stars-smtp-mailer')); ?>', '<?php echo esc_js(__('Port 25 → use None (no encryption)', 'stars-smtp-mailer')); ?>'],
        connection:  ['<?php echo esc_js(__('Verify the SMTP host is spelled correctly', 'stars-smtp-mailer')); ?>', '<?php echo esc_js(__('Confirm the port is not blocked by your hosting firewall', 'stars-smtp-mailer')); ?>', '<?php echo esc_js(__('Try port 587 with TLS as a fallback', 'stars-smtp-mailer')); ?>'],
        default:     ['<?php echo esc_js(__('Check SMTP host, port and encryption match your provider settings', 'stars-smtp-mailer')); ?>', '<?php echo esc_js(__('Verify your username and password are correct', 'stars-smtp-mailer')); ?>', '<?php echo esc_js(__('Switch to the Technical tab and search the error code online', 'stars-smtp-mailer')); ?>']
    };
    function getFixList(t) {
        t = t.toLowerCase();
        if (t.indexOf('password') !== -1 || t.indexOf('credential') !== -1) return fixesByKeyword.credentials;
        if (t.indexOf('ssl') !== -1 || t.indexOf('tls') !== -1 || t.indexOf('port') !== -1) return fixesByKeyword.ssl;
        if (t.indexOf('connect') !== -1 || t.indexOf('reach') !== -1) return fixesByKeyword.connection;
        return fixesByKeyword.default;
    }
    $(document).on('click', '.stars-error-details-btn', function () {
        var plain = $(this).data('plain'), technical = $(this).data('technical');
        $('#stars-err-plain-text').text(plain);
        var $ul = $('#stars-err-fixes').empty();
        getFixList(plain).forEach(function (f) { $ul.append($('<li>').text(f)); });
        $('#stars-err-technical-text').text(technical || '(no debug output recorded)');
        $('.stars-err-tab').removeClass('stars-err-tab--active');
        $('.stars-err-tab[data-tab="plain"]').addClass('stars-err-tab--active');
        $('#stars-err-panel-plain').show();
        $('#stars-err-panel-technical').hide();
        $('#stars-error-modal-overlay').css('display', 'flex');
    });
    $(document).on('click', '.stars-err-tab', function () {
        var tab = $(this).data('tab');
        $('.stars-err-tab').removeClass('stars-err-tab--active');
        $(this).addClass('stars-err-tab--active');
        $('.stars-err-panel').hide();
        $('#stars-err-panel-' + tab).show();
    });
    $('#stars-error-modal-close, #stars-error-modal-close2').on('click', function () { $('#stars-error-modal-overlay').hide(); });
    $('#stars-error-modal-overlay').on('click', function (e) { if (e.target === this) $(this).hide(); });
    $('#stars-err-copy-btn').on('click', function () {
        navigator.clipboard.writeText($('#stars-err-technical-text').text()).then(function () {
            var $b = $('#stars-err-copy-btn');
            $b.text('<?php echo esc_js(__('Copied!', 'stars-smtp-mailer')); ?>');
            setTimeout(function () { $b.html('<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> <?php echo esc_js(__('Copy', 'stars-smtp-mailer')); ?>'); }, 2000);
        });
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') { $('#stars-error-modal-overlay, #stars-preview-overlay').hide(); }
    });
});
</script>
