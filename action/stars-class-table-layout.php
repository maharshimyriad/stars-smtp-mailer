<?php
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly
$Show_List_Table = new STARS_SMTPM_Show_List_Table();

class STARS_SMTPM_Show_List_Table extends WP_List_Table
{
    private $table_name;
    private $unique_id;
    private $remove_columns = array();
    public function __construct($args = array())
    {
        $args = wp_parse_args(
            $args,
            array(
                'plural' => "plural",
                'singular' => '',
                'ajax' => false,
                'screen' => null,
            )
        );
        $this->screen = convert_to_screen($args['screen']);
        add_filter("manage_{$this->screen->id}_columns", array($this, 'get_columns'), 0);
        if (!$args['plural']) {
            $args['plural'] = $this->screen->base;
        }
        $args['plural'] = sanitize_key($args['plural']);
        $args['singular'] = sanitize_key($args['singular']);
        $this->_args = $args;

    }
    public function set_tablename($tableName)
    {
        $this->table_name = $tableName;
    }
    public function remove_table_columns($columns)
    {
        $this->remove_columns = $columns;
    }
    public function set_id($id)
    {
        $this->unique_id = $id;
    }

    public function column_from_email($item)
    {
        if ($this->table_name == STARS_SMTPM_SMTP_SETTINGS) {
            // Fix: use current_user_can() — is_admin() only checks page context, not user permissions
            if (current_user_can('manage_options')) {
                $base_url     = '?page=stars-smtpm-accounts&action=delete&id=' . intval($item[$this->unique_id]);
                $complete_url = wp_nonce_url($base_url, 'delete-log_' . $item[$this->unique_id]);
                $actions = array(
                    'edit'   => sprintf(
                        '<a href="%s">%s</a>',
                        esc_url(admin_url('admin.php?page=stars-smtpm-new-account&action=edit&id=' . intval($item[$this->unique_id]))),
                        esc_html__('Edit', 'stars-smtp-mailer')
                    ),
                    'delete' => sprintf(
                        '<a href="%s" class="confirm-delete" data-value="account" data-id="%s">%s</a>',
                        esc_url($complete_url),
                        esc_attr($item[$this->unique_id]),
                        esc_html__('Delete', 'stars-smtp-mailer')
                    ),
                );
            } else {
                $actions = array(
                    'edit'   => '<a href="javascript:void(0);" class="tooltip-toggle" title="&lt;p&gt;' . esc_attr__('This feature is available in PRO version!', 'stars-smtp-mailer') . '&lt;/p&gt;">' . esc_html__('Edit', 'stars-smtp-mailer') . '</a>',
                    'delete' => '<a href="javascript:void(0);" class="tooltip-toggle" title="&lt;p&gt;' . esc_attr__('This feature is available in PRO version!', 'stars-smtp-mailer') . '&lt;/p&gt;">' . esc_html__('Delete', 'stars-smtp-mailer') . '</a>',
                );
            }
            return sprintf('%s %s', esc_html($item['from_email']), $this->row_actions($actions));
        }
        return esc_html($item['from_email']);
    }
    public function column_sub($item)
    {
        if ($this->table_name == STARS_SMTPM_EMAILS_LOG) {
            // Fix: use current_user_can() — is_admin() only checks page context, not user permissions
            if (current_user_can('manage_options')) {
                $base_url     = '?page=stars-smtpm-email-log&action=delete&id=' . intval($item[$this->unique_id]);
                $complete_url = wp_nonce_url($base_url, 'delete-log_' . $item[$this->unique_id]);
                $actions = array(
                    'view'   => sprintf(
                        '<a href="#TB_inline?width=600&height=550&inlineId=my-content-%s" class="thickbox">%s</a>',
                        intval($item[$this->unique_id]),
                        esc_html__('View', 'stars-smtp-mailer')
                    ),
                    'delete' => sprintf(
                        '<a href="%s" class="confirm-delete" data-value="log">%s</a>',
                        esc_url($complete_url),
                        esc_html__('Delete', 'stars-smtp-mailer')
                    ),
                );
            } else {
                $actions = array(
                    'view'   => '<a href="javascript:void(0);" class="tooltip-toggle" title="&lt;p&gt;' . esc_attr__('This feature is available in PRO version!', 'stars-smtp-mailer') . '&lt;/p&gt;">' . esc_html__('View', 'stars-smtp-mailer') . '</a>',
                    'delete' => '<a href="javascript:void(0);" class="tooltip-toggle" title="&lt;p&gt;' . esc_attr__('This feature is available in PRO version!', 'stars-smtp-mailer') . '&lt;/p&gt;">' . esc_html__('Delete', 'stars-smtp-mailer') . '</a>',
                );
            }
            $actions['resend'] = '<a href="javascript:void(0);" class="tooltip-toggle" title="&lt;p&gt;' . esc_attr__('This feature is available in PRO version!', 'stars-smtp-mailer') . '&lt;/p&gt;">' . esc_html__('Resend', 'stars-smtp-mailer') . '</a>';

            // Fix: escape subject before output
            return sprintf('%s %s', esc_html(stripslashes($item['sub'])), $this->row_actions($actions));
        }
        return esc_html(stripslashes($item['sub']));
    }
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $handle_delete = $this->process_bulk_action();
        $data = $this->table_data();
        $_j = 0;
        for ($_i = 1; $_i <= count($data); $_i++) {
            if ($this->table_name == STARS_SMTPM_SMTP_SETTINGS) {
                $status = ($data[$_j]['status'] == 0 ? 'Activate' : 'Deactivate');
                $class  = ($data[$_j]['status'] == 0 ? 'stars-btn-green' : 'stars-btn-red');
                unset($data[$_j]['status']);
                // Fix: escape all DB-sourced values injected into HTML attributes/content
                $data[$_j]['status'] = '<button type="button" id="' . esc_attr($data[$_j]['id']) . '" class="smtp-activation button stars-btn-width ' . esc_attr(strtolower($status)) . ' ' . esc_attr($class) . ' ">' . esc_html($status) . '</button>';
                $auth = ($data[$_j]['auth'] == 0 ? 'False' : 'True');
                unset($data[$_j]['auth']);
                $data[$_j]['auth']       = esc_html($auth);
                $encryption              = strtoupper($data[$_j]['encryption']);
                unset($data[$_j]['encryption']);
                $data[$_j]['encryption'] = esc_html($encryption);
            } else if ($this->table_name == STARS_SMTPM_EMAILS_LOG) {
                $status = '<p class="email-status"><span class="' . esc_attr( strtolower( $data[$_j]['status'] ) ) . '">' . esc_html( ucwords( strtolower( $data[$_j]['status'] ) ) ) . '</span>' . ( $data[$_j]['status'] === 'Unsent' ? '&nbsp;&nbsp;&nbsp;<span class="tooltip-toggle" title="<p>' . esc_attr( $data[$_j]['debug_op'] ) . '</p>">!</span>' : '' ) . '</p>';
                $body = $data[$_j]['mail_body'];
                $from = $data[$_j]['from_email'];
                $to   = $data[$_j]['email_id'];

                $Attachmetns = '';
                $attachmetns = stars_smtpm_decode_attachment( $data[$_j]['attachment'] );
                if (is_array($attachmetns)) {
                    foreach ($attachmetns as $name => $url) {
                        $Attachmetns .= '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($name) . '</a><br>';
                    }
                }

                unset($data[$_j]['status']);
                $data[$_j]['status']     = $status;
                $data[$_j]['attachment'] = $Attachmetns;
                $data[$_j]['from']       = esc_html($from);
                // Fix #7: body is placed inside a hidden div for the thickbox modal — escape it properly
                $data[$_j]['to']         = esc_html($to)
                    . "<div style='display:none' id='my-content-" . intval($data[$_j]['log_id']) . "'>"
                    . "<div>" . wp_kses_post($body) . "</div></div>";
                $data[$_j]['body']       = $body;
                $data[$_j]['date_time']  = esc_html(gmdate(' D , d M Y h:i A', strtotime($data[$_j]['mail_date'])));
                $data[$_j]['details']    = '<style>.email-details span{display:block}</style><p class="email-details">'
                    . (trim($data[$_j]['reply_to']) != '' ? '<span><strong>Reply To: </strong>' . esc_html($data[$_j]['reply_to']) . '</span>' : '')
                    . (trim($data[$_j]['cc'])       != '' ? '<span><strong>CC: </strong>'       . esc_html($data[$_j]['cc'])       . '</span>' : '')
                    . (trim($data[$_j]['bcc'])      != '' ? '<span><strong>BCC: </strong>'      . esc_html($data[$_j]['bcc'])      . '</span>' : '')
                    . ($data[$_j]['mail_type'] == 'test' ? '<span class="test_email">Test Email</span>' : '')
                    . '</p>';
            }
            $_j++;
        }

