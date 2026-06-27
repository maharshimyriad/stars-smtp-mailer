<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

/* ----------------------------------------------------------------
   Gather stats
---------------------------------------------------------------- */
$rows_7d = $wpdb->get_results(
    "SELECT DATE(mail_date) as d, status FROM " . STARS_SMTPM_EMAILS_LOG .
    " WHERE mail_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)", ARRAY_A
);

$today        = gmdate('Y-m-d');
$total_sent   = 0;
$total_failed = 0;
$today_count  = 0;
$days = $day_sent = $day_failed = [];

for ($i = 6; $i >= 0; $i--) {
    $d             = gmdate('Y-m-d', strtotime("-{$i} days"));
    $days[]        = gmdate('D d', strtotime($d));
    $day_sent[$d]  = 0;
    $day_failed[$d]= 0;
}

foreach ($rows_7d as $r) {
    $d = $r['d'];
    if ($r['status'] === 'Sent') {
        $total_sent++;
        if (isset($day_sent[$d]))   $day_sent[$d]++;
    } else {
        $total_failed++;
        if (isset($day_failed[$d])) $day_failed[$d]++;
    }
    if ($d === $today) $today_count++;
}

$log_count   = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . STARS_SMTPM_EMAILS_LOG);
$acc_count   = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . STARS_SMTPM_SMTP_SETTINGS);
$active_acc  = stars_smtpm_get_smtp_account();
$success_rate = ($total_sent + $total_failed) > 0
    ? round(($total_sent / ($total_sent + $total_failed)) * 100)
    : 0;

// Last 5 log entries
$recent_logs = $wpdb->get_results(
    "SELECT log_id, from_email, email_id, sub, status, mail_date FROM " . STARS_SMTPM_EMAILS_LOG .
    " ORDER BY log_id DESC LIMIT 5", ARRAY_A
);
?>

<div id="wpbody">
<div id="wpbody-content">
<h1><?php esc_html_e('Stars SMTP Mailer — Dashboard', 'stars-smtp-mailer'); ?></h1>

<!-- ============================================================
     Stat cards row
============================================================ -->
<div class="stars-dash-cards">

    <div class="stars-dash-card">
        <div class="stars-dash-card__icon" style="background:#e8f0fb;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2271b1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <div class="stars-dash-card__body">
            <span class="stars-dash-card__value"><?php echo intval($today_count); ?></span>
            <span class="stars-dash-card__label"><?php esc_html_e("Today's Emails", 'stars-smtp-mailer'); ?></span>
        </div>
    </div>

    <div class="stars-dash-card">
        <div class="stars-dash-card__icon" style="background:#edfaef;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#00a32a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stars-dash-card__body">
            <span class="stars-dash-card__value" style="color:#00a32a;"><?php echo intval($total_sent); ?></span>
            <span class="stars-dash-card__label"><?php esc_html_e('Accepted (7 days)', 'stars-smtp-mailer'); ?></span>
        </div>
    </div>

    <div class="stars-dash-card">
        <div class="stars-dash-card__icon" style="background:#fce8e8;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d63638" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stars-dash-card__body">
            <span class="stars-dash-card__value" style="color:#d63638;"><?php echo intval($total_failed); ?></span>
            <span class="stars-dash-card__label"><?php esc_html_e('Failed (7 days)', 'stars-smtp-mailer'); ?></span>
        </div>
    </div>

    <div class="stars-dash-card">
        <div class="stars-dash-card__icon" style="background:#fff8e1;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dba617" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        </div>
        <div class="stars-dash-card__body">
            <span class="stars-dash-card__value" style="color:#dba617;"><?php echo intval($success_rate); ?>%</span>
            <span class="stars-dash-card__label"><?php esc_html_e('Success Rate (7 days)', 'stars-smtp-mailer'); ?></span>
        </div>
    </div>

</div><!-- /.stars-dash-cards -->

<!-- ============================================================
     Two-column layout: chart + sidebar
