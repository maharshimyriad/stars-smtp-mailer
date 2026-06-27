<?php
/**
 * Add / Edit Account — display only.
 * All form processing (including wp_redirect) is handled in
 * stars_smtpm_process_account_form() via admin_init in the main plugin file,
 * so headers are never sent before the redirect.
 */
if (!defined('ABSPATH'))
   exit;

global $wpdb;

$edit_id = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;
$is_edit = false;
$e_result = null;

/* ----------------------------------------------------------------
   Load existing record when editing
---------------------------------------------------------------- */
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'edit' && $edit_id) {
   if (!defined('STARS_SMTPM_SMTP_SETTINGS')) {
      define('STARS_SMTPM_SMTP_SETTINGS', $wpdb->prefix . 'stars_smtp_settings');
   }
   $table    = STARS_SMTPM_SMTP_SETTINGS;
   $e_result = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $edit_id),
      ARRAY_A
   );
   if ($e_result) {
      $is_edit = true;
   }
}

/* ----------------------------------------------------------------
   Flash messages (set by admin_init handler via $_SESSION)
---------------------------------------------------------------- */
$message    = '';
$errMessage = '';

if (!empty($_SESSION['acc_msg'])) {
   $message = $_SESSION['acc_msg'];
   unset($_SESSION['acc_msg']);
}
if (!empty($_SESSION['acc_err'])) {
   $errMessage = $_SESSION['acc_err'];
   unset($_SESSION['acc_err']);
}

/* ----------------------------------------------------------------
   Nonce / validation errors surfaced when admin_init didn't redirect
   (e.g. bad nonce, or missing required fields where we returned early)
---------------------------------------------------------------- */
if (isset($_POST['form-action'])) {
   if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'stars_smtpm-add_edit_account')) {
      $errMessage = esc_html__('Invalid nonce specified. Please try again!', 'stars-smtp-mailer');
   } elseif (
      sanitize_text_field($_POST['from_name'])  === '' ||
      sanitize_email($_POST['from_email'])      === '' ||
      sanitize_text_field($_POST['smtp_host'])  === '' ||
      absint($_POST['smtp_port'])               === 0  ||
      sanitize_text_field($_POST['username'])   === ''
   ) {
      $errMessage = esc_html__('Please enter mandatory fields!', 'stars-smtp-mailer');
   }
}

$title = $is_edit
   ? __('Edit Account', 'stars-smtp-mailer')
   : __('Add New Account', 'stars-smtp-mailer');