        usort($data, array(&$this, 'sort_data'));

        $user = get_current_user_id();
        $screen = get_current_screen();
        if ($this->table_name == STARS_SMTPM_SMTP_SETTINGS || $this->table_name == STARS_SMTPM_EMAILS_LOG) {
            $option = $screen->get_option('per_page', 'option');
            $per_page = get_user_meta($user, $option, true);
            if (empty($per_page) || $per_page < 1) {
                $per_page = $screen->get_option('per_page', 'default');
            }
        } else {
            $per_page = 50;
        }
        $perPage = $per_page;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);
        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page' => $perPage
        ));
        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }
    public function get_columns()
    {
        $result = $this->get_table_columns();
        $remove_col = $this->remove_columns;
        $columns['cb'] = '<input type="checkbox" />';

        if ($this->table_name == STARS_SMTPM_SMTP_SETTINGS) {
            foreach ($result as $key => $value) {
                if (isset($value['Field']) && !in_array($value['Field'], $remove_col)) {
                    switch ($value['Field']) {
                        case 'from_email':
                            $label = __('From Email', 'stars-smtp-mailer');
                            break;
                        case 'smtp_host':
                            $label = __('SMTP Host', 'stars-smtp-mailer');
                            break;
                        case 'smtp_port':
                            $label = __('SMTP Port', 'stars-smtp-mailer');
                            break;
                        case 'encryption':
                            $label = __('Encryption', 'stars-smtp-mailer');
                            break;
                        case 'auth':
                            $label = __('Authentication', 'stars-smtp-mailer');
                            break;
                        case 'username':
                            $label = __('Username', 'stars-smtp-mailer');
                            break;
                        case 'status':
                            $label = __('Status', 'stars-smtp-mailer');
                            break;
                        default:
                            $label = ucwords(str_replace("_", " ", $value['Field']));
                            break;
                    }
                    $columns[$value['Field']] = $label;
                }
            }
        } else if ($this->table_name == STARS_SMTPM_EMAILS_LOG) {
            $columns['sub'] = __('Title', 'stars-smtp-mailer');
            $columns['from'] = __('From', 'stars-smtp-mailer');
            $columns['to'] = __('To', 'stars-smtp-mailer');
            $columns['details'] = __('Email Headers', 'stars-smtp-mailer');
            $columns['date_time'] = __('Date Sent', 'stars-smtp-mailer');
            $columns['status'] = __('Status', 'stars-smtp-mailer');
            $columns['attachment'] = __('Attachment', 'stars-smtp-mailer');
        }

        return $columns;
    }

    public function get_hidden_columns()
    {
        return array();
    }
    public function get_sortable_columns()
    {
        $sort_columns = array();
        if ($this->table_name == STARS_SMTPM_EMAILS_LOG) {
            $sort_columns = array(
                'to' => array('email_id', false),
                'sub' => array('sub', false),
                'date_time' => array("mail_date", false),
                'status' => array("status", false)
            );
        } else if ($this->table_name == STARS_SMTPM_SMTP_SETTINGS) {
            $sort_columns = array('from_email' => array('from_email', false), 'username' => array('username', false));
        }

        return $sort_columns;
    }
    private function table_data()
    {
        $table_data = $this->get_result();
        return $table_data;
    }
    /**
     * Status column — contains pre-escaped HTML (button or status badge).
     * Must NOT be passed through esc_html() again.
     */
    public function column_status($item)
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is escaped in prepare_items()
        return $item['status'];
    }

    /**
     * Attachment column — contains pre-escaped anchor tags built in prepare_items().
     */
    public function column_attachment($item)
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is escaped in prepare_items()
        return isset($item['attachment']) ? $item['attachment'] : '';
    }

    /**
     * To column — contains pre-escaped text + hidden thickbox div built in prepare_items().
     */
    public function column_to($item)
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is escaped in prepare_items()
        return isset($item['to']) ? $item['to'] : '';
    }

    /**
     * Details column — contains pre-escaped HTML built in prepare_items().
     */
    public function column_details($item)
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is escaped in prepare_items()
        return isset($item['details']) ? $item['details'] : '';
    }

    public function column_default($item, $column_name)
    {
        return esc_html( isset($item[$column_name]) ? $item[$column_name] : '' );
    }
    private function sort_data($a, $b)
    {
        // Fix #12: whitelist allowed sort columns to prevent unvalidated GET input
        $allowed_orderby_log      = array('log_id', 'email_id', 'sub', 'mail_date', 'status');
        $allowed_orderby_settings = array('id', 'from_email', 'username');

        $default_orderby = ($this->table_name == STARS_SMTPM_EMAILS_LOG ? 'log_id' : 'id');
        $orderby         = $default_orderby;

        if (!empty($_GET['orderby'])) {
            $requested = sanitize_key($_GET['orderby']);
            $allowed   = ($this->table_name == STARS_SMTPM_EMAILS_LOG)
                ? $allowed_orderby_log
                : $allowed_orderby_settings;
            if (in_array($requested, $allowed, true)) {
                $orderby = $requested;
            }
        }

        $order = 'desc';
        if (!empty($_GET['order']) && in_array(strtolower(sanitize_key($_GET['order'])), array('asc', 'desc'), true)) {
            $order = strtolower(sanitize_key($_GET['order']));
        }

        $result = strnatcmp($a[$orderby], $b[$orderby]);
        return ($order === 'asc') ? $result : -$result;
    }
    public function get_bulk_actions()
    {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    public function set_column_labels($labels)
    {
        $this->column_labels = $labels;
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="table_dlt_id[]" value="%s" />',
            esc_attr($item[$this->unique_id])
        );
    }
    public function process_bulk_action()
    {
        global $wpdb;
        // Fix: use current_user_can() instead of is_admin() which only checks page context, not permissions
        if (isset($_POST['table_dlt_id']) && !empty($_POST['table_dlt_id']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural']) && current_user_can('manage_options')) {
            $action = $this->current_action();
            switch ($action) {
                case 'delete':
                    foreach ($_POST['table_dlt_id'] as $key => $value) {
                        if ($this->table_name == STARS_SMTPM_EMAILS_LOG) {
                            $table_name  = STARS_SMTPM_EMAILS_LOG;
                            $column_name = $this->unique_id;

                            // Fix: select only what's needed — avoids pulling sensitive columns into memory
                            $getRow = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT attachment FROM {$table_name} WHERE {$column_name} = %d",
                                    intval($value)
                                )
                            );

                            if (isset($getRow->attachment) && $getRow->attachment != '') {
                                $attachment = stars_smtpm_decode_attachment($getRow->attachment);
                                if (is_array($attachment)) {
                                    foreach ($attachment as $att) {
                                        $filename  = basename($att);
                                        $file_path = stars_smtpm_get_upload_path() . '/' . $filename;
                                        if (file_exists($file_path)) {
                                            unlink($file_path);
                                        }
                                    }
                                }
                            }
                        }

                        $wpdb->delete($this->table_name, array($this->unique_id => intval($value)));
                    }
                    break;
                default:
                    return;
            }
        }
        return;
    }
    function extra_tablenav($which)
    {
        if ($this->table_name == STARS_SMTPM_SMTP_SETTINGS)
            return;
        global $wpdb;
        if ($which == "top") {
            $min = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT mail_date FROM {$this->table_name} ORDER BY mail_date ASC LIMIT %d",
                    1
                )
            );
            $sdate = (isset($_POST['sdate'])
                ? sanitize_text_field($_POST['sdate'])
                : (isset($min->mail_date) && $min->mail_date != '' ? gmdate('d/m/Y', strtotime($min->mail_date)) : gmdate('d/m/Y')));
            $edate = (isset($_POST['edate']) ? sanitize_text_field($_POST['edate']) : gmdate('d/m/Y'));
            ?>
            <div class="alignleft actions">
                <input placeholder="Date From" name="sdate" type="text" value="<?php echo esc_attr($sdate); ?>"
                    class="stars_datepicker" id="sdate" />
                <input placeholder="Date To" name="edate" type="text" value="<?php echo esc_attr($edate); ?>"
                    class="stars_datepicker" id="edate" />
                <input type="submit" name="filter_table_action" id="post-query-submit" class="button" value="Filter" />
            </div>
            <?php
        }
    }
    public function get_result()
    {
        global $wpdb;
        // Fix: use current_user_can() instead of is_admin() for the delete gate
        if (
            isset($_GET['action']) && $_GET['action'] == 'delete'
            && isset($_GET['id'])
            && isset($_GET['_wpnonce'])
            && wp_verify_nonce($_GET['_wpnonce'], 'delete-log_' . $_GET['id'])
            && current_user_can('manage_options')
        ) {
            $id = intval($_GET['id']);

            if ($this->table_name == STARS_SMTPM_EMAILS_LOG) {
                $table_name  = STARS_SMTPM_EMAILS_LOG;
                $column_name = $this->unique_id;

                // Fix: select only the attachment column — no need to pull all fields into memory
                $getRow = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT attachment FROM {$table_name} WHERE {$column_name} = %d",
                        $id
                    )
                );

                if (isset($getRow->attachment) && $getRow->attachment != '') {
                    $attachment = stars_smtpm_decode_attachment($getRow->attachment);
                    if (is_array($attachment)) {
                        foreach ($attachment as $att) {
                            $filename  = basename($att);
                            $file_path = stars_smtpm_get_upload_path() . '/' . $filename;
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                    }
                }
            }

            $wpdb->delete($this->table_name, array($this->unique_id => $id));

            // Safely build the redirect URL
            $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            $redirect_url = admin_url("admin.php?page=" . urlencode($page));
            ?>
            <script>
                window.history.replaceState({}, "", <?php echo wp_json_encode($redirect_url); ?>);
            </script>
            <?php
        }
        if (isset($_POST['sdate']) && isset($_POST['edate']) && $this->table_name == STARS_SMTPM_EMAILS_LOG) {

            // Fix #12: sanitize date inputs before use
            $date_tmp = sanitize_text_field(str_replace('/', '-', $_POST['sdate']));
            $sdate    = gmdate('Y-m-d', strtotime($date_tmp));

            $date_tmp = sanitize_text_field(str_replace('/', '-', $_POST['edate']));
            $edate    = gmdate('Y-m-d', strtotime($date_tmp));

            $table_name = $this->table_name;
            $start_datetime = $sdate . ' 00:00:00';
            $end_datetime = $edate . ' 23:59:59';

            $cur_form_res = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE (mail_date BETWEEN %s AND %s) LIMIT 200",
                    $start_datetime,
                    $end_datetime
                ),
                ARRAY_A
            );
        } else if (isset($_GET['s']) && trim($_GET['s']) != "" && $this->table_name == STARS_SMTPM_EMAILS_LOG) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $table_name = $this->table_name;

            $cur_form_res = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE
            from_name LIKE %s OR
            from_email LIKE %s OR
            reply_to LIKE %s OR
            email_id LIKE %s OR
            cc LIKE %s OR
            bcc LIKE %s OR
            sub LIKE %s OR
            mail_body LIKE %s OR
            status LIKE %s OR
            mail_type LIKE %s
            LIMIT 200",
                    $search,
                    $search,
                    $search,
                    $search,
                    $search,
                    $search,
                    $search,
                    $search,
                    $search,
                    $search
                ),
                ARRAY_A
            );
        } else {
            $limit = ($this->table_name == STARS_SMTPM_SMTP_SETTINGS ? 3 : 200);
            $table_name = $this->table_name;

            $cur_form_res = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
        }
        return ($cur_form_res ? $cur_form_res : array());
    }

    public function get_table_columns()
    {
        global $wpdb;
        $table_name = $this->table_name;

        $cur_form_res = $wpdb->get_results(
            "SHOW COLUMNS FROM {$table_name}",
            ARRAY_A
        );

        return ($cur_form_res ? $cur_form_res : array());
    }
}