============================================================ -->
<div class="stars-dash-grid">

    <!-- Chart card -->
    <div class="stars-dash-col stars-dash-col--main">
        <div class="stars-dash-panel">
            <div class="stars-dash-panel__header">
                <span class="stars-dash-panel__title"><?php esc_html_e('Email Activity — Last 7 Days', 'stars-smtp-mailer'); ?></span>
            </div>
            <div class="stars-dash-panel__body">
                <canvas id="stars-dash-chart" height="80"></canvas>
            </div>
        </div>

        <!-- Recent emails table -->
        <div class="stars-dash-panel" style="margin-top:20px;">
            <div class="stars-dash-panel__header">
                <span class="stars-dash-panel__title"><?php esc_html_e('Recent Emails', 'stars-smtp-mailer'); ?></span>
                <a href="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-email-log')); ?>" class="stars-dash-panel__action">
                    <?php esc_html_e('View all', 'stars-smtp-mailer'); ?> &rarr;
                </a>
            </div>
            <div class="stars-dash-panel__body" style="padding:0;">
                <?php if (empty($recent_logs)) : ?>
                    <p style="padding:16px;color:#646970;font-size:13px;margin:0;"><?php esc_html_e('No emails logged yet.', 'stars-smtp-mailer'); ?></p>
                <?php else : ?>
                <table class="stars-dash-recent-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Subject', 'stars-smtp-mailer'); ?></th>
                            <th><?php esc_html_e('To', 'stars-smtp-mailer'); ?></th>
                            <th><?php esc_html_e('Date', 'stars-smtp-mailer'); ?></th>
                            <th><?php esc_html_e('Status', 'stars-smtp-mailer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html(wp_trim_words($log['sub'], 6, '…')); ?></td>
                            <td><?php echo esc_html($log['email_id']); ?></td>
                            <td><?php echo esc_html(gmdate('d M, H:i', strtotime($log['mail_date']))); ?></td>
                            <td>
                                <?php if ($log['status'] === 'Sent') : ?>
                                    <span class="stars-status-pill stars-status-pill--accepted"><span class="stars-status-dot"></span><?php esc_html_e('Accepted', 'stars-smtp-mailer'); ?></span>
                                <?php else : ?>
                                    <span class="stars-status-pill stars-status-pill--failed"><span class="stars-status-dot"></span><?php esc_html_e('Failed', 'stars-smtp-mailer'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="stars-dash-col stars-dash-col--side">

        <!-- Active account card -->
        <div class="stars-dash-panel">
            <div class="stars-dash-panel__header">
                <span class="stars-dash-panel__title"><?php esc_html_e('Active SMTP Account', 'stars-smtp-mailer'); ?></span>
            </div>
            <div class="stars-dash-panel__body">
                <?php if ($active_acc) : ?>
                    <div class="stars-dash-account">
                        <div class="stars-dash-account__avatar">
                            <?php echo esc_html(strtoupper(substr($active_acc['from_name'] ?: $active_acc['username'], 0, 2))); ?>
                        </div>
                        <div class="stars-dash-account__info">
                            <strong><?php echo esc_html($active_acc['from_name'] ?: $active_acc['username']); ?></strong>
                            <span><?php echo esc_html($active_acc['from_email']); ?></span>
                            <span class="stars-dash-account__meta">
                                <?php echo esc_html(strtoupper($active_acc['smtp_host'])); ?>
                                &nbsp;·&nbsp;
                                <?php echo esc_html(strtoupper($active_acc['encryption'])); ?>
                                &nbsp;·&nbsp;
                                <?php echo esc_html($active_acc['smtp_port']); ?>
                            </span>
                        </div>
                    </div>
                    <div style="margin-top:12px;display:flex;gap:8px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-new-account&action=edit&id=' . intval($active_acc['id']))); ?>" class="button button-secondary" style="flex:1;text-align:center;">
                            <?php esc_html_e('Edit', 'stars-smtp-mailer'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-test-mail&id=' . intval($active_acc['id']))); ?>" class="button button-primary" style="flex:1;text-align:center;">
                            <?php esc_html_e('Send Test', 'stars-smtp-mailer'); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <div style="text-align:center;padding:12px 0;">
                        <p style="color:#646970;font-size:13px;margin:0 0 12px;"><?php esc_html_e('No active SMTP account.', 'stars-smtp-mailer'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-new-account')); ?>" class="button button-primary">
                            <?php esc_html_e('Add Account', 'stars-smtp-mailer'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick stats card -->
        <div class="stars-dash-panel" style="margin-top:20px;">
            <div class="stars-dash-panel__header">
                <span class="stars-dash-panel__title"><?php esc_html_e('Overview', 'stars-smtp-mailer'); ?></span>
            </div>
            <div class="stars-dash-panel__body" style="padding:0;">
                <ul class="stars-dash-overview-list">
                    <li>
                        <span><?php esc_html_e('SMTP Accounts', 'stars-smtp-mailer'); ?></span>
                        <strong><?php echo intval($acc_count); ?> / 3</strong>
                    </li>
                    <li>
                        <span><?php esc_html_e('Log Entries', 'stars-smtp-mailer'); ?></span>
                        <strong class="<?php echo $log_count >= 180 ? 'stars-log-count-warn' : ''; ?>">
                            <?php echo intval($log_count); ?> / 200
                        </strong>
                    </li>
                    <li>
                        <span><?php esc_html_e('Plugin Version', 'stars-smtp-mailer'); ?></span>
                        <strong><?php echo esc_html(STARS_SMTPM_PLUGIN_VERSION); ?></strong>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick actions card -->
        <div class="stars-dash-panel" style="margin-top:20px;">
            <div class="stars-dash-panel__header">
                <span class="stars-dash-panel__title"><?php esc_html_e('Quick Actions', 'stars-smtp-mailer'); ?></span>
            </div>
            <div class="stars-dash-panel__body" style="padding:10px 16px;display:flex;flex-direction:column;gap:8px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-new-account')); ?>" class="button stars-dash-action-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php esc_html_e('Add SMTP Account', 'stars-smtp-mailer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-test-mail')); ?>" class="button stars-dash-action-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <?php esc_html_e('Send Test Email', 'stars-smtp-mailer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-email-log')); ?>" class="button stars-dash-action-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <?php esc_html_e('View Email Logs', 'stars-smtp-mailer'); ?>
                </a>
                <?php
                $export_url = wp_nonce_url(admin_url('admin.php?page=stars-smtpm-email-log&stars_export_csv=1'), 'stars_smtpm_export_csv');
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button stars-dash-action-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?php esc_html_e('Export Logs CSV', 'stars-smtp-mailer'); ?>
                </a>
            </div>
        </div>

    </div><!-- /.stars-dash-col--side -->
</div><!-- /.stars-dash-grid -->

<div class="stars_footer">
    <a href="https://myriadsolutionz.com/" target="_blank">
        <img src="<?php echo esc_url(STARS_SMTPM_MYRIAD_LOGO); ?>" alt="logo" title="Myriad Solutionz" />
    </a>
</div>

</div><!-- /#wpbody-content -->
</div><!-- /#wpbody -->

<script>
(function(){
    var load = function(){
        if(typeof Chart === 'undefined'){
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
            s.onload = draw;
            document.head.appendChild(s);
        } else { draw(); }
    };
    function draw(){
        var ctx = document.getElementById('stars-dash-chart');
        if(!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo wp_json_encode(array_values($days)); ?>,
                datasets:[
                    {
                        label: '<?php echo esc_js(__('Accepted','stars-smtp-mailer')); ?>',
                        data: <?php echo wp_json_encode(array_values($day_sent)); ?>,
                        backgroundColor: 'rgba(0,163,42,0.75)',
                        borderRadius: 4,
                        borderSkipped: false
                    },
                    {
                        label: '<?php echo esc_js(__('Failed','stars-smtp-mailer')); ?>',
                        data: <?php echo wp_json_encode(array_values($day_failed)); ?>,
                        backgroundColor: 'rgba(214,54,56,0.75)',
                        borderRadius: 4,
                        borderSkipped: false
                    }
                ]
            },
            options:{
                responsive:true,
                plugins:{
                    legend:{ position:'bottom', labels:{ boxWidth:12, font:{size:12}, padding:16 } }
                },
                scales:{
                    x:{ grid:{display:false}, ticks:{font:{size:11}} },
                    y:{ beginAtZero:true, ticks:{stepSize:1,font:{size:11}}, grid:{color:'#f0f0f1'} }
                }
            }
        });
    }
    if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded',load); }
    else { load(); }
})();
</script>
