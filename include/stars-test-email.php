<?php
if (!defined('ABSPATH'))
   exit;
global $wpdb;
$submitted = $submittedErr = $submittedWarn = '';

if (
   isset($_POST['send_test'])
   && $_POST['send_test'] === __('Send Test Email', 'stars-smtp-mailer')
   && isset($_POST['_wpnonce'])
   && wp_verify_nonce($_POST['_wpnonce'], 'stars_smtpm-testing-email')
) {
   $to_email      = sanitize_email($_POST['to_email']);
   $email_subject = sanitize_text_field($_POST['email_subject']);
   $email_content = wp_kses_post(wp_unslash($_POST['email_content']));

   /* ----------------------------------------------------------
      MX record check — catch completely fake / unreachable domains
      before we even try to send. Catches things like test@notreal.xyz
      but NOT nonexistent users at real domains (e.g. ghost@gmail.com)
      since real mail servers accept those then bounce later.
   ---------------------------------------------------------- */
   $to_domain  = substr( strrchr( $to_email, '@' ), 1 );
   $mx_records = array();
   $mx_ok      = ( function_exists('getmxrr') && getmxrr( $to_domain, $mx_records ) );

   if ( ! $mx_ok ) {
      $submittedErr = sprintf(
         /* translators: %s: the recipient email domain */
         esc_html__( 'Cannot send: the domain "%s" has no mail servers (MX records). Please check the recipient address.', 'stars-smtp-mailer' ),
         esc_html( $to_domain )
      );
   } else {
      /* ----------------------------------------------------------
         Domain has MX records — proceed to send
      ---------------------------------------------------------- */
      $header = array('Content-Type' => 'Content-Type: text/html; charset=UTF-8');

      if (!empty($_POST['email_cc']))  $header['Cc']  = 'Cc:'  . sanitize_email($_POST['email_cc']);
      if (!empty($_POST['email_bcc'])) $header['Bcc'] = 'Bcc:' . sanitize_email($_POST['email_bcc']);

      $attached_files     = $_FILES;
      $plugin_upload_dir  = stars_smtpm_get_upload_path();
      $all_uploaded_files = stars_smtpm_move_uploaded_files($attached_files);
      $all_files_path     = array();

      if (!empty($all_uploaded_files)) {
         foreach ($all_uploaded_files as $uploaded_file) {
            $all_files_path[] = $plugin_upload_dir . '/' . $uploaded_file;
         }
      }

      update_option('_mail_type', 'test');
      $response = wp_mail($to_email, $email_subject, $email_content, $header, $all_files_path);

      if ($response) {
         $mail_log = stars_smtpm_get_mail_log($response);
         if ($mail_log && isset($mail_log['status']) && $mail_log['status'] == 'Unsent') {
            $submittedErr = esc_html__('Something went wrong. Please check the email log for details.', 'stars-smtp-mailer')
               . ' <span style="color:red;">'
               . ( isset($mail_log['debug_op']) ? esc_html($mail_log['debug_op']) : '' )
               . '</span>';
         } else {
            /* SUCCESS — but explain what "accepted" actually means */
            $submitted = sprintf(
               /* translators: %s: the recipient email address */
               esc_html__( 'Email accepted by your SMTP server and queued for delivery to %s.', 'stars-smtp-mailer' ),
               '<strong>' . esc_html($to_email) . '</strong>'
            );
            /* Separate warning explaining the limitation */
            $submittedWarn = esc_html__(
               'Note: "Accepted" means your SMTP server received the message — it does not guarantee the email reached the inbox. '
               . 'If the recipient address does not exist, your SMTP server will send a bounce/failure notice back to your sender address within a few minutes.',
               'stars-smtp-mailer'
            );
         }
      } else {
         $submittedErr = esc_html__('Something went wrong. Please check the email log for details.', 'stars-smtp-mailer');
      }
   }
}
?>
<div id="wpbody">
   <div id="wpbody-content">
      <h1><?php echo esc_html__('Send Test Email', 'stars-smtp-mailer'); ?></h1>

      <?php if ($submitted !== '') : ?>
         <div class="notice notice-success is-dismissible stars_save_msg">
            <p><?php echo wp_kses($submitted, array('strong' => array())); ?></p>
            <?php if ($submittedWarn !== '') : ?>
               <p class="stars-delivery-note">
                  <span class="dashicons dashicons-info" style="font-size:15px;vertical-align:middle;color:#dba617;"></span>
                  <?php echo esc_html($submittedWarn); ?>
               </p>
            <?php endif; ?>
         </div>
      <?php elseif ($submittedErr !== '') : ?>
         <div class="notice notice-error is-dismissible stars_save_msg">
            <p><?php echo wp_kses_post($submittedErr); ?></p>
         </div>
      <?php endif; ?>

      <!-- Info banner: always visible, explains SMTP delivery reality -->
      <div class="notice notice-info stars-test-info-banner" style="margin-bottom:16px; margin-left:0;">
         <p>
            <span class="dashicons dashicons-email-alt" style="font-size:15px;vertical-align:middle;"></span>
            <strong><?php esc_html_e('How test sending works:', 'stars-smtp-mailer'); ?></strong>
            <?php esc_html_e(
               'This form sends a real email through your configured SMTP account. '
               . '"Accepted" means your SMTP server queued it — not that it landed in the inbox. '
               . 'If the address does not exist, you will receive a bounce email at your sender address.',
               'stars-smtp-mailer'
            ); ?>
         </p>
      </div>

      <div style="overflow:hidden;width:100%;">
         <div class="wrap stars_wrap col-md-9 col-sm-12">
            <div class="wrap-body">
               <div class="sidebar-content">
                  <form action="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-test-mail')); ?>"
                     method="POST" id="send_test_form" enctype="multipart/form-data">
                     <div class="wrapper" id="header">

                        <div class="form-group">
                           <label for="to_email">
                              <?php esc_html_e('To', 'stars-smtp-mailer'); ?>
                              <span class="req-star" aria-hidden="true">*</span>
                           </label>
                           <div class="input-area">
                              <input type="text" name="to_email" id="to_email"
                                 value="<?php echo isset($_POST['to_email']) ? esc_attr(sanitize_email($_POST['to_email'])) : ''; ?>"
                                 placeholder="<?php esc_attr_e('recipient@example.com', 'stars-smtp-mailer'); ?>"
                                 class="required email" />
                              <?php if (isset($_GET['id'])) : ?>
                                 <input type="hidden" name="stars_test_row_id" id="stars_test_row_id"
                                    value="<?php echo esc_attr(absint($_GET['id'])); ?>" />
                              <?php endif; ?>
                              <p class="stars-input-tooltip"><?php esc_html_e('Enter the address you want to receive the test email.', 'stars-smtp-mailer'); ?></p>
                           </div>
                        </div>

                        <div class="form-group">
                           <label for="email_cc"><?php esc_html_e('CC', 'stars-smtp-mailer'); ?></label>
                           <div class="input-area">
                              <input type="text" name="email_cc" id="email_cc"
                                 value="<?php echo isset($_POST['email_cc']) ? esc_attr(sanitize_email($_POST['email_cc'])) : ''; ?>"
                                 class="email" />
                           </div>
                        </div>

                        <div class="form-group">
                           <label for="email_bcc"><?php esc_html_e('BCC', 'stars-smtp-mailer'); ?></label>
                           <div class="input-area">
                              <input type="text" name="email_bcc" id="email_bcc"
                                 value="<?php echo isset($_POST['email_bcc']) ? esc_attr(sanitize_email($_POST['email_bcc'])) : ''; ?>"
                                 class="email" />
                           </div>
                        </div>

                        <div class="form-group">
                           <label for="email_subject">
                              <?php esc_html_e('Subject', 'stars-smtp-mailer'); ?>
                              <span class="req-star" aria-hidden="true">*</span>
                           </label>
                           <div class="input-area">
                              <input type="text" name="email_subject" id="email_subject"
                                 value="<?php echo isset($_POST['email_subject']) ? esc_attr(sanitize_text_field($_POST['email_subject'])) : esc_attr__('Test Email', 'stars-smtp-mailer'); ?>"
                                 class="required" />
                           </div>
                        </div>

                        <div class="form-group">
                           <label for="email_content">
                              <?php esc_html_e('Body', 'stars-smtp-mailer'); ?>
                              <span class="req-star" aria-hidden="true">*</span>
                           </label>
                           <div class="input-area">
                              <textarea
                                 id="email_content"
                                 name="email_content"
                                 rows="8"
                                 class="required stars-test-body"
                                 placeholder="<?php esc_attr_e('Enter email body (plain text or HTML)', 'stars-smtp-mailer'); ?>"
                              ><?php
                                 if (!empty($_POST['email_content'])) {
                                    echo esc_textarea(wp_kses_post(wp_unslash($_POST['email_content'])));
                                 } else {
                                    echo esc_textarea( sprintf(
                                       /* translators: %s: site name */
                                       __('This is a test email from %s — Stars SMTP Mailer', 'stars-smtp-mailer'),
                                       get_bloginfo('name')
                                    ));
                                 }
                              ?></textarea>
                              <p class="stars-input-tooltip"><?php esc_html_e('Plain text or HTML accepted.', 'stars-smtp-mailer'); ?></p>
                           </div>
                        </div>

                        <div class="form-group">
                           <label for="email_attach"><?php esc_html_e('Attachments', 'stars-smtp-mailer'); ?></label>
                           <div class="input-area">
                              <input type="file" name="email_attach[]" id="email_attach" multiple="multiple" />
                              <p class="stars-input-tooltip"><?php esc_html_e('Optional. Executable files (.php, .sh, etc.) are blocked.', 'stars-smtp-mailer'); ?></p>
                           </div>
                        </div>

                        <div class="form-group">
                           <label></label>
                           <div class="input-area stars-form-actions">
                              <input type="submit" class="button button-primary" name="send_test" id="send_test"
                                 value="<?php echo esc_attr__('Send Test Email', 'stars-smtp-mailer'); ?>" />
                              <?php wp_nonce_field('stars_smtpm-testing-email'); ?>
                           </div>
                        </div>

                     </div>
                  </form>
               </div>
            </div>
         </div>

         <div class="col-md-3 col-sm-12">
            <div class="star-pro-version">
               <img src="<?php echo esc_url(STARS_SMTPM_PRO_LOGO); ?>"
                  alt="<?php esc_attr_e('banner', 'stars-smtp-mailer'); ?>"
                  title="<?php esc_attr_e('Stars SMTP Mailer Pro Version', 'stars-smtp-mailer'); ?>">
               <h2><?php esc_html_e('SMTP Mailer Pro Features (Coming Soon)', 'stars-smtp-mailer'); ?></h2>
               <div class="star-pro-version-features">
                  <ul>
                     <li><?php esc_html_e('Unlimited Emails Log', 'stars-smtp-mailer'); ?></li>
                     <li><?php esc_html_e('Resend Emails', 'stars-smtp-mailer'); ?></li>
                     <li><?php esc_html_e('Track Receipt ( Read / Unread Email )', 'stars-smtp-mailer'); ?></li>
                     <li><?php esc_html_e('Add Unlimited SMTP Accounts', 'stars-smtp-mailer'); ?></li>
                     <li><?php esc_html_e('Unlimited Active SMTP Accounts', 'stars-smtp-mailer'); ?></li>
                     <li><?php esc_html_e('Email Tracking', 'stars-smtp-mailer'); ?></li>
                     <li><?php esc_html_e('Send emails via multiple accounts', 'stars-smtp-mailer'); ?></li>
                     <li><?php esc_html_e('Advanced Sending Rules', 'stars-smtp-mailer'); ?></li>
                     <li>
                        <div class="stars-pro-notification-text"><?php esc_html_e('Almost There – We\'ll Notify You When It\'s Ready', 'stars-smtp-mailer'); ?></div>
                        <a href="#" class="button-primary" id="starsopenModal"><?php esc_html_e('Get Notified', 'stars-smtp-mailer'); ?></a>
                     </li>
                  </ul>
               </div>

               <div class="stars-pro-notification-modal-overlay" id="stars-pro-notification-modal">
                  <div class="stars-pro-notification-modal-box">
                     <h2><?php esc_html_e('Join the Waitlist', 'stars-smtp-mailer'); ?></h2>
                     <input id="stars_user_mail" type="email"
                        placeholder="<?php esc_attr_e('Enter your email', 'stars-smtp-mailer'); ?>" />
                     <input id="wp_username" type="hidden" name="wp_username"
                        value="<?php echo esc_attr(wp_get_current_user()->user_login); ?>" />
                     <input id="wp_first_name" type="hidden" name="wp_first_name"
                        value="<?php echo esc_attr(get_user_meta(wp_get_current_user()->ID, 'first_name', true)); ?>" />
                     <input id="wp_last_name" type="hidden" name="wp_last_name"
                        value="<?php echo esc_attr(get_user_meta(wp_get_current_user()->ID, 'last_name', true)); ?>" />
                     <button type="submit"><?php esc_html_e('Notify Me', 'stars-smtp-mailer'); ?></button>
                     <button class="close-modal"><?php esc_html_e('Cancel', 'stars-smtp-mailer'); ?></button>
                  </div>
               </div>
            </div>
         </div>

         <div class="stars_footer">
            <a href="https://myriadsolutionz.com/" target="_blank">
               <img src="<?php echo esc_url(STARS_SMTPM_MYRIAD_LOGO); ?>"
                  alt="<?php esc_attr_e('logo', 'stars-smtp-mailer'); ?>"
                  title="<?php esc_attr_e('Myriad Solutionz', 'stars-smtp-mailer'); ?>" />
            </a>
         </div>
      </div>
   </div>
</div>