?>
<div id="wpbody">
   <div id="wpbody-content">

      <h1><?php echo esc_html($title); ?></h1>

      <?php if (!empty($message)) : ?>
         <div class="updated notice is-dismissible stars_save_msg">
            <p><strong><?php echo wp_kses($message, array('a' => array('href' => array(), 'class' => array()))); ?></strong></p>
         </div>
      <?php elseif (!empty($errMessage)) : ?>
         <div class="error is-dismissible stars_save_msg">
            <p><strong><?php echo esc_html($errMessage); ?></strong></p>
         </div>
      <?php endif; ?>

      <div class="wrap stars_wrap">
         <div class="wrap-body">
            <div class="sidebar-content">
               <form id="stars-add-new-account" method="POST">
                  <div class="wrapper" id="header">

                     <!-- ==========================================
                          Section 1: Server Connection
                     =========================================== -->
                     <div class="stars-form-section-title">
                        <?php esc_html_e('Server Connection', 'stars-smtp-mailer'); ?>
                     </div>

                     <div class="form-group">
                        <label for="smtp_host">
                           <?php esc_html_e('SMTP Host', 'stars-smtp-mailer'); ?>
                           <span class="req-star" aria-hidden="true">*</span>
                        </label>
                        <div class="input-area">
                           <input id="smtp_host" type="text"
                              placeholder="<?php echo esc_attr__('e.g. smtp.gmail.com', 'stars-smtp-mailer'); ?>"
                              name="smtp_host"
                              value="<?php echo esc_attr($is_edit ? $e_result['smtp_host'] : ''); ?>"
                              class="required" />
                           <p class="stars-input-tooltip">
                              <?php esc_html_e('The hostname of your SMTP server.', 'stars-smtp-mailer'); ?>
                           </p>
                           <span class="check_error none"></span>
                        </div>
                     </div>

                     <div class="form-group">
                        <label for="smtp_port">
                           <?php esc_html_e('SMTP Port', 'stars-smtp-mailer'); ?>
                           <span class="req-star" aria-hidden="true">*</span>
                        </label>
                        <div class="input-area">
                           <input id="smtp_port" type="text"
                              placeholder="<?php echo esc_attr__('e.g. 587', 'stars-smtp-mailer'); ?>"
                              name="smtp_port"
                              value="<?php echo esc_attr($is_edit ? $e_result['smtp_port'] : ''); ?>"
                              class="required number" />
                           <p class="stars-input-tooltip">
                              <?php esc_html_e('Common ports: 587 (TLS), 465 (SSL), 25 (None).', 'stars-smtp-mailer'); ?>
                           </p>
                        </div>
                     </div>

                     <div class="form-group">
                        <label><?php esc_html_e('Encryption', 'stars-smtp-mailer'); ?> <span class="req-star" aria-hidden="true">*</span></label>
                        <div class="input-area">
                           <div class="stars-radio-group" role="radiogroup" aria-label="<?php esc_attr_e('Encryption type', 'stars-smtp-mailer'); ?>">
                              <label class="stars-radio-pill">
                                 <input name="encryption" type="radio" id="enc_tls" value="tls"
                                    <?php echo $is_edit ? ($e_result['encryption'] == 'tls' ? 'checked' : '') : 'checked'; ?> />
                                 <?php esc_html_e('TLS', 'stars-smtp-mailer'); ?>
                              </label>
                              <label class="stars-radio-pill">
                                 <input name="encryption" type="radio" id="enc_ssl" value="ssl"
                                    <?php echo ($is_edit && $e_result['encryption'] == 'ssl') ? 'checked' : ''; ?> />
                                 <?php esc_html_e('SSL', 'stars-smtp-mailer'); ?>
                              </label>
                              <label class="stars-radio-pill">
                                 <input name="encryption" type="radio" id="enc_none" value="none"
                                    <?php echo ($is_edit && $e_result['encryption'] == 'none') ? 'checked' : ''; ?> />
                                 <?php esc_html_e('None', 'stars-smtp-mailer'); ?>
                              </label>
                           </div>
                           <p class="stars-input-tooltip">
                              <?php esc_html_e('TLS is recommended. Use SSL for port 465, or None if your host requires it.', 'stars-smtp-mailer'); ?>
                           </p>
                        </div>
                     </div>

                     <div class="form-group">
                        <label><?php esc_html_e('Authentication', 'stars-smtp-mailer'); ?> <span class="req-star" aria-hidden="true">*</span></label>
                        <div class="input-area">
                           <div class="stars-radio-group" role="radiogroup" aria-label="<?php esc_attr_e('Authentication', 'stars-smtp-mailer'); ?>">
                              <label class="stars-radio-pill">
                                 <input name="auth" type="radio" id="auth_true" value="1"
                                    <?php echo $is_edit ? ($e_result['auth'] == 1 ? 'checked' : '') : 'checked'; ?> />
                                 <?php esc_html_e('Enabled', 'stars-smtp-mailer'); ?>
                              </label>
                              <label class="stars-radio-pill">
                                 <input name="auth" type="radio" id="auth_false" value="0"
                                    <?php echo ($is_edit && $e_result['auth'] == 0) ? 'checked' : ''; ?> />
                                 <?php esc_html_e('Disabled', 'stars-smtp-mailer'); ?>
                              </label>
                           </div>
                           <p class="stars-input-tooltip">
                              <?php esc_html_e('Whether to use SMTP authentication. Recommended: Enabled.', 'stars-smtp-mailer'); ?>
                           </p>
                        </div>
                     </div>

                     <!-- ==========================================
                          Section 2: Credentials
                     =========================================== -->
                     <div class="stars-form-section-title">
                        <?php esc_html_e('Credentials', 'stars-smtp-mailer'); ?>
                     </div>

                     <div class="form-group">
                        <label for="username">
                           <?php esc_html_e('Username', 'stars-smtp-mailer'); ?>
                           <span class="req-star" aria-hidden="true">*</span>
                        </label>
                        <div class="input-area">
                           <input type="text" readonly="readonly" onfocus="this.removeAttribute('readonly')"
                              id="username"
                              placeholder="<?php echo esc_attr__('e.g. you@gmail.com', 'stars-smtp-mailer'); ?>"
                              name="username"
                              value="<?php echo esc_attr($is_edit ? $e_result['username'] : ''); ?>"
                              class="required" style="background:#fff;" />
                        </div>
                     </div>

                     <div class="form-group">
                        <label for="pass">
                           <?php esc_html_e('Password', 'stars-smtp-mailer'); ?>
                           <?php if (!$is_edit) : ?><span class="req-star" aria-hidden="true">*</span><?php endif; ?>
                        </label>
                        <div class="input-area">
                           <div class="stars-pw-wrapper">
                              <input type="password" readonly="readonly" onfocus="this.removeAttribute('readonly')"
                                 id="pass"
                                 placeholder="<?php echo $is_edit ? esc_attr__('Leave blank to keep current password', 'stars-smtp-mailer') : esc_attr__('Enter your SMTP password', 'stars-smtp-mailer'); ?>"
                                 name="pass"
                                 value=""
                                 class="acc-password <?php echo $is_edit ? '' : 'required'; ?>"
                                 style="background:#fff;" />
                              <button type="button" class="stars-pw-toggle"
                                 aria-label="<?php esc_attr_e('Toggle password visibility', 'stars-smtp-mailer'); ?>"
                                 data-target="pass">
                                 <!-- eye open -->
                                 <svg class="pw-icon-show" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                 <!-- eye off -->
                                 <svg class="pw-icon-hide" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                              </button>
                           </div>
                           <?php if ($is_edit) : ?>
                              <p class="stars-input-tooltip">
                                 <?php esc_html_e('Leave blank to keep your current password.', 'stars-smtp-mailer'); ?>
                              </p>
                           <?php endif; ?>
                        </div>
                     </div>

                     <!-- ==========================================
                          Section 3: Sender Identity
                     =========================================== -->
                     <div class="stars-form-section-title">
                        <?php esc_html_e('Sender Identity', 'stars-smtp-mailer'); ?>
                     </div>

                     <div class="form-group">
                        <label for="from_name">
                           <?php esc_html_e('From Name', 'stars-smtp-mailer'); ?>
                           <span class="req-star" aria-hidden="true">*</span>
                        </label>
                        <div class="input-area">
                           <input type="text" id="from_name"
                              placeholder="<?php echo esc_attr__('e.g. My Website', 'stars-smtp-mailer'); ?>"
                              value="<?php echo esc_attr($is_edit ? $e_result['from_name'] : ''); ?>"
                              name="from_name" class="required" />
                           <p class="stars-input-tooltip">
                              <?php esc_html_e('Overrides the default sender name for outgoing emails.', 'stars-smtp-mailer'); ?>
                           </p>
                        </div>
                     </div>

                     <div class="form-group">
                        <label for="from_email">
                           <?php esc_html_e('From Email', 'stars-smtp-mailer'); ?>
                           <span class="req-star" aria-hidden="true">*</span>
                        </label>
                        <div class="input-area">
                           <input type="email" id="from_email"
                              placeholder="<?php echo esc_attr__('e.g. hello@yourdomain.com', 'stars-smtp-mailer'); ?>"
                              value="<?php echo esc_attr($is_edit ? $e_result['from_email'] : ''); ?>"
                              name="from_email" class="required" />
                           <p class="stars-input-tooltip">
                              <?php esc_html_e('Overrides the default sender email address.', 'stars-smtp-mailer'); ?>
                           </p>
                        </div>
                     </div>

                     <!-- ==========================================
                          Section 4: Optional Recipients
                     =========================================== -->
                     <div class="stars-form-section-title">
                        <?php esc_html_e('Optional Recipients', 'stars-smtp-mailer'); ?>
                     </div>

                     <div class="form-group">
                        <label for="reply_to"><?php esc_html_e('Reply-To', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <input type="email" id="reply_to"
                              placeholder="<?php echo esc_attr__('e.g. replies@yourdomain.com', 'stars-smtp-mailer'); ?>"
                              value="<?php echo esc_attr($is_edit ? $e_result['reply_to'] : ''); ?>"
                              name="reply_to" />
                           <p class="stars-input-tooltip">
                              <?php esc_html_e('Replies will be directed to this address instead of From Email.', 'stars-smtp-mailer'); ?>
                           </p>
                        </div>
                     </div>

                     <div class="form-group">
                        <label for="cc"><?php esc_html_e('CC', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <input type="email" id="cc"
                              placeholder="<?php echo esc_attr__('e.g. copy@yourdomain.com', 'stars-smtp-mailer'); ?>"
                              value="<?php echo esc_attr($is_edit ? $e_result['cc'] : ''); ?>" name="cc" />
                           <p class="stars-input-tooltip">
                              <?php esc_html_e("Every email from this account will be CC'd to this address.", 'stars-smtp-mailer'); ?>
                           </p>
                        </div>
                     </div>

                     <div class="form-group">
                        <label for="bcc"><?php esc_html_e('BCC', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <input type="email" id="bcc"
                              placeholder="<?php echo esc_attr__('e.g. archive@yourdomain.com', 'stars-smtp-mailer'); ?>"
                              value="<?php echo esc_attr($is_edit ? $e_result['bcc'] : ''); ?>" name="bcc" />
                           <p class="stars-input-tooltip">
                              <?php esc_html_e('Every email from this account will be blind-copied to this address.', 'stars-smtp-mailer'); ?>
                           </p>
                        </div>
                     </div>

                     <!-- ==========================================
                          Section 5: Advanced
                     =========================================== -->
                     <div class="stars-form-section-title">
                        <?php esc_html_e('Advanced', 'stars-smtp-mailer'); ?>
                     </div>

                     <div class="form-group">
                        <label for="add_header"><?php esc_html_e('Additional Headers', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <textarea id="add_header"
                              placeholder="<?php echo esc_attr__('MIME-Version: 1.0, Content-Type: text/html; charset=UTF-8', 'stars-smtp-mailer'); ?>"
                              name="add_header" cols="30" rows="4"
                              class="form-control no-resize"><?php echo esc_textarea($is_edit ? $e_result['add_header'] : ''); ?></textarea>
                           <p class="stars-input-tooltip">
                              <?php esc_html_e('Separate multiple headers with a comma. Example:', 'stars-smtp-mailer'); ?>
                              <code>MIME-Version: 1.0, Content-Type: text/html; charset=UTF-8</code>
                           </p>
                        </div>
                     </div>

                     <!-- Submit -->
                     <div class="form-group">
                        <label></label>
                        <div class="input-area stars-form-actions">
                           <input type="hidden" value="form-submit" name="form-action" />
                           <?php if ($is_edit) : ?>
                              <input type="submit" class="button button-primary" name="update" id="submit"
                                 value="<?php echo esc_attr__('Save Changes', 'stars-smtp-mailer'); ?>" />
                           <?php else : ?>
                              <input type="submit" class="button button-primary" name="add_new" id="submit"
                                 value="<?php echo esc_attr__('Add Account', 'stars-smtp-mailer'); ?>" />
                           <?php endif; ?>
                           <a href="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-accounts')); ?>" class="button button-secondary">
                              <?php esc_html_e('Cancel', 'stars-smtp-mailer'); ?>
                           </a>
                           <?php wp_nonce_field('stars_smtpm-add_edit_account'); ?>
                        </div>
                     </div>

                  </div><!-- /.wrapper -->
               </form>
            </div><!-- /.sidebar-content -->
         </div><!-- /.wrap-body -->
      </div><!-- /.stars_wrap -->

      <div class="stars_footer">
         <a href="https://myriadsolutionz.com/" target="_blank">
            <img src="<?php echo esc_url(STARS_SMTPM_MYRIAD_LOGO); ?>" alt="logo" title="Myriad Solutionz" />
         </a>
      </div>
   </div><!-- /#wpbody-content -->
</div><!-- /#wpbody -->

<script type="text/javascript">
document.title = '<?php echo esc_js($title); ?>';

/* Password visibility toggle */
(function () {
   document.querySelectorAll('.stars-pw-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
         var input = document.getElementById(btn.dataset.target);
         if (!input) return;
         var isHidden = input.type === 'password';
         input.type = isHidden ? 'text' : 'password';
         btn.querySelector('.pw-icon-show').style.display = isHidden ? 'none' : '';
         btn.querySelector('.pw-icon-hide').style.display = isHidden ? '' : 'none';
      });
   });
})();
</script>
