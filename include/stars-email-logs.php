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

$export_url = wp_nonce_url(
    admin_url('admin.php?page=stars-smtpm-email-log&stars_export_csv=1'),
    'stars_smtpm_export_csv'
);
?>

<div id="wpbody" role="main">
    <div id="wpbody-content" aria-label="Main content" tabindex="0">
        <div class="wrap stars-email-logs">
            <div id="icon-users" class="icon32"></div>
            <h1 class="wp-heading-inline"><?php echo esc_html__('Email Logs', 'stars-smtp-mailer'); ?></h1>

            <a href="<?php echo esc_url($export_url); ?>" class="button stars-export-csv-btn" title="<?php esc_attr_e('Download all log entries as CSV', 'stars-smtp-mailer'); ?>">
                <span class="dashicons dashicons-download" style="margin-top:3px;"></span>
                <?php esc_html_e('Export CSV', 'stars-smtp-mailer'); ?>
            </a>

            <form action="<?php echo esc_url(admin_url('/admin.php')); ?>" method="GET"
                class="stars-float-right star-margin-top-18">
                <p class="search-box">
                    <input type="hidden" name="page" value="<?php echo esc_attr('stars-smtpm-email-log'); ?>">
                    <?php if (isset($_GET['paged']) && sanitize_key($_GET['paged']) !== '') { ?>
                        <input type="hidden" name="paged" value="<?php echo esc_attr(sanitize_key($_GET['paged'])); ?>">
                    <?php } ?>
                    <label class="screen-reader-text" for="post-search-input"><?php echo esc_html__('Search:', 'stars-smtp-mailer'); ?></label>
                    <input type="search" id="post-search-input" name="s"
                        value="<?php echo isset($_GET['s']) ? esc_attr(wp_unslash($_GET['s'])) : ''; ?>" />
                    <input type="submit" id="search-submit" class="button"
                        value="<?php echo esc_attr__('Search', 'stars-smtp-mailer'); ?>" />
                </p>
            </form>

            <form method="POST" name="smtp_accounts_list" id="my-content-id">
                <?php $Show_List_Table->display(); ?>
            </form>

            <input type="hidden" id="check_admin" value="<?php echo $isAdmin ? '1' : '0'; ?>" />
        </div>
    </div>
</div>

<!-- Email preview modal (iframe-based, replaces thickbox) -->
<div id="stars-preview-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;width:700px;max-width:95vw;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #f0f0f1;">
            <strong style="font-size:14px;" id="stars-preview-subject"></strong>
            <button id="stars-preview-close" type="button" style="background:none;border:none;font-size:22px;cursor:pointer;color:#646970;line-height:1;" aria-label="Close">&times;</button>
        </div>
        <div style="padding:6px 20px;font-size:12px;color:#646970;border-bottom:1px solid #f0f0f1;" id="stars-preview-meta"></div>
        <iframe id="stars-preview-iframe" sandbox="allow-same-origin" style="flex:1;border:none;min-height:420px;border-radius:0 0 8px 8px;" title="Email preview"></iframe>
    </div>
</div>

<script type="text/javascript">
    var Permission = true;
    <?php if (!$isAdmin) { ?>
        Permission = false;
    <?php } ?>

    jQuery(document).ready(function ($) {
        // Date pickers
        $("#sdate").datepicker({
            dateFormat: "dd/mm/yy", changeMonth: true, changeYear: true,
            numberOfMonths: 1, maxDate: 0,
            onClose: function (d) { $("#edate").datepicker("option", "minDate", d); }
        });
        $("#edate").datepicker({
            dateFormat: "dd/mm/yy", changeMonth: true, changeYear: true,
            numberOfMonths: 1, maxDate: 0,
            onClose: function (d) { $("#sdate").datepicker("option", "maxDate", d); }
        });

        // Checkbox guard for non-admins
        $(".stars-email-logs input[type='checkbox']").click(function () {
            if (Permission === false) {
                $(this).prop("checked", false).change();
                OpenPopup(
                    '<?php echo esc_js(__('Access Restricted', 'stars-smtp-mailer')); ?>',
                    '<?php echo esc_js(__('This feature is available in PRO version!', 'stars-smtp-mailer')); ?>'
                );
            }
        });

        // Intercept thickbox "View" links — open iframe preview instead
        $(document).on('click', '.stars-email-logs a.thickbox', function (e) {
            e.preventDefault();
            var href   = $(this).attr('href'); // #TB_inline?...&inlineId=my-content-NNN
            var match  = href.match(/inlineId=my-content-(\d+)/);
            if (!match) return;
            var id     = match[1];
            var $wrap  = $('#my-content-' + id);
            if (!$wrap.length) return;

            var body    = $wrap.find('div').html();
            var subject = $(this).closest('tr').find('.column-sub').text().trim();
            var from    = $(this).closest('tr').find('.column-from').text().trim();
            var to      = $(this).closest('tr').find('.column-to').text().replace(/\s+/g, ' ').trim();
            // Strip hidden div text from "to" cell
            to = to.split('\n')[0].trim();

            $('#stars-preview-subject').text(subject || '(no subject)');
            $('#stars-preview-meta').html(
                '<strong><?php echo esc_js(__('From', 'stars-smtp-mailer')); ?>:</strong> ' + $('<span>').text(from).html() +
                ' &nbsp;|&nbsp; <strong><?php echo esc_js(__('To', 'stars-smtp-mailer')); ?>:</strong> ' + $('<span>').text(to).html()
            );

            var iframe = document.getElementById('stars-preview-iframe');
            iframe.onload = function () {};
            // Write HTML into sandboxed iframe — no scripts can run inside
            var doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:sans-serif;padding:16px;margin:0;font-size:13px;color:#1d2327;word-break:break-word}</style></head><body>' + (body || '<em>(empty body)</em>') + '</body></html>');
            doc.close();

            $('#stars-preview-overlay').css('display', 'flex');
        });

        $('#stars-preview-close, #stars-preview-overlay').on('click', function (e) {
            if (e.target === this) $('#stars-preview-overlay').hide();
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') $('#stars-preview-overlay').hide();
        });
    });
</script>
