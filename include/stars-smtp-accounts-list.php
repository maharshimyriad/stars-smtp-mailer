<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$path = STARS_SMTPM_PLUGIN_DIR.'/action/stars-class-table-layout.php';
include ($path);    
$Show_List_Table->set_tablename(STARS_SMTPM_SMTP_SETTINGS);
$Show_List_Table->set_id('id');
$Show_List_Table->remove_table_columns(array('id','from_name','reply_to','cc','bcc','add_header','pass','smtp_date'));    
$Show_List_Table->prepare_items();
global $isAdmin; ?>

<div id="wpbody" role="main">
    <div id="wpbody-content" aria-label="<?php esc_attr_e('Main content', 'stars-smtp-mailer'); ?>" tabindex="0">
    <div class="wrap stars-smtp-account-list">
        <div id="icon-users" class="icon32"></div>
        <h1 class="wp-heading-inline"><?php echo esc_html__('SMTP Accounts', 'stars-smtp-mailer'); ?></h1>
        <?php if (isset($_SESSION['acc_msg']) && !empty($_SESSION['acc_msg'])){ ?>
            <div class="updated below-h2 stars_save_msg" aria-live="polite"><p><strong><?php echo wp_kses($_SESSION['acc_msg'], array('a' => array('href' => array(), 'class' => array()))); ?></strong></p></div>
        <?php unset($_SESSION['acc_msg']);
        } elseif (isset($_SESSION['acc_err']) && !empty($_SESSION['acc_err'])){ ?>
            <div class="error below-h2 stars_save_msg" aria-live="assertive"><p><strong><?php echo esc_html($_SESSION['acc_err']); ?></strong></p></div>
        <?php unset($_SESSION['acc_err']);
        } ?> 
        <a href="?page=stars-smtpm-new-account" class="page-title-action"><?php echo esc_html__('Add New', 'stars-smtp-mailer'); ?></a>
        <form method="POST" name="smtp_accounts_list" aria-label="<?php esc_attr_e('SMTP Accounts List Table', 'stars-smtp-mailer'); ?>">
        <?php $Show_List_Table->display(); ?>
        </form>
        <input type="hidden" id="check_admin" value="<?php echo (!$isAdmin ? 0 : 1); ?>" />
    </div> 
    </div>
</div>
<script type="text/javascript">
    var Permission = true;
    <?php if(!$isAdmin ){ ?>
        Permission = false;
    <?php } ?>
    jQuery(document).ready(function($){        
        $(".smtp-activation").click(function(){
            if(Permission === true){
                $(this).after("<img src='<?php echo esc_url(STARS_SMTPM_AJAX_LOADER); ?>' id='ajax-load' style='position: relative;top: 6px;right: -10px;' alt='<?php echo esc_attr__('Loading', 'stars-smtp-mailer'); ?>' />");
                var status = 1;
                if($(this).hasClass('deactivate')) status = 0;
                $.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: {
                        action: "stars_smtpm_change_status",
                        // Fix #5: include CSRF nonce
                        nonce: (typeof starsSmtpNotify !== 'undefined' ? starsSmtpNotify.status_change_nonce : ''),
                        status: status,
                        id: $(this).attr('id')
                    },
                    success: function(response) {
                        window.location = '?page=stars-smtpm-accounts'; 
                    }
                });
            }else{
                OpenPopup('<?php echo esc_js(__('Access Restricted', 'stars-smtp-mailer')); ?>','<?php echo esc_js(__('This feature is available in PRO version!', 'stars-smtp-mailer')); ?>');    
            }
        });        
        $(".stars-smtp-account-list input[type='checkbox']").click(function(e){
            if(Permission === false){
                jQuery(this).removeProp("checked").change();
                OpenPopup('<?php echo esc_js(__('Access Restricted', 'stars-smtp-mailer')); ?>','<?php echo esc_js(__('This feature is available in PRO version!', 'stars-smtp-mailer')); ?>');
            }                      
        });        
    });    
</script>
