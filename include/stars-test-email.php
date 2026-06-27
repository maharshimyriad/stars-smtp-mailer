<?php
if (!defined('ABSPATH'))
   exit;
global $wpdb;
$site_url  = site_url();
$submitted = $submittedErr = '';

if (
   isset($_POST['send_test'])
   && $_POST['send_test'] === __('Send', 'stars-smtp-mailer')
   && isset($_POST['_wpnonce'])
   && wp_verify_nonce($_POST['_wpnonce'], 'stars_smtpm-testing-email')
) {
   $header = array('Content-Type' => 'Content-Type: text/html; charset=UTF-8');

   // Fix #6: sanitize CC / BCC before use
   if (!empty($_POST['email_cc'])) {
      $header['Cc'] = 'Cc:' . sanitize_email($_POST['email_cc']);
   }
   if (!empty($_POST['email_bcc'])) {
      $header['Bcc'] = 'Bcc:' . sanitize_email($_POST['email_bcc']);
   }

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

   // Fix #6: sanitize subject and body before passing to wp_mail
   $to_email      = sanitize_email($_POST['to_email']);
   $email_subject = sanitize_text_field($_POST['email_subject']);
   // wp_kses_post allows safe HTML in the body while stripping dangerous tags
   $email_content = wp_kses_post(wp_unslash($_POST['email_content']));

   $response = wp_mail($to_email, $email_subject, $email_content, $header, $all_files_path);

   if ($response) {
      $mail_log = stars_smtpm_get_mail_log($response);
      if ($mail_log && isset($mail_log['status']) && $mail_log['status'] == 'Unsent') {
         $submittedErr = esc_html__('Something went wrong. Please check email log to check what went wrong.', 'stars-smtp-mailer')
            . " <span style='color:red;'>" . esc_html__('Error', 'stars-smtp-mailer') . ': '
            . (isset($mail_log['debug_op']) ? esc_html($mail_log['debug_op']) : '') . '</span>';
      } else {
         $submitted = esc_html__('Test Email Sent.', 'stars-smtp-mailer');
      }
   } else {
      $submittedErr = esc_html__('Something went wrong. Please check email log to check what went wrong.', 'stars-smtp-mailer');
   }
}
?>
<div id="wpbody">
   <div id="wpbody-content">
      <h1><?php echo esc_html__('Send Test Email', 'stars-smtp-mailer'); ?></h1>
      <?php if ($submitted != '') { ?>
         <div class="updated notice is-dismissible stars_save_msg">
            <p><strong><?php echo esc_html($submitted); ?></strong></p>
         </div>
      <?php } elseif ($submittedErr != '') { ?>
         <div id="message" class="error is-dismissible stars_save_msg">
            <p><strong><?php echo wp_kses_post($submittedErr); ?></strong></p>
         </div>
      <?php } ?>
      <div style="overflow:hidden; width:100%;">
      <div class="wrap stars_wrap col-md-9 col-sm-12">
         <div class="wrap-body">
            <div class="sidebar-content ">
               <div style="clear:both"></div>
               <form action="<?php echo esc_url(admin_url('admin.php?page=stars-smtpm-test-mail')); ?>" method="POST"
                  id="send_test_form" enctype="multipart/form-data">
                  <div class="wrapper" id="header">
                     <div class="form-group">
                        <label for="to_email"><?php echo esc_html__('To:', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <input type="text" name="to_email" id="to_email" value="" class="required email" />
                           <?php if (isset($_GET['id'])) { ?>
                              <input type="hidden" name="stars_test_row_id" id="stars_test_row_id"
                                 value="<?php echo esc_attr(absint($_GET['id'])); ?>" />
                           <?php } ?>
                        </div>
                     </div>
                     <div class="form-group">
                        <label for="email_cc"><?php echo esc_html__('CC:', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <input type="text" name="email_cc" id="email_cc" value="" class="email" />
                        </div>
                     </div>
                     <div class="form-group">
                        <label for="email_bcc"><?php echo esc_html__('BCC:', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <input type="text" name="email_bcc" id="email_bcc" value="" class="email" />
                        </div>
                     </div>
                     <div class="form-group">
                        <label for="email_subject"><?php echo esc_html__('Subject:', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <input type="text" name="email_subject" id="email_subject" value="" class="required" />
                        </div>
                     </div>
                     <div class="form-group">
                        <label for="email_content"><?php echo esc_html__('Body:', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <textarea
                              id="email_content"
                              name="email_content"
                              rows="10"
                              class="required stars-test-body"
                              placeholder="<?php echo esc_attr__('Enter email body (HTML allowed)', 'stars-smtp-mailer'); ?>"
                           ><?php echo esc_textarea( sprintf(
                              /* translators: %s is the site name */
                              __( 'This is a Test Email from %s - Stars SMTP Mailer', 'stars-smtp-mailer' ),
                              get_bloginfo('name')
                           ) ); ?></textarea>
                           <p class="stars-input-tooltip"><?php esc_html_e('Plain text or HTML accepted.', 'stars-smtp-mailer'); ?></p>
                        </div>
                     </div>
                     <div class="form-group">
                        <label for="email_attach"><?php echo esc_html__('Add Attachments:', 'stars-smtp-mailer'); ?></label>
                        <div class="input-area">
                           <input type="file" name="email_attach[]" id="email_attach" value="" multiple="multiple" />
                        </div>
                     </div>
                     <div class="form-group">
                        <label></label>
                        <div class="input-area stars-form-actions">
                           <input type="submit" class="button button-primary" name="send_test" id="send_test"
                              value="<?php echo esc_attr__('Send Test Email', 'stars-smtp-mailer'); ?>"
                              onclick="return SetEmailBody();" />
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
               alt="<?php echo esc_attr__('banner', 'stars-smtp-mailer'); ?>"
               title="<?php echo esc_attr__('Stars SMTP Mailer Pro Version', 'stars-smtp-mailer'); ?>">
            <h2><?php echo esc_html__('SMTP Mailer Pro Features (Coming Soon)', 'stars-smtp-mailer'); ?></h2>
            <div class="star-pro-version-features">
               <ul>
                  <li><?php echo esc_html__('Unlimited Emails Log', 'stars-smtp-mailer'); ?></li>
                  <li><?php echo esc_html__('Resend Emails', 'stars-smtp-mailer'); ?></li>
                  <li><?php echo esc_html__('Track Receipt ( Read / Unread Email )', 'stars-smtp-mailer'); ?></li>
                  <li><?php echo esc_html__('Add Unlimited SMTP Accounts', 'stars-smtp-mailer'); ?></li>
                  <li><?php echo esc_html__('Unlimited Active SMTP Accounts', 'stars-smtp-mailer'); ?></li>
                  <li><?php echo esc_html__('Email Tracking', 'stars-smtp-mailer'); ?></li>
                  <li><?php echo esc_html__('Send emails via multiple accounts', 'stars-smtp-mailer'); ?></li>
                  <li><?php echo esc_html__('Advanced Sending Rules', 'stars-smtp-mailer'); ?></li>
                  <li>
                     <div class="stars-pro-notification-text">Almost There – We'll Notify You When It's Ready</div>
                     <a href="#" class="button-primary"
                        id="starsopenModal"><?php echo esc_html__('Get Notified', 'stars-smtp-mailer'); ?></a>
                  </li>
               </ul>
            </div>

            <div class="stars-pro-notification-modal-overlay" id="stars-pro-notification-modal">
               <div class="stars-pro-notification-modal-box">
                  <h2><?php echo esc_html__('Join the Waitlist', 'stars-smtp-mailer'); ?></h2>
                  <input id="stars_user_mail" type="email"
                     placeholder="<?php echo esc_attr__('Enter your email', 'stars-smtp-mailer'); ?>" />
                  <input id="wp_username" type="hidden"
                     name="wp_username"
                     value="<?php echo esc_attr(wp_get_current_user()->user_login); ?>" />
                  <input id="wp_first_name" type="hidden"
                     name="wp_first_name"
                     value="<?php echo esc_attr(get_user_meta(wp_get_current_user()->ID, 'first_name', true)); ?>" />
                  <input id="wp_last_name" type="hidden"
                     name="wp_last_name"
                     value="<?php echo esc_attr(get_user_meta(wp_get_current_user()->ID, 'last_name', true)); ?>" />
                  <button type="submit"><?php echo esc_html__('Notify Me', 'stars-smtp-mailer'); ?></button>
                  <button class="close-modal"><?php echo esc_html__('Cancel', 'stars-smtp-mailer'); ?></button>
               </div>
            </div>
         </div><!-- /.col-md-3 -->
      </div><!-- clearfix -->
      <div class="stars_footer">
         <a href="https://myriadsolutionz.com/" target="_blank">
            <img src="<?php echo esc_url(STARS_SMTPM_MYRIAD_LOGO); ?>" alt="<?php echo esc_attr('logo'); ?>"
               title="<?php echo esc_attr('Myriad Solutionz'); ?>" />
         </a>
      </div>
   </div>
</div>
