<?php if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, array("GFZarinPal_HANNANStd", "add_permissions"));
add_action('init', array('GFZarinPal_HANNANStd', 'init'));

require_once('functions.php');
require_once('database.php');
require_once('chart.php');

class GFZarinPal_HANNANStd
{

    //Dont Change this Parameter if you are legitimate !!!
    public static $author = "HANNANStd";

    // ------------------------GravityForms.IR-------------------------
    private static $version = "2.2.0";
    private static $min_gravityforms_version = "1.9.15";
    private static $config = null;

    // ------------------------GravityForms.IR-------------------------
    public static function init()
    {
        if (is_admin() && self::has_access()) {

            if (!class_exists("GFParsi")) {
                add_action('admin_notices', array('GFZarinPal_HANNANStd', 'admin_notice_persian_gf'));
                return false;
            }

            if (!self::is_gravityforms_supported()) {
                add_action('admin_notices', array('GFZarinPal_HANNANStd', 'admin_notice_gf_support'));
                return false;
            }

            if (function_exists('members_get_capabilities'))
                add_filter('members_get_capabilities', array("GFZarinPal_HANNANStd", "members_get_capabilities"));

            add_filter('gform_tooltips', array('GFZarinPal_HANNANStd', 'tooltips'));
            add_filter('gform_addon_navigation', array('GFZarinPal_HANNANStd', 'menu'));
            add_action('gform_entry_info', array('GFZarinPal_HANNANStd', 'payment_entry_detail'), 4, 2);
            add_action('gform_after_update_entry', array('GFZarinPal_HANNANStd', 'update_payment_entry'), 4, 2);

            if (get_option("gf_zarinpal_configured")) {
                add_filter('gform_form_settings_menu', array('GFZarinPal_HANNANStd', 'toolbar'), 10, 2);
                add_action('gform_form_settings_page_zarinpal', array('GFZarinPal_HANNANStd', 'zarinpal_form_settings_page'));
            }

            if (rgget("page") == "gf_settings") {
                RGForms::add_settings_page(array(
                        'name' => 'gf_zarinpal',
                        'tab_label' => __('درگاه زرین پال', 'gravityformszarinpal'),
                        'title' => __('تنظیمات درگاه زرین پال', 'gravityformszarinpal'),
                        'handler' => array('GFZarinPal_HANNANStd', 'settings_page'),
                    )
                );
            }

            if (self::is_zarinpal_page()) {
                wp_enqueue_script(array("sack"));
                self::setup();
            }

            if (in_array(RG_CURRENT_PAGE, array("admin-ajax.php"))) {
                add_action('wp_ajax_gf_zarinpal_update_feed_active', array('GFZarinPal_HANNANStd', 'update_feed_active'));
            }
        }
        if (get_option("gf_zarinpal_configured")) {
            add_filter("gform_pre_render", array("GFZarinPal_HANNANStd", "change_price"), 10, 1);
            add_filter("gform_confirmation", array("GFZarinPal_HANNANStd", "Request"), 1000, 4);
            add_filter("gform_disable_notification", array("GFZarinPal_HANNANStd", "delay_notifications"), 10, 4);
            add_filter("gform_disable_registration", array("GFZarinPal_HANNANStd", "delay_registration"), 10, 4);
            add_filter("gform_disable_post_creation", array("GFZarinPal_HANNANStd", "delay_posts"), 10, 3);
            add_filter("gform_is_delayed_pre_process_feed", array("GFZarinPal_HANNANStd", "delay_addons"), 10, 4);

            add_action('wp', array('GFZarinPal_HANNANStd', 'Verify'), 5);
        }

        add_filter("gform_logging_supported", array("GFZarinPal_HANNANStd", "set_logging_supported"));

        // --------------------------------------------------------------------------------------------
        add_filter('gf_payment_gateways', array("GFZarinPal_HANNANStd", 'gravityformszarinpal'), 2);
        do_action('gravityforms_gateways');
        do_action('gravityforms_zarinpal');
        // --------------------------------------------------------------------------------------------
    }


    // ------------------------GravityForms.IR-------------------------
    public static function admin_notice_persian_gf()
    {
        $class = 'notice notice-error';
        $message = sprintf(__("برای استفاده از درگاه های پرداخت گرویتی فرم نصب بسته فارسی ساز الزامی است . برای نصب فارسی ساز %sکلیک کنید%s.", "gravityformszarinpal"), '<a href="' . admin_url("plugin-install.php?tab=plugin-information&plugin=persian-gravity-forms&TB_iframe=true&width=772&height=884") . '">', '</a>');
        printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
    }

    // ------------------------GravityForms.IR-------------------------
    public static function admin_notice_gf_support()
    {
        $class = 'notice notice-error';
        $message = sprintf(__("درگاه زرین پال نیاز به گرویتی فرم نسخه %s به بالا دارد . برای بروز رسانی هسته گرویتی فرم به %sسایت گرویتی فرم فارسی%s مراجعه نمایید .", "gravityformszarinpal"), self::$min_gravityforms_version, "<a href='http://gravityforms.ir/11378' target='_blank'>", "</a>");
        printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
    }


    // #1
    // ------------------------GravityForms.IR-------------------------
    public static function gravityformszarinpal($form, $lead)
    {
        $zarinpal = array('class' => (__CLASS__ . '|' . self::$author), 'title' => __('زرین پال', 'gravityformszarinpal'),
            'param' => array(
                'email' => __('ایمیل', 'gravityformszarinpal'),
                'mobile' => __('موبایل', 'gravityformszarinpal'),
                'desc' => __('توضیحات', 'gravityformszarinpal')
            )
        );

        return apply_filters(self::$author . '_gf_zarinpal_detail', apply_filters(self::$author . '_gf_gateway_detail', $zarinpal, $form, $lead), $form, $lead);
    }

    // ------------------------GravityForms.IR-------------------------
    public static function add_permissions()
    {
        global $wp_roles;
        $editable_roles = get_editable_roles();
        foreach ((array)$editable_roles as $role => $details) {
            if ($role == 'administrator' || in_array('gravityforms_edit_forms', $details['capabilities'])) {
                $wp_roles->add_cap($role, 'gravityforms_zarinpal');
                $wp_roles->add_cap($role, 'gravityforms_zarinpal_uninstall');
            }
        }
    }

    // ------------------------GravityForms.IR-------------------------
    public static function members_get_capabilities($caps)
    {
        return array_merge($caps, array("gravityforms_zarinpal", "gravityforms_zarinpal_uninstall"));
    }

    // ------------------------GravityForms.IR-------------------------
    private static function setup()
    {
        if (get_option("gf_zarinpal_version") != self::$version)
            GFZarinPalData::update_table();
        update_option("gf_zarinpal_version", self::$version);
    }

    // ------------------------GravityForms.IR-------------------------
    public static function tooltips($tooltips)
    {
        $tooltips["gateway_name"] = __("تذکر مهم : این قسمت برای نمایش به بازدید کننده می باشد و لطفا جهت جلوگیری از مشکل و تداخل آن را فقط یکبار تنظیم نمایید و از تنظیم مکرر آن خود داری نمایید .", "gravityformszarinpal");
        return $tooltips;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function menu($menus)
    {
        $permission = "gravityforms_zarinpal";
        if (!empty($permission))
            $menus[] = array("name" => "gf_zarinpal", "label" => __("زرین پال", "gravityformszarinpal"), "callback" => array("GFZarinPal_HANNANStd", "zarinpal_page"), "permission" => $permission);
        return $menus;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function toolbar($menu_items)
    {
        $menu_items[] = array(
            'name' => 'zarinpal',
            'label' => __('زرین پال', 'gravityformszarinpal')
        );
        return $menu_items;
    }

    // ------------------------GravityForms.IR-------------------------
    private static function is_gravityforms_supported()
    {
        if (class_exists("GFCommon")) {
            $is_correct_version = version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=");
            return $is_correct_version;
        } else {
            return false;
        }
    }

    // ------------------------GravityForms.IR-------------------------
    protected static function has_access($required_permission = 'gravityforms_zarinpal')
    {
        if (!function_exists('wp_get_current_user'))
            include(ABSPATH . "wp-includes/pluggable.php");
        return GFCommon::current_user_can_any($required_permission);
    }

    // ------------------------GravityForms.IR-------------------------
    protected static function get_base_url()
    {
        return plugins_url(null, __FILE__);
    }

    // ------------------------GravityForms.IR-------------------------
    protected static function get_base_path()
    {
        $folder = basename(dirname(__FILE__));
        return WP_PLUGIN_DIR . "/" . $folder;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function set_logging_supported($plugins)
    {
        $plugins[basename(dirname(__FILE__))] = "ZarinPal";
        return $plugins;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function uninstall()
    {
        if (!self::has_access("gravityforms_zarinpal_uninstall"))
            die(__("شما مجوز کافی برای این کار را ندارید . سطح دسترسی شما پایین تر از حد مجاز است . ", "gravityformszarinpal"));
        GFZarinPalData::drop_tables();
        delete_option("gf_zarinpal_settings");
        delete_option("gf_zarinpal_configured");
        delete_option("gf_zarinpal_version");
        $plugin = basename(dirname(__FILE__)) . "/index.php";
        deactivate_plugins($plugin);
        update_option('recently_activated', array($plugin => time()) + (array)get_option('recently_activated'));
    }

    // ------------------------GravityForms.IR-------------------------
    private static function is_zarinpal_page()
    {
        $current_page = in_array(trim(strtolower(rgget("page"))), array('gf_zarinpal', 'zarinpal'));
        $current_view = in_array(trim(strtolower(rgget("view"))), array('gf_zarinpal', 'zarinpal'));
        $current_subview = in_array(trim(strtolower(rgget("subview"))), array('gf_zarinpal', 'zarinpal'));
        return $current_page || $current_view || $current_subview;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function zarinpal_form_settings_page()
    {
        GFFormSettings::page_header(); ?>
        <h3>
			<span><i class="fa fa-credit-card"></i> <?php esc_html_e('زرین پال', 'gravityformszarinpal') ?>
                <a id="add-new-confirmation" class="add-new-h2"
                   href="<?php echo esc_url(admin_url('admin.php?page=gf_zarinpal&view=edit&fid=' . absint(rgget("id")))) ?>"><?php esc_html_e('افزودن فید جدید', 'gravityformszarinpal') ?></a></span>
            <a class="add-new-h2"
               href="admin.php?page=gf_zarinpal&view=stats&id=<?php echo absint(rgget("id")) ?>"><?php _e("نمودار ها", "gravityformszarinpal") ?></a>
        </h3>
        <?php self::list_page('per-form'); ?>
        <?php GFFormSettings::page_footer();
    }

    // ------------------------GravityForms.IR-------------------------
    public static function has_zarinpal_condition($form, $config)
    {

        if (empty($config["meta"]))
            return false;

        $config = $config["meta"];
        $field = '';
        $operator = isset($config["zarinpal_conditional_operator"]) ? $config["zarinpal_conditional_operator"] : "";
        if (!empty($config["zarinpal_conditional_field_id"]))
            $field = RGFormsModel::get_field($form, $config["zarinpal_conditional_field_id"]);

        if (empty($field) || empty($config["zarinpal_conditional_enabled"]))
            return true;

        $is_visible = !RGFormsModel::is_field_hidden($form, $field, array());
        $field_value = RGFormsModel::get_field_value($field, array());
        $is_value_match = RGFormsModel::is_value_match($field_value, $config["zarinpal_conditional_value"], $operator);
        return $is_value_match && $is_visible;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function get_config_by_entry($entry)
    {
        $feed_id = gform_get_meta($entry["id"], "zarinpal_feed_id");
        $feed = !empty($feed_id) ? GFZarinPalData::get_feed($feed_id) : '';
        $return = !empty($feed) ? $feed : false;

        return apply_filters(self::$author . '_gf_zarinpal_get_config_by_entry', apply_filters(self::$author . '_gf_gateway_get_config_by_entry', $return, $entry), $entry);
    }

    // ------------------------GravityForms.IR-------------------------
    public static function delay_posts($is_disabled, $form, $lead)
    {

        $config = self::get_active_config($form);

        if (!empty($config) && is_array($config) && $config)
            return true;

        return $is_disabled;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function delay_notifications($is_disabled, $notification, $form, $lead)
    {

        $config = self::get_active_config($form);

        if (!empty($config) && is_array($config) && $config)
            return true;

        return $is_disabled;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function delay_addons($is_delayed, $form, $entry, $slug)
    {

        $config = self::get_active_config($form);

        if (!empty($config) && is_array($config) && $config) {

            if ($slug != 'gravityformsuserregistration' && isset($config["meta"]) && isset($config["meta"]["addon"]) && $config["meta"]["addon"] == 'true') {

                $fulfilled = gform_get_meta($entry['id'], $slug . '_is_fulfilled');
                $processed = gform_get_meta($entry['id'], 'processed_feeds');

                return empty($fulfilled) && rgempty($slug, $processed);
            }


            if ($slug == 'gravityformsuserregistration' && isset($config["meta"]) && isset($config["meta"]["type"]) && $config["meta"]["type"] == "subscription") {

                $fulfilled = gform_get_meta($entry['id'], $slug . '_is_fulfilled');
                $processed = gform_get_meta($entry['id'], 'processed_feeds');

                return empty($fulfilled) && rgempty($slug, $processed);
            }

        }

        return $is_delayed;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function delay_registration($is_disabled, $form, $entry, $fulfilled = '')
    {

        $config = self::get_active_config($form);

        if (!empty($config) && is_array($config) && $config) {

            if (isset($config["meta"]) && isset($config["meta"]["type"]) && $config["meta"]["type"] == "subscription" && apply_filters('gform_disable_registration_', true)) {

                if (!class_exists('GF_User_Registration') && class_exists("GFUser")) {

                    $config = GFUser::get_active_config($form, $entry);
                    $is_update_feed = rgars($config, 'meta/feed_type') == 'update';
                    $user_data = GFUser::get_user_data($entry, $form, $config, $is_update_feed);
                    if (!empty($user_data['password']))
                        gform_update_meta($entry['id'], 'userregistration_password', GFUser::encrypt($user_data['password']));

                }

                return true;
            }
        }

        return $is_disabled;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function Creat_User($form, $lead)
    {

        add_filter("gform_disable_registration_", '__return_false');

        //Create User for gravityforms user registration 2.x
        if (!class_exists('GF_User_Registration') && class_exists("GFUser")) {

            GFUser::log_debug("form #{$form['id']} - starting gf_create_user().");
            global $wpdb;

            if (rgar($lead, 'status') == 'spam') {
                GFUser::log_debug('gf_create_user(): aborting. Entry is marked as spam.');
                return;
            }

            $config = GFUser::get_active_config($form, $lead);
            $is_update_feed = rgars($config, 'meta/feed_type') == 'update';

            if (!$config || !$config['is_active']) {
                GFUser::log_debug('gf_create_user(): aborting. No feed or feed is inactive.');
                return;
            }

            $user_data = GFUser::get_user_data($lead, $form, $config, $is_update_feed);
            if (!$user_data) {
                GFUser::log_debug('gf_create_user(): aborting. user_login or user_email are empty.');
                return;
            }

            $password = gform_get_meta($lead['id'], 'userregistration_password');
            if ($password) {
                $password = GFUser::decrypt($password);
                gform_delete_meta($lead['id'], 'userregistration_password');
            } else {
                $password = '';
            }

            $user_activation = rgars($config, 'meta/user_activation');
            if (!$is_update_feed && $user_activation) {

                require_once(GFUser::get_base_path() . '/includes/signups.php');
                GFUserSignups::prep_signups_functionality();
                $meta = array(
                    'lead_id' => $lead['id'],
                    'user_login' => $user_data['user_login'],
                    'email' => $user_data['user_email'],
                    'password' => GFUser::encrypt($password),
                );

                $meta = apply_filters('gform_user_registration_signup_meta', $meta, $form, $lead, $config);
                $meta = apply_filters("gform_user_registration_signup_meta_{$form['id']}", $meta, $form, $lead, $config);
                $ms_options = rgars($config, 'meta/multisite_options');

                if (is_multisite() && rgar($ms_options, 'create_site') && $site_data = GFUser::get_site_data($lead, $form, $config)) {
                    wpmu_signup_blog($site_data['domain'], $site_data['path'], $site_data['title'], $user_data['user_login'], $user_data['user_email'], $meta);
                } else {
                    $user_data['user_login'] = preg_replace('/\s+/', '', sanitize_user($user_data['user_login'], true));
                    GFUser::log_debug("Calling wpmu_signup_user (sends email with activation link) with login: " . $user_data['user_login'] . " email: " . $user_data['user_email'] . " meta: " . print_r($meta, true));
                    wpmu_signup_user($user_data['user_login'], $user_data['user_email'], $meta);
                    GFUser::log_debug("Done with wpmu_signup_user");
                }

                $activation_key = $wpdb->get_var($wpdb->prepare("SELECT activation_key FROM $wpdb->signups WHERE user_login = %s ORDER BY registered DESC LIMIT 1", $user_data['user_login']));

                GFUserSignups::add_signup_meta($lead['id'], $activation_key);

                return;
            }

            if ($is_update_feed) {
                GFUser::update_user($lead, $form, $config);
            } else {
                if (!$user_activation) {
                    GFUser::log_debug("in gf_create_user - calling create_user");
                    GFUser::create_user($lead, $form, $config, $password);
                }
            }
        }

    }

    // ------------------------GravityForms.IR-------------------------
    public static function send_notification($event, $form, $lead, $status = 'submit', $config)
    {

        if (empty($config) || !is_array($config))
            return false;

        switch (strtolower($status)) {

            case 'submit':
                $selected_notifications = !empty($config["meta"]["gf_zarinpal_notif_1"]) ? $config["meta"]["gf_zarinpal_notif_1"] : array();
                break;

            case 'completed':
                $selected_notifications = !empty($config["meta"]["gf_zarinpal_notif_2"]) ? $config["meta"]["gf_zarinpal_notif_2"] : array();
                break;

            case 'failed':
                $selected_notifications = !empty($config["meta"]["gf_zarinpal_notif_3"]) ? $config["meta"]["gf_zarinpal_notif_3"] : array();
                break;

            case 'cancelled':
                $selected_notifications = !empty($config["meta"]["gf_zarinpal_notif_4"]) ? $config["meta"]["gf_zarinpal_notif_4"] : array();
                break;
        }

        $notifications = GFCommon::get_notifications_to_send($event, $form, $lead);
        $notifications_to_send = array();

        foreach ($notifications as $notification) {
            if (in_array($notification['id'], $selected_notifications) && apply_filters('gf_zarinpal_send_notification', apply_filters('gf_gateway_send_notification', true, $notification, $selected_notifications, $event, $form, $lead, $status), $notification, $selected_notifications, $event, $form, $lead, $status))
                $notifications_to_send[] = $notification['id'];
        }

        GFCommon::send_notifications($notifications_to_send, $form, $lead, true, $event);
    }


    // ------------------------GravityForms.IR-------------------------
    public static function get_confirmation($form, $lead = null, $event = '', $status = '', $config)
    {

        if (empty($config) || !is_array($config))
            return false;

        if (!class_exists("GFFormDisplay"))
            require_once(GFCommon::get_base_path() . "/form_display.php");

        switch (strtolower($status)) {

            case 'completed':
                $selected_confirmations = !empty($config["meta"]["gf_zarinpal_conf_1"]) ? $config["meta"]["gf_zarinpal_conf_1"] : array();
                break;

            case 'failed':
                $selected_confirmations = !empty($config["meta"]["gf_zarinpal_conf_2"]) ? $config["meta"]["gf_zarinpal_conf_2"] : array();
                break;

            case 'cancelled':
                $selected_confirmations = !empty($config["meta"]["gf_zarinpal_conf_3"]) ? $config["meta"]["gf_zarinpal_conf_3"] : array();
                break;
        }

        if (!is_array(rgar($form, 'confirmations'))) {
            return $form;
        }

        if (!empty($event)) {
            $confirmations = wp_filter_object_list($form['confirmations'], array('event' => $event));
        } else {
            $confirmations = $form['confirmations'];
        }

        if (is_array($form['confirmations']) && count($confirmations) <= 1) {
            $form['confirmation'] = reset($confirmations);
            return $form;
        }

        if (empty($lead)) {
            //$lead = GFFormsModel::create_lead( $form );
        }

        foreach ($confirmations as $confirmation) {

            if (rgar($confirmation, 'event') != $event) {
                continue;
            }

            if (rgar($confirmation, 'isDefault')) {
                continue;
            }

            if (isset($confirmation['isActive']) && !$confirmation['isActive']) {
                continue;
            }

            $logic = rgar($confirmation, 'conditionalLogic');
            if (GFCommon::evaluate_conditional_logic($logic, $form, $lead)) {

                if (in_array(rgar($confirmation, 'id'), $selected_confirmations) && apply_filters('gf_zarinpal_send_confirmation', apply_filters('gf_gateway_send_confirmation', true, $confirmation, $selected_confirmations, $event, $form, $lead, $status), $confirmation, $selected_confirmations, $event, $form, $lead, $status)) {
                    $form['confirmation'] = $confirmation;
                    return $form;
                }
            }
        }

        $filtered_list = wp_filter_object_list($form['confirmations'], array('isDefault' => true));
        $form['confirmation'] = reset($filtered_list);
        return $form;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function confirmation($form, $lead = null, $event = '', $status = '', $fault = '', $config)
    {

        if (!class_exists("GFFormDisplay"))
            require_once(GFCommon::get_base_path() . "/form_display.php");

        $form = self::get_confirmation($form, $lead, $event, $status, $config);

        if (empty($form) || !$form)
            return false;

        $ajax = false;

        if (!empty($form['confirmation']['type']) && $form['confirmation']['type'] == 'message') {
            $default_anchor = 0;
            $anchor = gf_apply_filters('gform_confirmation_anchor', $form['id'], $default_anchor) ? "<a id='gf_{$form['id']}' name='gf_{$form['id']}' class='gform_anchor' ></a>" : '';
            $nl2br = rgar($form['confirmation'], 'disableAutoformat') ? false : true;
            $cssClass = rgar($form, 'cssClass');
            $confirmation = empty($form['confirmation']['message']) ? "{$anchor} " : "{$anchor}<div id='gform_confirmation_wrapper_{$form['id']}' class='gform_confirmation_wrapper {$cssClass}'><div id='gform_confirmation_message_{$form['id']}' class='gform_confirmation_message_{$form['id']} gform_confirmation_message'>" . GFCommon::replace_variables($form['confirmation']['message'], $form, $lead, false, true, $nl2br) . '</div></div>';
        } else {
            if (!empty($form['confirmation']['pageId'])) {
                $url = get_permalink($form['confirmation']['pageId']);
            } else {
                $url = GFCommon::replace_variables(trim($form['confirmation']['url']), $form, $lead, false, true, true, 'text');
            }

            $url_info = parse_url($url);
            $query_string = rgar($url_info, 'query');
            $dynamic_query = GFCommon::replace_variables(trim($form['confirmation']['queryString']), $form, $lead, true, false, false, 'text');
            $dynamic_query = str_replace(array("\r", "\n"), '', $dynamic_query);
            $query_string .= rgempty('query', $url_info) || empty($dynamic_query) ? $dynamic_query : '&' . $dynamic_query;

            if (!empty($url_info['fragment'])) {
                $query_string .= '#' . rgar($url_info, 'fragment');
            }

            $url = isset($url_info['scheme']) ? $url_info['scheme'] : 'http';
            $url .= '://' . rgar($url_info, 'host');
            if (!empty($url_info['port'])) {
                $url .= ':' . rgar($url_info, 'port');
            }

            $url .= rgar($url_info, 'path');
            if (!empty($query_string)) {
                $url .= "?{$query_string}";
            }

            if (headers_sent() || $ajax) {
                //Perform client side redirect for AJAX forms, of if headers have already been sent
                $confirmation = self::get_js_redirect_confirmation($url, $ajax);
            } else {
                $confirmation = array('redirect' => $url);
            }
        }

        $confirmation = gf_apply_filters('gform_confirmation', $form['id'], $confirmation, $form, $lead, $ajax);

        if (!is_array($confirmation)) {
            $confirmation = GFCommon::gform_do_shortcode($confirmation); //enabling shortcodes
        } else if (headers_sent() || $ajax) {
            //Perform client side redirect for AJAX forms, of if headers have already been sent
            $confirmation = self::get_js_redirect_confirmation($confirmation['redirect'], $ajax); //redirecting via client side
        }

        GFCommon::log_debug('GFFormDisplay::handle_confirmation(): Confirmation => ' . print_r($confirmation, true));

        if (is_array($confirmation) && isset($confirmation["redirect"])) {
            header("Location: {$confirmation["redirect"]}");
            exit;
        }
        $confirmation = str_ireplace('{fault}', $fault, $confirmation);
        GFFormDisplay::$submission[$form['id']] = array("is_confirmation" => true, "confirmation_message" => $confirmation, "form" => $form, "lead" => $lead);
    }

    // ------------------------GravityForms.IR-------------------------
    public static function get_js_redirect_confirmation($url, $ajax)
    {
        $confirmation = "<script type=\"text/javascript\">" . apply_filters('gform_cdata_open', '') . " function gformRedirect(){document.location.href='$url';}";
        if (!$ajax) {
            $confirmation .= 'gformRedirect();';
        }
        $confirmation .= apply_filters('gform_cdata_close', '') . '</script>';
        return $confirmation;
    }

    // ------------------------GravityForms.IR-------------------------
    private static function redirect_confirmation($url, $ajax)
    {

        if (headers_sent() || $ajax) {
            if (is_callable(array('GFFormDisplay', 'get_js_redirect_confirmation')))
                $confirmation = GFFormDisplay::get_js_redirect_confirmation($url, $ajax);
            else
                $confirmation = self::get_js_redirect_confirmation($url, $ajax);
        } else {
            $confirmation = array('redirect' => $url);
        }

        return $confirmation;
    }


    // ------------------------GravityForms.IR-------------------------
    public static function change_price($form)
    {

        $config = self::get_active_config($form);
        if (empty($config) || !is_array($config))
            return $form;

        $shaparak = !empty($GLOBALS['shaparak']) ? $GLOBALS['shaparak'] : '';

        if (empty($shaparak)) {

            $currency = GFCommon::get_currency();
            if ($currency == 'IRR' || $currency == 'IRT') {

                $GLOBALS['shaparak'] = 'apply';

                $max = $currency == 'IRR' ? 1000 : 100;
                $show_max = !empty($config["meta"]["shaparak"]) && $config["meta"]["shaparak"] == "sadt";
                ?>
                <script type="text/javascript">gform.addFilter('gform_product_total', function (total, formId) {
                        if (total < <?php echo $max ?> && total > 0) {
                            total = <?php echo $show_max ? $max : 0 ?>;
                        }
                        return total;
                    });
                </script>
                <?php
            }
        }

        return $form;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function get_active_config($form)
    {

        if (!empty(self::$config))
            return self::$config;

        $configs = GFZarinPalData::get_feed_by_form($form["id"], true);

        $configs = apply_filters(self::$author . '_gf_zarinpal_get_active_configs', apply_filters(self::$author . '_gf_gateway_get_active_configs', $configs, $form), $form);

        $return = false;

        if (!empty($configs) && is_array($configs)) {

            foreach ($configs as $config) {
                if (self::has_zarinpal_condition($form, $config))
                    $return = $config;
                break;
            }
        }

        self::$config = apply_filters(self::$author . '_gf_zarinpal_get_active_config', apply_filters(self::$author . '_gf_gateway_get_active_config', $return, $form), $form);
        return self::$config;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function zarinpal_page()
    {
        $view = rgget("view");
        if ($view == "edit")
            self::config_page();
        else if ($view == "stats")
            ZarinPal_Chart::stats_page();
        else
            self::list_page('');
    }

    // ------------------------GravityForms.IR-------------------------
    private static function list_page($arg)
    {

        if (!self::is_gravityforms_supported()) {
            die(sprintf(__("درگاه زرین پال نیاز به گرویتی فرم نسخه %s دارد . برای بروز رسانی هسته گرویتی فرم به %sسایت گرویتی فرم فارسی%s مراجعه نمایید .", "gravityformszarinpal"), self::$min_gravityforms_version, "<a href='http://gravityforms.ir/11378' target='_blank'>", "</a>"));
        }

        if (rgpost('action') == "delete") {
            check_admin_referer("list_action", "gf_zarinpal_list");
            $id = absint(rgpost("action_argument"));
            GFZarinPalData::delete_feed($id);
            ?>
            <div class="updated fade" style="padding:6px"><?php _e("فید حذف شد", "gravityformszarinpal") ?></div><?php
        } else if (!empty($_POST["bulk_action"])) {

            check_admin_referer("list_action", "gf_zarinpal_list");
            $selected_feeds = rgpost("feed");
            if (is_array($selected_feeds)) {
                foreach ($selected_feeds as $feed_id)
                    GFZarinPalData::delete_feed($feed_id);
            }

            ?>
            <div class="updated fade" style="padding:6px"><?php _e("فید ها حذف شدند", "gravityformszarinpal") ?></div>
            <?php
        }
        ?>
        <div class="wrap">

            <?php if ($arg != 'per-form') { ?>

                <h2>
                    <?php _e("فرم های زرین پال", "gravityformszarinpal");
                    if (get_option("gf_zarinpal_configured")) { ?>
                        <a class="add-new-h2"
                           href="admin.php?page=gf_zarinpal&view=edit"><?php _e("افزودن جدید", "gravityformszarinpal") ?></a>
                        <?php
                    } ?>
                </h2>

            <?php } ?>

            <form id="confirmation_list_form" method="post">
                <?php wp_nonce_field('list_action', 'gf_zarinpal_list') ?>
                <input type="hidden" id="action" name="action"/>
                <input type="hidden" id="action_argument" name="action_argument"/>
                <div class="tablenav">
                    <div class="alignleft actions" style="padding:8px 0 7px 0;">
                        <label class="hidden"
                               for="bulk_action"><?php _e("اقدام دسته جمعی", "gravityformszarinpal") ?></label>
                        <select name="bulk_action" id="bulk_action">
                            <option value=''> <?php _e("اقدامات دسته جمعی", "gravityformszarinpal") ?> </option>
                            <option value='delete'><?php _e("حذف", "gravityformszarinpal") ?></option>
                        </select>
                        <?php
                        echo '<input type="submit" class="button" value="' . __("اعمال", "gravityformszarinpal") . '" onclick="if( jQuery(\'#bulk_action\').val() == \'delete\' && !confirm(\'' . __("فید حذف شود ؟ ", "gravityformszarinpal") . __("\'Cancel\' برای منصرف شدن, \'OK\' برای حذف کردن", "gravityformszarinpal") . '\')) { return false; } return true;"/>';
                        ?>
                        <a class="button button-primary"
                           href="admin.php?page=gf_settings&subview=gf_zarinpal"><?php _e('تنظیمات حساب زرین پال', 'gravityformszarinpal') ?></a>
                    </div>
                </div>
                <table class="wp-list-table widefat fixed striped toplevel_page_gf_edit_forms" cellspacing="0">
                    <thead>
                    <tr>
                        <th scope="col" id="cb" class="manage-column column-cb check-column"
                            style="padding:13px 3px;width:30px"><input type="checkbox"/></th>
                        <th scope="col" id="active" class="manage-column"
                            style="width:<?php echo $arg != 'per-form' ? '50px' : '20px' ?>"><?php echo $arg != 'per-form' ? __('وضعیت', 'gravityformszarinpal') : '' ?></th>
                        <th scope="col" class="manage-column"
                            style="width:<?php echo $arg != 'per-form' ? '65px' : '30%' ?>"><?php _e(" آیدی فید", "gravityformszarinpal") ?></th>
                        <?php if ($arg != 'per-form') { ?>
                            <th scope="col"
                                class="manage-column"><?php _e("فرم متصل به درگاه", "gravityformszarinpal") ?></th>
                        <?php } ?>
                        <th scope="col" class="manage-column"><?php _e("نوع تراکنش", "gravityformszarinpal") ?></th>
                    </tr>
                    </thead>
                    <tfoot>
                    <tr>
                        <th scope="col" id="cb" class="manage-column column-cb check-column" style="padding:13px 3px;">
                            <input type="checkbox"/></th>
                        <th scope="col" id="active"
                            class="manage-column"><?php echo $arg != 'per-form' ? __('وضعیت', 'gravityformszarinpal') : '' ?></th>
                        <th scope="col" class="manage-column"><?php _e("آیدی فید", "gravityformszarinpal") ?></th>
                        <?php if ($arg != 'per-form') { ?>
                            <th scope="col"
                                class="manage-column"><?php _e("فرم متصل به درگاه", "gravityformszarinpal") ?></th>
                        <?php } ?>
                        <th scope="col" class="manage-column"><?php _e("نوع تراکنش", "gravityformszarinpal") ?></th>
                    </tr>
                    </tfoot>
                    <tbody class="list:user user-list">
                    <?php
                    $currency = GFCommon::get_currency();

                    if ($arg != 'per-form')
                        $settings = GFZarinPalData::get_feeds();
                    else
                        $settings = GFZarinPalData::get_feed_by_form(rgget('id'), false);

                    if (!get_option("gf_zarinpal_configured")) {
                        ?>
                        <td colspan="5" style="padding:20px;">
                            <?php echo sprintf(__("برای شروع باید درگاه را فعال نمایید . به %sتنظیمات زرین پال%s بروید . ", "gravityformszarinpal"), '<a href="admin.php?page=gf_settings&subview=gf_zarinpal">', "</a>"); ?>
                        </td>
                        </tr>
                        <?php
                    } else if ($currency != 'IRR' && $currency != 'IRT') { ?>
                        <tr>
                            <td colspan="5" style="padding:20px;">
                                <?php echo sprintf(__("برای استفاده از این درگاه باید واحد پول را بر روی « تومان » یا « ریال ایران » تنظیم کنید . %sبرای تنظیم واحد پول کلیک نمایید%s . ", "gravityformszarinpal"), '<a href="admin.php?page=gf_settings">', "</a>"); ?>
                            </td>
                        </tr>
                    <?php } else if (is_array($settings) && sizeof($settings) > 0) {
                        foreach ($settings as $setting) {
                            ?>
                            <tr class='author-self status-inherit' valign="top">

                                <th scope="row" class="check-column"><input type="checkbox" name="feed[]"
                                                                            value="<?php echo $setting["id"] ?>"/></th>

                                <td><img style="cursor:pointer;"
                                         src="<?php echo esc_url(GFCommon::get_base_url()) ?>/images/active<?php echo intval($setting["is_active"]) ?>.png"
                                         alt="<?php echo $setting["is_active"] ? __("درگاه فعال است", "gravityformszarinpal") : __("درگاه غیر فعال است", "gravityformszarinpal"); ?>"
                                         title="<?php echo $setting["is_active"] ? __("درگاه فعال است", "gravityformszarinpal") : __("درگاه غیر فعال است", "gravityformszarinpal"); ?>"
                                         onclick="ToggleActive(this, <?php echo $setting['id'] ?>); "/></td>

                                <td><?php echo $setting["id"] ?>
                                    <?php if ($arg == 'per-form') { ?>
                                        <div class="row-actions">
                                                <span class="edit">
                                                    <a title="<?php _e("ویرایش فید", "gravityformszarinpal") ?>"
                                                       href="admin.php?page=gf_zarinpal&view=edit&id=<?php echo $setting["id"] ?>"><?php _e("ویرایش فید", "gravityformszarinpal") ?></a>
                                                    |
                                                </span>
                                                <span class="trash">
                                                    <a title="<?php _e("حذف", "gravityformszarinpal") ?>"
                                                       href="javascript: if(confirm('<?php _e("فید حذف شود؟ ", "gravityformszarinpal") ?> <?php _e("\'Cancel\' برای انصراف, \'OK\' برای حذف کردن.", "gravityformszarinpal") ?>')){ DeleteSetting(<?php echo $setting["id"] ?>);}"><?php _e("حذف", "gravityformszarinpal") ?></a>
                                                </span>
                                        </div>
                                    <?php } ?>
                                </td>

                                <?php if ($arg != 'per-form') { ?>
                                    <td class="column-title">
                                        <strong><a class="row-title"
                                                   href="admin.php?page=gf_zarinpal&view=edit&id=<?php echo $setting["id"] ?>"
                                                   title="<?php _e("تنظیم مجدد درگاه", "gravityformszarinpal") ?>"><?php echo $setting["form_title"] ?></a></strong>

                                        <div class="row-actions">
                                            <span class="edit">
                                                <a title="<?php _e("ویرایش فید", "gravityformszarinpal") ?>"
                                                   href="admin.php?page=gf_zarinpal&view=edit&id=<?php echo $setting["id"] ?>"><?php _e("ویرایش فید", "gravityformszarinpal") ?></a>
                                                |
                                            </span>
                                            <span class="trash">
                                                <a title="<?php _e("حذف فید", "gravityformszarinpal") ?>"
                                                   href="javascript: if(confirm('<?php _e("فید حذف شود؟ ", "gravityformszarinpal") ?> <?php _e("\'Cancel\' برای انصراف, \'OK\' برای حذف کردن.", "gravityformszarinpal") ?>')){ DeleteSetting(<?php echo $setting["id"] ?>);}"><?php _e("حذف", "gravityformszarinpal") ?></a>
                                                |
                                            </span>
											<span class="view">
                                                <a title="<?php _e("ویرایش فرم", "gravityformszarinpal") ?>"
                                                   href="admin.php?page=gf_edit_forms&id=<?php echo $setting["form_id"] ?>"><?php _e("ویرایش فرم", "gravityformszarinpal") ?></a>
                                                |
                                            </span>
                                            <span class="view">
                                                <a title="<?php _e("مشاهده صندوق ورودی", "gravityformszarinpal") ?>"
                                                   href="admin.php?page=gf_entries&view=entries&id=<?php echo $setting["form_id"] ?>"><?php _e("صندوق ورودی", "gravityformszarinpal") ?></a>
                                                |
                                            </span>
                                            <span class="view">
                                                <a title="<?php _e("نمودارهای فرم", "gravityformszarinpal") ?>"
                                                   href="admin.php?page=gf_zarinpal&view=stats&id=<?php echo $setting["form_id"] ?>"><?php _e("نمودارهای فرم", "gravityformszarinpal") ?></a>
                                            </span>
                                        </div>
                                    </td>
                                <?php } ?>


                                <td class="column-date">
                                    <?php
                                    if (isset($setting["meta"]["type"]) && $setting["meta"]["type"] == 'subscription') {
                                        _e("عضویت", "gravityformszarinpal");
                                    } else {
                                        _e("محصول معمولی یا فرم ارسال پست", "gravityformszarinpal");
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5" style="padding:20px;">
                                <?php
                                if ($arg == 'per-form')
                                    echo sprintf(__("شما هیچ فید زرین پالی ندارید . %sیکی بسازید%s .", "gravityformszarinpal"), '<a href="admin.php?page=gf_zarinpal&view=edit&fid=' . absint(rgget("id")) . '">', "</a>");
                                else
                                    echo sprintf(__("شما هیچ فید زرین پالی ندارید . %sیکی بسازید%s .", "gravityformszarinpal"), '<a href="admin.php?page=gf_zarinpal&view=edit">', "</a>");
                                ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </form>
        </div>
        <script type="text/javascript">
            function DeleteSetting(id) {
                jQuery("#action_argument").val(id);
                jQuery("#action").val("delete");
                jQuery("#confirmation_list_form")[0].submit();
            }
            function ToggleActive(img, feed_id) {
                var is_active = img.src.indexOf("active1.png") >= 0;
                if (is_active) {
                    img.src = img.src.replace("active1.png", "active0.png");
                    jQuery(img).attr('title', '<?php _e("درگاه غیر فعال است", "gravityformszarinpal") ?>').attr('alt', '<?php _e("درگاه غیر فعال است", "gravityformszarinpal") ?>');
                }
                else {
                    img.src = img.src.replace("active0.png", "active1.png");
                    jQuery(img).attr('title', '<?php _e("درگاه فعال است", "gravityformszarinpal") ?>').attr('alt', '<?php _e("درگاه فعال است", "gravityformszarinpal") ?>');
                }
                var mysack = new sack(ajaxurl);
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar("action", "gf_zarinpal_update_feed_active");
                mysack.setVar("gf_zarinpal_update_feed_active", "<?php echo wp_create_nonce("gf_zarinpal_update_feed_active") ?>");
                mysack.setVar("feed_id", feed_id);
                mysack.setVar("is_active", is_active ? 0 : 1);
                mysack.onError = function () {
                    alert('<?php _e("خطای Ajax رخ داده است", "gravityformszarinpal") ?>')
                };
                mysack.runAJAX();
                return true;
            }
        </script>
        <?php
    }

    // ------------------------GravityForms.IR-------------------------
    public static function fix_mobile($mobile = '')
    {

        if (empty($mobile))
            return '';

        $phone = '';
        preg_match_all('/\d+/', $mobile, $matches);
        if (!empty($matches[0])) {
            foreach ((array)$matches[0] as $number) {
                $phone .= $number;
            }
        }

        if (strpos($mobile, '+') !== false || stripos($mobile, '%2B') !== false) {
            return '+' . $phone;
        } else if (substr($phone, 0, 2) == '00') {
            return '+' . substr($phone, 2);
        } else if (substr($phone, 0, 1) == '0') {
            $phone = substr($phone, 1);
        }

        return '0' . $phone;
    }

    // ------------------------GravityForms.IR-------------------------
    public static function update_feed_active()
    {
        check_ajax_referer('gf_zarinpal_update_feed_active', 'gf_zarinpal_update_feed_active');
        $id = absint(rgpost('feed_id'));
        $feed = GFZarinPalData::get_feed($id);
        GFZarinPalData::update_feed($id, $feed["form_id"], $_POST["is_active"], $feed["meta"]);
    }

    // ------------------------GravityForms.IR-------------------------
    private static function Return_URL($form_id, $lead_id)
    {

        $pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

        if ($_SERVER['SERVER_PORT'] != '80') {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $arr_params = array('id', 'lead', 'no', 'Authority', 'Status');
        $pageURL = esc_url(remove_query_arg($arr_params, $pageURL));

        $pageURL = str_replace('#038;', '&', add_query_arg(array('id' => $form_id, 'lead' => $lead_id), $pageURL));

        return apply_filters(self::$author . '_zarinpal_return_url', apply_filters(self::$author . '_gateway_return_url', $pageURL, $form_id, $lead_id, __CLASS__), $form_id, $lead_id, __CLASS__);
    }

    // ------------------------GravityForms.IR-------------------------
    public static function get_order_total($form, $entry)
    {

        $total = GFCommon::get_order_total($form, $entry);
        $total = (!empty($total) && $total > 0) ? $total : 0;

        $config = self::get_config_by_entry($entry);

        if (!empty($config) && isset($config["meta"]) && isset($config["meta"]["shaparak"])) {

            $currency = GFCommon::get_currency();

            if ($currency == 'IRR' || $currency == 'IRT') {

                if ($currency == 'IRR' && $total < 1000 && $total > 0) {
                    if ($config["meta"]["shaparak"] == "sadt")
                        $total = 1000;
                    else
                        $total = 0;
                }

                if ($currency == 'IRT' && $total < 100 && $total > 0) {
                    if ($config["meta"]["shaparak"] == "sadt")
                        $total = 100;
                    else
                        $total = 0;
                }
            }
        }

        return apply_filters(self::$author . '_zarinpal_get_order_total', apply_filters(self::$author . '_gateway_get_order_total', $total, $form, $entry), $form, $entry);
    }

    // ------------------------GravityForms.IR-------------------------
    private static function get_mapped_field_list($field_name, $selected_field, $fields)
    {
        $str = "<select name='$field_name' id='$field_name'><option value=''></option>";
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $field_id = $field[0];
                $field_label = esc_html(GFCommon::truncate_middle($field[1], 40));
                $selected = $field_id == $selected_field ? "selected='selected'" : "";
                $str .= "<option value='" . $field_id . "' " . $selected . ">" . $field_label . "</option>";
            }
        }
        $str .= "</select>";
        return $str;
    }

    // ------------------------GravityForms.IR-------------------------
    private static function get_form_fields($form)
    {
        $fields = array();
        if (is_array($form["fields"])) {
            foreach ($form["fields"] as $field) {
                if (isset($field["inputs"]) && is_array($field["inputs"])) {
                    foreach ($field["inputs"] as $input)
                        $fields[] = array($input["id"], GFCommon::get_label($field, $input["id"]));
                } else if (!rgar($field, 'displayOnly')) {
                    $fields[] = array($field["id"], GFCommon::get_label($field));
                }
            }
        }
        return $fields;
    }

    // ------------------------GravityForms.IR---------------------------------------------------------------------
    //desc
    private static function get_customer_information_desc($form, $config = null)
    {
        $form_fields = self::get_form_fields($form);
        $selected_field = !empty($config["meta"]["customer_fields_desc"]) ? $config["meta"]["customer_fields_desc"] : '';
        return self::get_mapped_field_list('zarinpal_customer_field_desc', $selected_field, $form_fields);
    }

    //email
    private static function get_customer_information_email($form, $config = null)
    {
        $form_fields = self::get_form_fields($form);
        $selected_field = !empty($config["meta"]["customer_fields_email"]) ? $config["meta"]["customer_fields_email"] : '';
        return self::get_mapped_field_list('zarinpal_customer_field_email', $selected_field, $form_fields);
    }

    //mobile
    private static function get_customer_information_mobile($form, $config = null)
    {
        $form_fields = self::get_form_fields($form);
        $selected_field = !empty($config["meta"]["customer_fields_mobile"]) ? $config["meta"]["customer_fields_mobile"] : '';
        return self::get_mapped_field_list('zarinpal_customer_field_mobile', $selected_field, $form_fields);
    }
    // ------------------------------------------------------------------------------------------------------------


    // ------------------------GravityForms.IR-------------------------
    public static function payment_entry_detail($form_id, $lead)
    {

        $payment_gateway = rgar($lead, "payment_method");

        if (!empty($payment_gateway) && $payment_gateway == "zarinpal") {

            do_action('gf_gateway_entry_detail');

            ?>
            <hr/>
            <strong>
                <?php _e('اطلاعات تراکنش :', 'gravityformszarinpal') ?>
            </strong>
            <br/>
            <br/>
            <?php

            $transaction_type = rgar($lead, "transaction_type");
            $payment_status = rgar($lead, "payment_status");
            $payment_amount = rgar($lead, "payment_amount");

            if (empty($payment_amount)) {
                $form = RGFormsModel::get_form_meta($form_id);
                $payment_amount = GFCommon::get_order_total($form, $lead);
            }

            $transaction_id = rgar($lead, "transaction_id");
            $payment_date = rgar($lead, "payment_date");

            $date = new DateTime($payment_date);
            $tzb = get_option('gmt_offset');
            $tzn = abs($tzb) * 3600;
            $tzh = intval(gmdate("H", $tzn));
            $tzm = intval(gmdate("i", $tzn));

            if (intval($tzb) < 0) {
                $date->sub(new DateInterval('P0DT' . $tzh . 'H' . $tzm . 'M'));
            } else {
                $date->add(new DateInterval('P0DT' . $tzh . 'H' . $tzm . 'M'));
            }

            $payment_date = $date->format('Y-m-d H:i:s');
            $payment_date = GF_jdate('Y-m-d H:i:s', strtotime($payment_date), '', date_default_timezone_get(), 'en');

            if ($payment_status == 'Paid')
                $payment_status_persian = __('موفق', 'gravityformszarinpal');

            if ($payment_status == 'Active')
                $payment_status_persian = __('موفق', 'gravityformszarinpal');

            if ($payment_status == 'Cancelled')
                $payment_status_persian = __('منصرف شده', 'gravityformszarinpal');

            if ($payment_status == 'Failed')
                $payment_status_persian = __('ناموفق', 'gravityformszarinpal');

            if ($payment_status == 'Processing')
                $payment_status_persian = __('معلق', 'gravityformszarinpal');

            if (!strtolower(rgpost("save")) || RGForms::post("screen_mode") != "edit") {
                echo __('وضعیت پرداخت : ', 'gravityformszarinpal') . $payment_status_persian . '<br/><br/>';
                echo __('تاریخ پرداخت : ', 'gravityformszarinpal') . '<span style="">' . $payment_date . '</span><br/><br/>';
                echo __('مبلغ پرداختی : ', 'gravityformszarinpal') . GFCommon::to_money($payment_amount, rgar($lead, "currency")) . '<br/><br/>';
                echo __('کد رهگیری : ', 'gravityformszarinpal') . $transaction_id . '<br/><br/>';
                echo __('درگاه پرداخت : زرین پال', 'gravityformszarinpal');
            } else {
                $payment_string = '';
                $payment_string .= '<select id="payment_status" name="payment_status">';
                $payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status_persian . '</option>';

                if ($transaction_type == 1) {
                    if ($payment_status != "Paid")
                        $payment_string .= '<option value="Paid">' . __('موفق', 'gravityformszarinpal') . '</option>';
                }

                if ($transaction_type == 2) {
                    if ($payment_status != "Active")
                        $payment_string .= '<option value="Active">' . __('موفق', 'gravityformszarinpal') . '</option>';
                }

                if (!$transaction_type) {

                    if ($payment_status != "Paid")
                        $payment_string .= '<option value="Paid">' . __('موفق', 'gravityformszarinpal') . '</option>';

                    if ($payment_status != "Active")
                        $payment_string .= '<option value="Active">' . __('موفق', 'gravityformszarinpal') . '</option>';
                }

                if ($payment_status != "Failed")
                    $payment_string .= '<option value="Failed">' . __('ناموفق', 'gravityformszarinpal') . '</option>';

                if ($payment_status != "Cancelled")
                    $payment_string .= '<option value="Cancelled">' . __('منصرف شده', 'gravityformszarinpal') . '</option>';

                if ($payment_status != "Processing")
                    $payment_string .= '<option value="Processing">' . __('معلق', 'gravityformszarinpal') . '</option>';

                $payment_string .= '</select>';

                echo __('وضعیت پرداخت :', 'gravityformszarinpal') . $payment_string . '<br/><br/>';
                ?>
                <div id="edit_payment_status_details" style="display:block">
                    <table>
                        <tr>
                            <td><?php _e('تاریخ پرداخت :', 'gravityformszarinpal') ?></td>
                            <td><input type="text" id="payment_date" name="payment_date"
                                       value="<?php echo $payment_date ?>"></td>
                        </tr>
                        <tr>
                            <td><?php _e('مبلغ پرداخت :', 'gravityformszarinpal') ?></td>
                            <td><input type="text" id="payment_amount" name="payment_amount"
                                       value="<?php echo $payment_amount ?>"></td>
                        </tr>
                        <tr>
                            <td><?php _e('شماره تراکنش :', 'gravityformszarinpal') ?></td>
                            <td><input type="text" id="zarinpal_transaction_id" name="zarinpal_transaction_id"
                                       value="<?php echo $transaction_id ?>"></td>
                        </tr>

                    </table>
                    <br/>
                </div>
                <?php
                echo __('درگاه پرداخت : زرین پال (غیر قابل ویرایش)', 'gravityformszarinpal');
            }

            echo '<br/>';
        }
    }

    // ------------------------GravityForms.IR-------------------------
    public static function update_payment_entry($form, $lead_id)
    {

        check_admin_referer('gforms_save_entry', 'gforms_save_entry');

        do_action('gf_gateway_update_entry');

        $lead = RGFormsModel::get_lead($lead_id);

        $payment_gateway = rgar($lead, "payment_method");

        if (empty($payment_gateway))
            return;

        if ($payment_gateway != "zarinpal")
            return;

        $payment_status = rgpost("payment_status");
        if (empty($payment_status)) {
            $payment_status = rgar($lead, "payment_status");
        }

        $payment_amount = rgpost("payment_amount");
        $payment_transaction = rgpost("zarinpal_transaction_id");
        $payment_date_Checker = $payment_date = rgpost("payment_date");

        list($date, $time) = explode(" ", $payment_date);
        list($Y, $m, $d) = explode("-", $date);
        list($H, $i, $s) = explode(":", $time);
        $miladi = GF_jalali_to_gregorian($Y, $m, $d);

        $date = new DateTime("$miladi[0]-$miladi[1]-$miladi[2] $H:$i:$s");
        $payment_date = $date->format('Y-m-d H:i:s');

        if (empty($payment_date_Checker)) {
            if (!empty($lead["payment_date"])) {
                $payment_date = $lead["payment_date"];
            } else {
                $payment_date = rgar($lead, "date_created");
            }
        } else {
            $payment_date = date("Y-m-d H:i:s", strtotime($payment_date));
            $date = new DateTime($payment_date);
            $tzb = get_option('gmt_offset');
            $tzn = abs($tzb) * 3600;
            $tzh = intval(gmdate("H", $tzn));
            $tzm = intval(gmdate("i", $tzn));
            if (intval($tzb) < 0) {
                $date->add(new DateInterval('P0DT' . $tzh . 'H' . $tzm . 'M'));
            } else {
                $date->sub(new DateInterval('P0DT' . $tzh . 'H' . $tzm . 'M'));
            }
            $payment_date = $date->format('Y-m-d H:i:s');
        }

        global $current_user;
        $user_id = 0;
        $user_name = __("مهمان", 'gravityformszarinpal');
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $lead["payment_status"] = $payment_status;
        $lead["payment_amount"] = $payment_amount;
        $lead["payment_date"] = $payment_date;
        $lead["transaction_id"] = $payment_transaction;
        GFAPI::update_entry($lead);

        if ($payment_status == 'Paid' || $payment_status == 'Active') {
            GFAPI::update_entry_property($lead["id"], "is_fulfilled", 1);
        } else {
            GFAPI::update_entry_property($lead["id"], "is_fulfilled", 0);
        }

        $new_status = '';
        switch (rgar($lead, "payment_status")) {
            case "Active" :
                $new_status = __('موفق', 'gravityformszarinpal');
                break;

            case "Paid" :
                $new_status = __('موفق', 'gravityformszarinpal');
                break;

            case "Cancelled" :
                $new_status = __('منصرف شده', 'gravityformszarinpal');
                break;

            case "Failed" :
                $new_status = __('ناموفق', 'gravityformszarinpal');
                break;

            case "Processing" :
                $new_status = __('معلق', 'gravityformszarinpal');
                break;
        }

        RGFormsModel::add_note($lead["id"], $user_id, $user_name, sprintf(__("اطلاعات تراکنش به صورت دستی ویرایش شد . وضعیت : %s - مبلغ : %s - کد رهگیری : %s - تاریخ : %s", "gravityformszarinpal"), $new_status, GFCommon::to_money($lead["payment_amount"], $lead["currency"]), $payment_transaction, $lead["payment_date"]));

    }

    // #2
    // ------------------------GravityForms.IR-------------------------
    public static function settings_page()
    {

        if (!extension_loaded('soap')) {
            _e('ماژول soap بر روی سرور شما فعال نیست. برای استفاده از درگاه باید آن را فعال نمایید. با مدیر هاست تماس بگیرید.', 'gravityformsrashapay');
            return;
        }

        if (rgpost("uninstall")) {
            check_admin_referer("uninstall", "gf_zarinpal_uninstall");
            self::uninstall();
            echo '<div class="updated fade" style="padding:20px;">' . __("درگاه با موفقیت غیرفعال شد و اطلاعات مربوط به آن نیز از بین رفت برای فعالسازی مجدد میتوانید از طریق افزونه های وردپرس اقدام نمایید .", "gravityformszarinpal") . '</div>';
            return;
        } else if (isset($_POST["gf_zarinpal_submit"])) {

            check_admin_referer("update", "gf_zarinpal_update");
            $settings = array(
                "merchent" => rgpost('gf_zarinpal_merchent'),
                "server" => rgpost('gf_zarinpal_server'),
                "gname" => rgpost('gf_zarinpal_gname'),
            );
            update_option("gf_zarinpal_settings", array_map('sanitize_text_field', $settings));
            if (isset($_POST["gf_zarinpal_configured"]))
                update_option("gf_zarinpal_configured", sanitize_text_field($_POST["gf_zarinpal_configured"]));
            else
                delete_option("gf_zarinpal_configured");
        } else {
            $settings = get_option("gf_zarinpal_settings");
        }

        if (!empty($_POST)) {

            if ( isset($_POST["gf_zarinpal_configured"]) && ($Response = self::Request('valid_checker', '', '', '') ) && $Response != false) {

                if ($Response === true) {
                    echo '<div class="updated fade" style="padding:6px">' . __("ارتباط با درگاه برقرار شد و اطلاعات وارد شده صحیح است .", "gravityformszarinpal") . '</div>';
                } else if ($Response == 'sandbox') {
                    echo '<div class="updated fade" style="padding:6px">' . __("در حالت تستی نیاز به ورود اطلاعات صحیح نمی باشد .", "gravityformszarinpal") . '</div>';
                } else {
                    echo '<div class="error fade" style="padding:6px">' . $Response . '</div>';
                }

            } else {
				echo '<div class="updated fade" style="padding:6px">' . __("تنظیمات ذخیره شدند .", "gravityformszarinpal") . '</div>'; 
            }
        }
		else if ( isset($_GET['subview']) && $_GET['subview'] == 'gf_zarinpal' && isset($_GET['updated']) ){
			echo '<div class="updated fade" style="padding:6px">' . __("تنظیمات ذخیره شدند .", "gravityformszarinpal") . '</div>';
		}
        ?>

        <form action="" method="post">

            <?php wp_nonce_field("update", "gf_zarinpal_update") ?>

            <h3>
				<span>
				<i class="fa fa-credit-card"></i>
                    <?php _e("تنظیمات زرین پال", "gravityformszarinpal") ?>
				</span>
            </h3>

            <table class="form-table">

                <tr>
                    <th scope="row"><label
                            for="gf_zarinpal_configured"><?php _e("فعالسازی", "gravityformszarinpal"); ?></label></th>
                    <td>
                        <input type="checkbox" name="gf_zarinpal_configured"
                               id="gf_zarinpal_configured" <?php echo get_option("gf_zarinpal_configured") ? "checked='checked'" : "" ?>/>
                        <label class="inline"
                               for="gf_zarinpal_configured"><?php _e("بله", "gravityformszarinpal"); ?></label>
                    </td>
                </tr>


                <tr>
                    <th scope="row"><label
                            for="gf_zarinpal_server"><?php _e("کشور سرور", "gravityformszarinpal"); ?></label></th>
                    <td>

                        <input type="radio" name="gf_zarinpal_server"
                               value="Iran" <?php echo rgar($settings, 'server') == "Iran" ? "checked='checked'" : "" ?>/>
                        <?php _e("ایران", "gravityformszarinpal"); ?>

                        <input type="radio" name="gf_zarinpal_server"
                               value="German" <?php echo rgar($settings, 'server') != "Iran" ? "checked='checked'" : "" ?>/>
                        <?php _e("آلمان (پیشنهادی)", "gravityformszarinpal"); ?>

                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="gf_zarinpal_merchent"><?php _e("مرچنت", "gravityformszarinpal"); ?></label></th>
                    <td>
                        <input style="width:350px;text-align:left;direction:ltr !important" type="text"
                               id="gf_zarinpal_merchent" name="gf_zarinpal_merchent"
                               value="<?php echo sanitize_text_field(rgar($settings, 'merchent')) ?>"/>
                    </td>
                </tr>

                <?php

                $gateway_title = __("زرین پال", "gravityformszarinpal");

                if (sanitize_text_field(rgar($settings, 'gname')))
                    $gateway_title = sanitize_text_field($settings["gname"]);

                ?>
                <tr>
                    <th scope="row">
                        <label for="gf_zarinpal_gname">
                            <?php _e("عنوان", "gravityformszarinpal"); ?>
                            <?php gform_tooltip('gateway_name') ?>
                        </label>
                    </th>
                    <td>
                        <input style="width:350px;" type="text" id="gf_zarinpal_gname" name="gf_zarinpal_gname"
                               value="<?php echo $gateway_title; ?>"/>
                    </td>
                </tr>

                <tr>
                    <td colspan="2"><input style="font-family:tahoma !important;" type="submit"
                                           name="gf_zarinpal_submit" class="button-primary"
                                           value="<?php _e("ذخیره تنظیمات", "gravityformszarinpal") ?>"/></td>
                </tr>

            </table>

        </form>

        <form action="" method="post">
            <?php

            wp_nonce_field("uninstall", "gf_zarinpal_uninstall");

            if (self::has_access("gravityforms_zarinpal_uninstall")) {

                ?>
                <div class="hr-divider"></div>
                <div class="delete-alert alert_red">

                    <h3>
                        <i class="fa fa-exclamation-triangle gf_invalid"></i>
                        <?php _e("غیر فعالسازی افزونه دروازه پرداخت زرین پال", "gravityformszarinpal"); ?>
                    </h3>

                    <div
                        class="gf_delete_notice"><?php _e("تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به زرین پال حذف خواهد شد", "gravityformszarinpal") ?></div>

                    <?php
                    $uninstall_button = '<input  style="font-family:tahoma !important;" type="submit" name="uninstall" value="' . __("غیر فعال سازی درگاه زرین پال", "gravityformszarinpal") . '" class="button" onclick="return confirm(\'' . __("تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به زرین پال حذف خواهد شد . آیا همچنان مایل به غیر فعالسازی میباشید؟", "gravityformszarinpal") . '\');"/>';
                    echo apply_filters("gform_zarinpal_uninstall_button", $uninstall_button);
                    ?>

                </div>

            <?php } ?>
        </form>
        <?php
    }


    // ------------------------GravityForms.IR-------------------------
    public static function get_gname()
    {
        $settings = get_option("gf_zarinpal_settings");
        if (isset($settings["gname"]))
            $gname = $settings["gname"];
        else
            $gname = __('زرین پال', 'gravityformszarinpal');
        return $gname;
    }

    // ------------------------GravityForms.IR-------------------------
    private static function get_merchent()
    {
        $settings = get_option("gf_zarinpal_settings");
        $merchent = isset($settings["merchent"]) ? $settings["merchent"] : '';
        return trim($merchent);
    }

    // ------------------------GravityForms.IR-------------------------
    private static function get_server()
    {
        $settings = get_option("gf_zarinpal_settings");
        $server = isset($settings["server"]) ? $settings["server"] : '';
        return $server;
    }


    // #3
    // ------------------------GravityForms.IR-------------------------
    private static function config_page()
    {

        wp_register_style('gform_admin_zarinpal', GFCommon::get_base_url() . '/css/admin.css');
        wp_print_styles(array('jquery-ui-styles', 'gform_admin_zarinpal', 'wp-pointer')); ?>

        <?php if (is_rtl()) { ?>
        <style type="text/css">
            table.gforms_form_settings th {
                text-align: right !important;
            }
        </style>
    <?php } ?>

        <div class="wrap gforms_edit_form gf_browser_gecko">

            <?php
            $id = !rgempty("zarinpal_setting_id") ? rgpost("zarinpal_setting_id") : absint(rgget("id"));
            $config = empty($id) ? array("meta" => array(), "is_active" => true) : GFZarinPalData::get_feed($id);
            $get_feeds = GFZarinPalData::get_feeds();
            $form_name = '';


            $_get_form_id = rgget('fid') ? rgget('fid') : (!empty($config["form_id"]) ? $config["form_id"] : '');

            foreach ((array)$get_feeds as $get_feed) {
                if ($get_feed['id'] == $id) {
                    $form_name = $get_feed['form_title'];
                }
            }
            ?>


            <h2 class="gf_admin_page_title"><?php _e("پیکربندی درگاه زرین پال", "gravityformszarinpal") ?>

                <?php if (!empty($_get_form_id)) { ?>
                    <span class="gf_admin_page_subtitle">
					<span
                        class="gf_admin_page_formid"><?php echo sprintf(__("فید: %s", "gravityformszarinpal"), $id) ?></span>
					<span
                        class="gf_admin_page_formname"><?php echo sprintf(__("فرم: %s", "gravityformszarinpal"), $form_name) ?></span>
				</span>
                <?php } ?>

            </h2>
            <a class="button add-new-h2" href="admin.php?page=gf_settings&subview=gf_zarinpal"
               style="margin:8px 9px;"><?php _e("تنظیمات حساب زرین پال", "gravityformszarinpal") ?></a>

            <?php
            if (!rgempty("gf_zarinpal_submit")) {
                // ------------------
                check_admin_referer("update", "gf_zarinpal_feed");

                $config["form_id"] = absint(rgpost("gf_zarinpal_form"));
                $config["meta"]["type"] = rgpost("gf_zarinpal_type");
                $config["meta"]["addon"] = rgpost("gf_zarinpal_addon");
                $config["meta"]["shaparak"] = rgpost("gf_zarinpal_shaparak");
                $config["meta"]["update_post_action1"] = rgpost('gf_zarinpal_update_action1');
                $config["meta"]["update_post_action2"] = rgpost('gf_zarinpal_update_action2');

                // ------------------
                if (isset($form["notifications"])) {
                    $config["meta"]["delay_notifications"] = rgpost('gf_zarinpal_delay_notifications');
                    $config["meta"]["selected_notifications"] = rgpost('gf_zarinpal_selected_notifications');
                } else {
                    if (isset($config["meta"]["delay_notifications"]))
                        unset($config["meta"]["delay_notifications"]);
                    if (isset($config["meta"]["selected_notifications"]))
                        unset($config["meta"]["selected_notifications"]);
                }


                // ------------------
                $config["meta"]["gf_zarinpal_conf_1"] = rgpost('gf_zarinpal_conf_1');
                $config["meta"]["gf_zarinpal_conf_2"] = rgpost('gf_zarinpal_conf_2');
                $config["meta"]["gf_zarinpal_conf_3"] = rgpost('gf_zarinpal_conf_3');

                // ------------------
                $config["meta"]["gf_zarinpal_notif_1"] = rgpost('gf_zarinpal_notif_1');
                $config["meta"]["gf_zarinpal_notif_2"] = rgpost('gf_zarinpal_notif_2');
                $config["meta"]["gf_zarinpal_notif_3"] = rgpost('gf_zarinpal_notif_3');
                $config["meta"]["gf_zarinpal_notif_4"] = rgpost('gf_zarinpal_notif_4');


                // ------------------
                $config["meta"]["zarinpal_conditional_enabled"] = rgpost('gf_zarinpal_conditional_enabled');
                $config["meta"]["zarinpal_conditional_field_id"] = rgpost('gf_zarinpal_conditional_field_id');
                $config["meta"]["zarinpal_conditional_operator"] = rgpost('gf_zarinpal_conditional_operator');
                $config["meta"]["zarinpal_conditional_value"] = rgpost('gf_zarinpal_conditional_value');

                // ------------------
                $config["meta"]["desc_pm"] = rgpost("gf_zarinpal_desc_pm");
                $config["meta"]["customer_fields_desc"] = rgpost("zarinpal_customer_field_desc");
                $config["meta"]["customer_fields_email"] = rgpost("zarinpal_customer_field_email");
                $config["meta"]["customer_fields_mobile"] = rgpost("zarinpal_customer_field_mobile");


                $safe_data = array();
				foreach ( $config["meta"] as $key => $val )
					if ( ! is_array($val) )
						$safe_data[$key] = sanitize_text_field($val);
					else
						$safe_data[$key] = array_map( 'sanitize_text_field' , $val);
				$config["meta"] = $safe_data;

                $config = apply_filters(self::$author . '_gform_gateway_save_config', $config);
                $config = apply_filters(self::$author . '_gform_zarinpal_save_config', $config);

                $id = GFZarinPalData::update_feed($id, $config["form_id"], $config["is_active"], $config["meta"]);
                if (!headers_sent()) {
                    wp_redirect(admin_url('admin.php?page=gf_zarinpal&view=edit&id=' . $id . '&updated=true'));
                    exit;
                } else {
                    echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" . admin_url('admin.php?page=gf_zarinpal&view=edit&id=' . $id . '&updated=true') . "'; };</script>";
                    exit;
                }
                ?>
                <div class="updated fade"
                     style="padding:6px"><?php echo sprintf(__("فید به روز شد . %sبازگشت به لیست%s.", "gravityformszarinpal"), "<a href='?page=gf_zarinpal'>", "</a>") ?></div>
                <?php
            }

            $_get_form_id = rgget('fid') ? rgget('fid') : (!empty($config["form_id"]) ? $config["form_id"] : '');

            $form = array();
            if (!empty($_get_form_id))
                $form = RGFormsModel::get_form_meta($_get_form_id);

            if (rgget('updated') == 'true') {

                $id = empty($id) && isset($_GET['id']) ? rgget('id') : $id;
				$id = absint($id); ?>

                <div class="updated fade"
                     style="padding:6px"><?php echo sprintf(__("فید به روز شد . %sبازگشت به لیست%s . ", "gravityformszarinpal"), "<a href='?page=gf_zarinpal'>", "</a>") ?></div>

                <?php
            }


            if (!empty($_get_form_id)) { ?>

                <div id="gf_form_toolbar">
                    <ul id="gf_form_toolbar_links">

                        <?php
                        $menu_items = apply_filters('gform_toolbar_menu', GFForms::get_toolbar_menu_items($_get_form_id), $_get_form_id);
                        echo GFForms::format_toolbar_menu_items($menu_items); ?>

                        <li class="gf_form_switcher">
                            <label for="export_form"><?php _e('یک فید انتخاب کنید', 'gravityformszarinpal') ?></label>
                            <?php
                            $feeds = GFZarinPalData::get_feeds();
                            if (RG_CURRENT_VIEW != 'entry') { ?>
                                <select name="form_switcher" id="form_switcher"
                                        onchange="GF_SwitchForm(jQuery(this).val());">
                                    <option value=""><?php _e('تغییر فید زرین پال', 'gravityformszarinpal') ?></option>
                                    <?php foreach ($feeds as $feed) {
                                        $selected = $feed["id"] == $id ? "selected='selected'" : ""; ?>
                                        <option
                                            value="<?php echo $feed["id"] ?>" <?php echo $selected ?> ><?php echo sprintf(__('فرم: %s (فید: %s)', 'gravityformszarinpal'), $feed["form_title"], $feed["id"]) ?></option>
                                    <?php } ?>
                                </select>
                                <?php
                            }
                            ?>
                        </li>
                    </ul>
                </div>
            <?php } ?>

            <div id="gform_tab_group" class="gform_tab_group vertical_tabs">
                <?php if (!empty($_get_form_id)) { ?>
                    <ul id="gform_tabs" class="gform_tabs">
                        <?php
                        $title = '';
                        $get_form = GFFormsModel::get_form_meta($_get_form_id);
                        $current_tab = rgempty('subview', $_GET) ? 'settings' : rgget('subview');
                        $current_tab = !empty($current_tab) ? $current_tab : ' ';
                        $setting_tabs = GFFormSettings::get_tabs($get_form['id']);
                        if (!$title) {
                            foreach ($setting_tabs as $tab) {
                                $query = array('page' => 'gf_edit_forms', 'view' => 'settings', 'subview' => $tab['name'], 'id' => $get_form['id']);
                                $url = add_query_arg($query, admin_url('admin.php'));
                                echo $tab['name'] == 'zarinpal' ? '<li class="active">' : '<li>';
                                ?>
                                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($tab['label']) ?></a>
                                <span></span>
                                </li>
                                <?php
                            }
                        }
                        ?>
                    </ul>
                <?php }
                $has_product = false;
                if (isset($form["fields"])) {
                    foreach ($form["fields"] as $field) {
                        $shipping_field = GFAPI::get_fields_by_type($form, array('shipping'));
                        if ($field["type"] == "product" || !empty($shipping_field)) {
                            $has_product = true;
                            break;
                        }
                    }
                } else if (empty($_get_form_id)) {
                    $has_product = true;
                }
                ?>
                <div id="gform_tab_container_<?php echo $_get_form_id ? $_get_form_id : 1 ?>"
                     class="gform_tab_container">
                    <div class="gform_tab_content" id="tab_<?php echo !empty($current_tab) ? $current_tab : '' ?>">
                        <div id="form_settings" class="gform_panel gform_panel_form_settings">
                            <h3>
								<span>
									<i class="fa fa-credit-card"></i>
                                    <?php _e("پیکربندی درگاه زرین پال", "gravityformszarinpal"); ?>
								</span>
                            </h3>
                            <form method="post" action="" id="gform_form_settings">

                                <?php wp_nonce_field("update", "gf_zarinpal_feed") ?>


                                <input type="hidden" name="zarinpal_setting_id" value="<?php echo $id ?>"/>
                                <table class="form-table gforms_form_settings" cellspacing="0" cellpadding="0">
                                    <tbody>
                                    <tr>
                                        <td colspan="2">
                                            <h4 class="gf_settings_subgroup_title">
                                                <?php _e("پیکربندی درگاه زرین پال", "gravityformszarinpal"); ?>
                                            </h4>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>
                                            <?php _e("انتخاب فرم", "gravityformszarinpal"); ?>
                                        </th>
                                        <td>
                                            <select id="gf_zarinpal_form" name="gf_zarinpal_form"
                                                    onchange="GF_SwitchFid(jQuery(this).val());">
                                                <option
                                                    value=""><?php _e("یک فرم انتخاب نمایید", "gravityformszarinpal"); ?> </option>
                                                <?php
                                                $available_forms = GFZarinPalData::get_available_forms();
                                                foreach ($available_forms as $current_form) {
                                                    $selected = absint($current_form->id) == $_get_form_id ? 'selected="selected"' : ''; ?>
                                                    <option
                                                        value="<?php echo absint($current_form->id) ?>" <?php echo $selected; ?>><?php echo esc_html($current_form->title) ?></option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                            <img
                                                src="<?php echo esc_url(GFCommon::get_base_url()) ?>/images/spinner.gif"
                                                id="zarinpal_wait" style="display: none;"/>
                                        </td>
                                    </tr>

                                    </tbody>
                                </table>

                                <?php if (empty($has_product) || !$has_product) { ?>
                                    <div id="gf_zarinpal_invalid_product_form" class="gf_zarinpal_invalid_form"
                                         style="background-color:#FFDFDF; margin-top:4px; margin-bottom:6px;padding:18px; border:1px dotted #C89797;">
                                        <?php _e("فرم انتخاب شده هیچ گونه فیلد قیمت گذاری ندارد، لطفا پس از افزودن این فیلدها مجددا اقدام نمایید.", "gravityformszarinpal") ?>
                                    </div>
                                <?php } else { ?>
                                    <table class="form-table gforms_form_settings"
                                           id="zarinpal_field_group" <?php echo empty($_get_form_id) ? "style='display:none;'" : "" ?>
                                           cellspacing="0" cellpadding="0">
                                        <tbody>

                                        <tr>
                                            <th>
                                                <?php _e("فرم ثبت نام", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <input type="checkbox" name="gf_zarinpal_type"
                                                       id="gf_zarinpal_type_subscription"
                                                       value="subscription" <?php echo rgar($config['meta'], 'type') == "subscription" ? "checked='checked'" : "" ?>/>
                                                <label for="gf_zarinpal_type"></label>
                                                <span
                                                    class="description"><?php _e('در صورتی که تیک بزنید عملیات ثبت نام که توسط افزونه User Registration انجام خواهد شد تنها برای پرداخت های موفق عمل خواهد کرد'); ?></span>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan="5">
                                                <h4 class="gf_settings_subgroup_title">
                                                    <?php _e("فیلد های ورودی زرین پال", "gravityformszarinpal"); ?>
                                                </h4>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
                                                <?php _e("توضیحات پرداخت", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <input type="text" name="gf_zarinpal_desc_pm" id="gf_zarinpal_desc_pm"
                                                       class="fieldwidth-1"
                                                       value="<?php echo rgar($config["meta"], "desc_pm") ?>"/>
                                                <span
                                                    class="description"><?php _e("شورت کد ها : {form_id} , {form_title} , {entry_id}", "gravityformszarinpal"); ?></span>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
                                                <?php _e("توضیح تکمیلی", "gravityformszarinpal"); ?>
                                            </th>
                                            <td class="zarinpal_customer_fields_desc">
                                                <?php
                                                if (!empty($form))
                                                    echo self::get_customer_information_desc($form, $config);
                                                ?>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
                                                <?php _e("ایمیل پرداخت کننده", "gravityformszarinpal"); ?>
                                            </th>
                                            <td class="zarinpal_customer_fields_email">
                                                <?php
                                                if (!empty($form))
                                                    echo self::get_customer_information_email($form, $config);
                                                ?>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
                                                <?php _e("تلفن همراه پرداخت کننده", "gravityformszarinpal"); ?>
                                            </th>
                                            <td class="zarinpal_customer_fields_mobile">
                                                <?php
                                                if (!empty($form))
                                                    echo self::get_customer_information_mobile($form, $config);
                                                ?>
                                            </td>
                                        </tr>


                                        <?php $display_post_fields = !empty($form) ? GFCommon::has_post_field($form["fields"]) : false; ?>

                                        <tr <?php echo $display_post_fields ? "" : "style='display:none;'" ?>>
                                            <td colspan="2">
                                                <h4 class="gf_settings_subgroup_title">
                                                    <?php _e("تنظیمات مربوط به وضعیت پست ها", "gravityformszarinpal"); ?>
                                                </h4>
                                            </td>
                                        </tr>

                                        <tr <?php echo $display_post_fields ? "" : "style='display:none;'" ?>>
                                            <th>
                                                <?php _e("بعد ار پرداخت موفق", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <select id="gf_zarinpal_update_action1"
                                                        name="gf_zarinpal_update_action1">
                                                    <option
                                                        value="default" <?php echo rgar($config["meta"], "update_post_action1") == "default" ? "selected='selected'" : "" ?>><?php _e("وضعیت پیشفرض فرم", "gravityformszarinpal") ?></option>
                                                    <option
                                                        value="publish" <?php echo rgar($config["meta"], "update_post_action1") == "publish" ? "selected='selected'" : "" ?>><?php _e("منشتر شده", "gravityformszarinpal") ?></option>
                                                    <option
                                                        value="draft" <?php echo rgar($config["meta"], "update_post_action1") == "draft" ? "selected='selected'" : "" ?>><?php _e("پیشنویس", "gravityformszarinpal") ?></option>
                                                    <option
                                                        value="pending" <?php echo rgar($config["meta"], "update_post_action1") == "pending" ? "selected='selected'" : "" ?>><?php _e("در انتظار بررسی", "gravityformszarinpal") ?></option>
                                                    <option
                                                        value="private" <?php echo rgar($config["meta"], "update_post_action1") == "private" ? "selected='selected'" : "" ?>><?php _e("خصوصی", "gravityformszarinpal") ?></option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr <?php echo $display_post_fields ? "" : "style='display:none;'" ?>>
                                            <th>
                                                <?php _e("بعد ار پرداخت ناموفق", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <select id="gf_zarinpal_update_action2"
                                                        name="gf_zarinpal_update_action2">
                                                    <option
                                                        value="dont" <?php echo rgar($config["meta"], "update_post_action2") == "dont" ? "selected='selected'" : "" ?>><?php _e("عدم ایجاد پست", "gravityformszarinpal") ?></option>
                                                    <option
                                                        value="default" <?php echo rgar($config["meta"], "update_post_action2") == "default" ? "selected='selected'" : "" ?>><?php _e("وضعیت پیشفرض فرم", "gravityformszarinpal") ?></option>
                                                    <option
                                                        value="publish" <?php echo rgar($config["meta"], "update_post_action2") == "publish" ? "selected='selected'" : "" ?>><?php _e("منشتر شده", "gravityformszarinpal") ?></option>
                                                    <option
                                                        value="draft" <?php echo rgar($config["meta"], "update_post_action2") == "draft" ? "selected='selected'" : "" ?>><?php _e("پیشنویس", "gravityformszarinpal") ?></option>
                                                    <option
                                                        value="pending" <?php echo rgar($config["meta"], "update_post_action2") == "pending" ? "selected='selected'" : "" ?>><?php _e("در انتظار بررسی", "gravityformszarinpal") ?></option>
                                                    <option
                                                        value="private" <?php echo rgar($config["meta"], "update_post_action2") == "private" ? "selected='selected'" : "" ?>><?php _e("خصوصی", "gravityformszarinpal") ?></option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr <?php echo !isset($form["confirmations"]) ? "style='display:none;'" : "" ?>>
                                            <td colspan="2">
                                                <h4 class="gf_settings_subgroup_title">
                                                    <?php _e("تنظیمات تاییدیه ها", "gravityformszarinpal"); ?>
                                                </h4>
                                            </td>
                                        </tr>

                                        <?php $confirmations = isset($form['confirmations']) ? $form['confirmations'] : array(); ?>
                                        <tr id="gf_zarinpal_confirmations_1" <?php echo !isset($form["confirmations"]) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <?php _e("بعد از پرداخت موفق", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <?php
                                                $selected_confirmations = !empty($config["meta"]["gf_zarinpal_conf_1"]) ? $config["meta"]["gf_zarinpal_conf_1"] : array();
                                                foreach ($confirmations as $confirmation) { ?>
                                                    <li class="gf_zarinpal_confirmation">
                                                        <input id="gf_zarinpal_conf_1_<?php echo $confirmation['id'] ?>"
                                                               name="gf_zarinpal_conf_1[]" type="checkbox"
                                                               class="confirmation_checkbox"
                                                               value="<?php echo $confirmation['id'] ?>" <?php checked(true, in_array($confirmation['id'], $selected_confirmations)) ?> />
                                                        <label
                                                            for="gf_zarinpal_conf_1_<?php echo $confirmation['id'] ?>"
                                                            class="inline"
                                                            for="gf_zarinpal_selected_confirmations"><?php echo $confirmation['name']; ?></label>
                                                    </li>
                                                <?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_zarinpal_confirmations_2" <?php echo !isset($form["confirmations"]) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <?php _e("بعد از پرداخت ناموفق", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <?php
                                                $selected_confirmations = !empty($config["meta"]["gf_zarinpal_conf_2"]) ? $config["meta"]["gf_zarinpal_conf_2"] : array();
                                                foreach ($confirmations as $confirmation) { ?>
                                                    <li class="gf_zarinpal_confirmation">
                                                        <input id="gf_zarinpal_conf_2_<?php echo $confirmation['id'] ?>"
                                                               name="gf_zarinpal_conf_2[]" type="checkbox"
                                                               class="confirmation_checkbox"
                                                               value="<?php echo $confirmation['id'] ?>" <?php checked(true, in_array($confirmation['id'], $selected_confirmations)) ?> />
                                                        <label
                                                            for="gf_zarinpal_conf_2_<?php echo $confirmation['id'] ?>"
                                                            class="inline"
                                                            for="gf_zarinpal_selected_confirmations"><?php echo $confirmation['name']; ?></label>
                                                    </li>
                                                <?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_zarinpal_confirmations_3" <?php echo !isset($form["confirmations"]) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <?php _e("بعد از انصراف", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <?php
                                                $selected_confirmations = !empty($config["meta"]["gf_zarinpal_conf_3"]) ? $config["meta"]["gf_zarinpal_conf_3"] : array();
                                                foreach ($confirmations as $confirmation) { ?>
                                                    <li class="gf_zarinpal_confirmation">
                                                        <input id="gf_zarinpal_conf_3_<?php echo $confirmation['id'] ?>"
                                                               name="gf_zarinpal_conf_3[]" type="checkbox"
                                                               class="confirmation_checkbox"
                                                               value="<?php echo $confirmation['id'] ?>" <?php checked(true, in_array($confirmation['id'], $selected_confirmations)) ?> />
                                                        <label
                                                            for="gf_zarinpal_conf_3_<?php echo $confirmation['id'] ?>"
                                                            class="inline"
                                                            for="gf_zarinpal_selected_confirmations"><?php echo $confirmation['name']; ?></label>
                                                    </li>
                                                <?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_zarinpal_confirmations" <?php echo !isset($form["confirmations"]) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <br><br><?php _e("توجه !", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <p class="description"><?php _e("در صورتی که هیچ تاییدیه ای برای نمایش وجود نداشته باشد یا منطق شرطی سایر تاییدیه ها برقرار نباشد ، به صورت خودکار از تاییدیه پیشفرض استفاده خواهد شد. پس به منطق شرطی تاییدیه دقت نمایید!", "gravityformszarinpal"); ?></p>
                                                <p class="description"><?php _e("برای نمایش علت خطا میتوانید از شورت کد {fault} داخل متن تاییدیه ها استفاده نمایید.", "gravityformszarinpal"); ?></p>
                                                <p class="description"><?php _e("همچنین برچسب های مربوط به کد تراکنش و ... هم داخل لیست برچسب های تاییدیه ها و اعلان ها موجود می باشند.", "gravityformszarinpal"); ?></p>
                                            </td>
                                        </tr>

                                        <tr <?php echo !isset($form["notifications"]) ? "style='display:none;'" : "" ?>>
                                            <td colspan="2">
                                                <h4 class="gf_settings_subgroup_title">
                                                    <?php _e("تنظیمات اعلان ها", "gravityformszarinpal"); ?>
                                                </h4>
                                            </td>
                                        </tr>

                                        <?php $notifications = GFCommon::get_notifications('form_submission', $form); ?>
                                        <tr id="gf_zarinpal_notifications" <?php echo !isset($form["notifications"]) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <?php _e("بلافاصله بعد از ثبت فرم", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <?php
                                                $selected_notifications = !empty($config["meta"]["gf_zarinpal_notif_1"]) ? $config["meta"]["gf_zarinpal_notif_1"] : array();
                                                foreach ($notifications as $notification) { ?>
                                                    <li class="gf_zarinpal_notification">
                                                        <input
                                                            id="gf_zarinpal_notif_1_<?php echo $notification['id'] ?>"
                                                            name="gf_zarinpal_notif_1[]" type="checkbox"
                                                            class="notification_checkbox"
                                                            value="<?php echo $notification['id'] ?>" <?php checked(true, in_array($notification['id'], $selected_notifications)) ?> />
                                                        <label
                                                            for="gf_zarinpal_notif_1_<?php echo $notification['id'] ?>"
                                                            class="inline"
                                                            for="gf_zarinpal_selected_notifications"><?php echo $notification['name']; ?></label>
                                                    </li>
                                                <?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_zarinpal_notifications" <?php echo !isset($form["notifications"]) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <?php _e("بعد از پرداخت موفق", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <?php
                                                $selected_notifications = !empty($config["meta"]["gf_zarinpal_notif_2"]) ? $config["meta"]["gf_zarinpal_notif_2"] : array();
                                                foreach ($notifications as $notification) { ?>
                                                    <li class="gf_zarinpal_notification">
                                                        <input
                                                            id="gf_zarinpal_notif_2_<?php echo $notification['id'] ?>"
                                                            name="gf_zarinpal_notif_2[]" type="checkbox"
                                                            class="notification_checkbox"
                                                            value="<?php echo $notification['id'] ?>" <?php checked(true, in_array($notification['id'], $selected_notifications)) ?> />
                                                        <label
                                                            for="gf_zarinpal_notif_2_<?php echo $notification['id'] ?>"
                                                            class="inline"
                                                            for="gf_zarinpal_selected_notifications"><?php echo $notification['name']; ?></label>
                                                    </li>
                                                <?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_zarinpal_notifications" <?php echo !isset($form["notifications"]) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <?php _e("بعد از پرداخت ناموفق", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <?php
                                                $selected_notifications = !empty($config["meta"]["gf_zarinpal_notif_3"]) ? $config["meta"]["gf_zarinpal_notif_3"] : array();
                                                foreach ($notifications as $notification) { ?>
                                                    <li class="gf_zarinpal_notification">
                                                        <input
                                                            id="gf_zarinpal_notif_3_<?php echo $notification['id'] ?>"
                                                            name="gf_zarinpal_notif_3[]" type="checkbox"
                                                            class="notification_checkbox"
                                                            value="<?php echo $notification['id'] ?>" <?php checked(true, in_array($notification['id'], $selected_notifications)) ?> />
                                                        <label
                                                            for="gf_zarinpal_notif_3_<?php echo $notification['id'] ?>"
                                                            class="inline"
                                                            for="gf_zarinpal_selected_notifications"><?php echo $notification['name']; ?></label>
                                                    </li>
                                                <?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_zarinpal_notifications" <?php echo !isset($form["notifications"]) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <?php _e("بعد از انصراف", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <?php
                                                $selected_notifications = !empty($config["meta"]["gf_zarinpal_notif_4"]) ? $config["meta"]["gf_zarinpal_notif_4"] : array();
                                                foreach ($notifications as $notification) { ?>
                                                    <li class="gf_zarinpal_notification">
                                                        <input
                                                            id="gf_zarinpal_notif_4_<?php echo $notification['id'] ?>"
                                                            name="gf_zarinpal_notif_4[]" type="checkbox"
                                                            class="notification_checkbox"
                                                            value="<?php echo $notification['id'] ?>" <?php checked(true, in_array($notification['id'], $selected_notifications)) ?> />
                                                        <label
                                                            for="gf_zarinpal_notif_4_<?php echo $notification['id'] ?>"
                                                            class="inline"
                                                            for="gf_zarinpal_selected_notifications"><?php echo $notification['name']; ?></label>
                                                    </li>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <tr id="gf_zarinpal_notifications" <?php echo (!isset($form["confirmations"]) && !isset($form["notifications"])) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <br><?php _e("توجه !", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <p class="description"><?php _e('در صورتی که مبلغ تراکنش 0 باشد، فرم به درگاه متصل نخواهد شد ولی تاییدیه و اعلان وضعیت "موفق" اعمال خواهد شد.', 'gravityformszarinpal'); ?></p>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan="2">
                                                <h4 class="gf_settings_subgroup_title">
                                                    <?php _e("سایر تنظیمات درگاه", "gravityformszarinpal"); ?>
                                                </h4>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
                                                <?php echo __("سازگاری با ادان ها", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <input type="checkbox" name="gf_zarinpal_addon"
                                                       id="gf_zarinpal_addon_true"
                                                       value="true" <?php echo rgar($config['meta'], 'addon') == "true" ? "checked='checked'" : "" ?>/>
                                                <label for="gf_zarinpal_addon"></label>
                                                <span
                                                    class="description"><?php _e('گرویتی فرم دارای ادان های مختلف وابسته به GFAddon نظیر ایمیل مارکتینگ و ... می باشد که دارای متد add_delayed_payment_support هستند. در صورتی که میخواهید این ادان ها تنها در صورت تراکنش موفق عمل کنند این گزینه را تیک بزنید.', 'gravityformszarinpal'); ?></span>
                                            </td>
                                        </tr>

                                        <?php $minprice = GFCommon::get_currency() == 'IRT' ? __("100 تومان", "gravityformszarinpal") : __("1000 ریال", "gravityformszarinpal"); ?>
                                        <tr>
                                            <th>
                                                <?php echo __("قیمت بین 0 تا ", "gravityformszarinpal") . $minprice; ?>
                                            </th>
                                            <td>
                                                <input type="radio" name="gf_zarinpal_shaparak"
                                                       id="gf_zarinpal_shaparak_raygan"
                                                       value="raygan" <?php echo rgar($config['meta'], 'shaparak') != "sadt" ? "checked" : "" ?>/>
                                                <label class="inline"
                                                       for="gf_zarinpal_shaparak_raygan"><?php _e("رایگان شود", "gravityformszarinpal"); ?></label>

                                                <input type="radio" name="gf_zarinpal_shaparak"
                                                       id="gf_zarinpal_shaparak_sadt"
                                                       value="sadt" <?php echo rgar($config['meta'], 'shaparak') == "sadt" ? "checked" : "" ?>/>
                                                <label class="inline"
                                                       for="gf_zarinpal_shaparak_sadt"><?php echo $minprice . __(" شود ", "gravityformszarinpal"); ?></label>
                                                <span
                                                    class="description"><?php _e('(قابل استفاده برای زمانیکه منطق شرطی درگاه فعال نباشد)', 'gravityformszarinpal'); ?></span>

                                            </td>
                                        </tr>


                                        <?php
                                        do_action(self::$author . '_gform_gateway_config', $config, $form);
                                        do_action(self::$author . '_gform_zarinpal_config', $config, $form);
                                        ?>

                                        <tr>
                                            <th>
                                                <?php _e("منطق شرطی", "gravityformszarinpal"); ?>
                                            </th>
                                            <td>
                                                <input type="checkbox" id="gf_zarinpal_conditional_enabled"
                                                       name="gf_zarinpal_conditional_enabled" value="1"
                                                       onclick="if(this.checked){jQuery('#gf_zarinpal_conditional_container').fadeIn('fast');} else{ jQuery('#gf_zarinpal_conditional_container').fadeOut('fast'); }" <?php echo rgar($config['meta'], 'zarinpal_conditional_enabled') ? "checked='checked'" : "" ?>/>
                                                <label
                                                    for="gf_zarinpal_conditional_enable"><?php _e("فعالسازی این درگاه اگر شرط زیر برقرار باشد", "gravityformszarinpal"); ?></label><br/>

                                                <table cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td>
                                                            <div
                                                                id="gf_zarinpal_conditional_container" <?php echo !rgar($config['meta'], 'zarinpal_conditional_enabled') ? "style='display:none'" : "" ?>>
                                                                <div id="gf_zarinpal_conditional_fields"
                                                                     style="display:none">
                                                                    <select id="gf_zarinpal_conditional_field_id"
                                                                            name="gf_zarinpal_conditional_field_id"
                                                                            class="optin_select"
                                                                            onchange='jQuery("#gf_zarinpal_conditional_value_container").html(GetFieldValues(jQuery(this).val(), "", 20));'></select>
                                                                    <select id="gf_zarinpal_conditional_operator"
                                                                            name="gf_zarinpal_conditional_operator">
                                                                        <option
                                                                            value="is" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "is" ? "selected='selected'" : "" ?>><?php _e("هست", "gravityformszarinpal") ?></option>
                                                                        <option
                                                                            value="isnot" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "isnot" ? "selected='selected'" : "" ?>><?php _e("نیست", "gravityformszarinpal") ?></option>
                                                                        <option
                                                                            value=">" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == ">" ? "selected='selected'" : "" ?>><?php _e("بزرگ تر است از", "gravityformszarinpal") ?></option>
                                                                        <option
                                                                            value="<" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "<" ? "selected='selected'" : "" ?>><?php _e("کوچک تر است از", "gravityformszarinpal") ?></option>
                                                                        <option
                                                                            value="contains" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "contains" ? "selected='selected'" : "" ?>><?php _e("شامل میشود ", "gravityformszarinpal") ?></option>
                                                                        <option
                                                                            value="starts_with" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "starts_with" ? "selected='selected'" : "" ?>><?php _e("شروع میشود با", "gravityformszarinpal") ?></option>
                                                                        <option
                                                                            value="ends_with" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "ends_with" ? "selected='selected'" : "" ?>><?php _e("تمام میشود با", "gravityformszarinpal") ?></option>
                                                                    </select>
                                                                    <div id="gf_zarinpal_conditional_value_container"
                                                                         name="gf_zarinpal_conditional_value_container"
                                                                         style="display:inline;"></div>
                                                                </div>
                                                                <div id="gf_zarinpal_conditional_message"
                                                                     style="display:none;background-color:#FFDFDF; margin-top:4px; margin-bottom:6px;padding:18px; border:1px dotted #C89797;">
                                                                    <?php _e("برای قرار دادن منطق شرطی ، باید فیلدهای فرم شما هم قابلیت منطق شرطی را داشته باشند . ", "gravityformszarinpal") ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <input type="submit" class="button-primary gfbutton"
                                                       name="gf_zarinpal_submit"
                                                       value="<?php _e("ذخیره", "gravityformszarinpal"); ?>"/>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                <?php } ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            function GF_SwitchFid(fid) {
                jQuery("#zarinpal_wait").show();
                document.location = "?page=gf_zarinpal&view=edit&fid=" + fid;
            }
            function GF_SwitchForm(id) {
                if (id.length > 0) {
                    document.location = "?page=gf_zarinpal&view=edit&id=" + id;
                }
            }
            <?php
            if( !empty($_get_form_id)){ ?>
            form = <?php echo !empty($form) ? GFCommon::json_encode($form) : array() ?> ;
            jQuery(document).ready(function () {
                var selectedField = "";
                var selectedValue = "";
                <?php if ( !empty($config["meta"]["zarinpal_conditional_field_id"]) ) { ?>
                var selectedField = "<?php echo str_replace('"', '\"', $config["meta"]["zarinpal_conditional_field_id"])?>";
                var selectedValue = "<?php echo str_replace('"', '\"', $config["meta"]["zarinpal_conditional_value"])?>";
                <?php } ?>
                SetZarinPalCondition(selectedField, selectedValue);
            });
            <?php
            }
            ?>
            function SetZarinPalCondition(selectedField, selectedValue) {
                jQuery("#gf_zarinpal_conditional_field_id").html(GetSelectableFields(selectedField, 20));
                var optinConditionField = jQuery("#gf_zarinpal_conditional_field_id").val();
                var checked = jQuery("#gf_zarinpal_conditional_enabled").attr('checked');
                if (optinConditionField) {
                    jQuery("#gf_zarinpal_conditional_message").hide();
                    jQuery("#gf_zarinpal_conditional_fields").show();
                    jQuery("#gf_zarinpal_conditional_value_container").html(GetFieldValues(optinConditionField, selectedValue, 20));
                    jQuery("#gf_zarinpal_conditional_value").val(selectedValue);
                }
                else {
                    jQuery("#gf_zarinpal_conditional_message").show();
                    jQuery("#gf_zarinpal_conditional_fields").hide();
                }
                if (!checked) jQuery("#gf_zarinpal_conditional_container").hide();

            }
            function GetFieldValues(fieldId, selectedValue, labelMaxCharacters) {
                if (!fieldId)
                    return "";
                var str = "";
                var field = GetFieldById(fieldId);
                if (!field)
                    return "";
                var isAnySelected = false;
                if (field["type"] == "post_category" && field["displayAllCategories"]) {
                    str += '<?php $dd = wp_dropdown_categories(array("class" => "optin_select", "orderby" => "name", "id" => "gf_zarinpal_conditional_value", "name" => "gf_zarinpal_conditional_value", "hierarchical" => true, "hide_empty" => 0, "echo" => false)); echo str_replace("\n", "", str_replace("'", "\\'", $dd)); ?>';
                }
                else if (field.choices) {
                    str += '<select id="gf_zarinpal_conditional_value" name="gf_zarinpal_conditional_value" class="optin_select">';
                    for (var i = 0; i < field.choices.length; i++) {
                        var fieldValue = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
                        var isSelected = fieldValue == selectedValue;
                        var selected = isSelected ? "selected='selected'" : "";
                        if (isSelected)
                            isAnySelected = true;
                        str += "<option value='" + fieldValue.replace(/'/g, "&#039;") + "' " + selected + ">" + TruncateMiddle(field.choices[i].text, labelMaxCharacters) + "</option>";
                    }
                    if (!isAnySelected && selectedValue) {
                        str += "<option value='" + selectedValue.replace(/'/g, "&#039;") + "' selected='selected'>" + TruncateMiddle(selectedValue, labelMaxCharacters) + "</option>";
                    }
                    str += "</select>";
                }
                else {
                    selectedValue = selectedValue ? selectedValue.replace(/'/g, "&#039;") : "";
                    str += "<input type='text' placeholder='<?php _e("یک مقدار وارد نمایید", "gravityformszarinpal"); ?>' id='gf_zarinpal_conditional_value' name='gf_zarinpal_conditional_value' value='" + selectedValue.replace(/'/g, "&#039;") + "'>";
                }
                return str;
            }
            function GetFieldById(fieldId) {
                for (var i = 0; i < form.fields.length; i++) {
                    if (form.fields[i].id == fieldId)
                        return form.fields[i];
                }
                return null;
            }
            function TruncateMiddle(text, maxCharacters) {
                if (!text)
                    return "";
                if (text.length <= maxCharacters)
                    return text;
                var middle = parseInt(maxCharacters / 2);
                return text.substr(0, middle) + "..." + text.substr(text.length - middle, middle);
            }
            function GetSelectableFields(selectedFieldId, labelMaxCharacters) {
                var str = "";
                var inputType;
                for (var i = 0; i < form.fields.length; i++) {
                    fieldLabel = form.fields[i].adminLabel ? form.fields[i].adminLabel : form.fields[i].label;
                    inputType = form.fields[i].inputType ? form.fields[i].inputType : form.fields[i].type;
                    if (IsConditionalLogicField(form.fields[i])) {
                        var selected = form.fields[i].id == selectedFieldId ? "selected='selected'" : "";
                        str += "<option value='" + form.fields[i].id + "' " + selected + ">" + TruncateMiddle(fieldLabel, labelMaxCharacters) + "</option>";
                    }
                }
                return str;
            }
            function IsConditionalLogicField(field) {
                inputType = field.inputType ? field.inputType : field.type;
                <?php
                $supported_fields = '"checkbox", "radio", "select", "text", "website", "textarea", "email", "hidden", "number", "phone", "multiselect", "post_title",
					"post_tags", "post_custom_field", "post_content", "post_excerpt"';
                $supported_fields = apply_filters(self::$author . '_gateways_supported_fields', $supported_fields);
                $supported_fields = apply_filters(self::$author . '_zarinpal_supported_fields', $supported_fields);
                ?>
                var supported_fields = [<?php echo $supported_fields; ?>];
                var index = jQuery.inArray(inputType, supported_fields);
                return index >= 0;
            }
        </script>
        <?php
    }

    // #4
    //Start Online Transaction
    public static function Request($confirmation, $form, $entry, $ajax)
    {

        do_action('gf_gateway_request_1', $confirmation, $form, $entry, $ajax);
        do_action('gf_zarinpal_request_1', $confirmation, $form, $entry, $ajax);

        if (apply_filters('gf_zarinpal_request_return', apply_filters('gf_gateway_request_return', false, $confirmation, $form, $entry, $ajax), $confirmation, $form, $entry, $ajax))
            return $confirmation;

        $valid_checker = $confirmation == 'valid_checker';
        $custom = $confirmation == 'custom';

        global $current_user;
        $user_id = 0;
        $user_name = __('مهمان', 'gravityformszarinpal');

        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        if (!$valid_checker) {

            $entry_id = $entry['id'];

            if (!$custom) {

                if (RGForms::post("gform_submit") != $form['id']) {
                    return $confirmation;
                }

                $config = self::get_active_config($form);
                if (empty($config)) {
                    return $confirmation;
                }

                unset($entry["payment_status"]);
                unset($entry["payment_amount"]);
                unset($entry["payment_date"]);
                unset($entry["transaction_id"]);
                unset($entry["transaction_type"]);
                unset($entry["is_fulfilled"]);
                GFAPI::update_entry($entry);

                gform_update_meta($entry['id'], 'zarinpal_feed_id', $config['id']);
                gform_update_meta($entry['id'], 'payment_type', 'form');
                gform_update_meta($entry['id'], 'payment_gateway', self::get_gname());
                GFAPI::update_entry_property($entry["id"], "payment_method", "zarinpal");
                GFAPI::update_entry_property($entry["id"], "payment_status", 'Processing');
                GFAPI::update_entry_property($entry["id"], "is_fulfilled", 0);

                switch ($config["meta"]["type"]) {
                    case "subscription" :
                        GFAPI::update_entry_property($entry["id"], "transaction_type", 2);
                        break;

                    default :
                        GFAPI::update_entry_property($entry["id"], "transaction_type", 1);
                        break;
                }

                if (GFCommon::has_post_field($form["fields"]) && !empty($config["meta"]["update_post_action2"]) && $config["meta"]["update_post_action2"] != 'dont') {

                    switch ($config["meta"]["update_post_action2"]) {

                        case "publish" :
                            $form['postStatus'] = 'publish';
                            break;

                        case "draft" :
                            $form['postStatus'] = 'draft';
                            break;

                        case "private" :
                            $form['postStatus'] = 'private';
                            break;

                        default :
                            $form['postStatus'] = 'pending';
                            break;
                    }

                    RGFormsModel::create_post($form, $entry);

                }

                $Amount = self::get_order_total($form, $entry);
                $Amount = apply_filters(self::$author . "_gform_form_gateway_price_{$form['id']}", apply_filters(self::$author . "_gform_form_gateway_price", $Amount, $form, $entry), $form, $entry);
                $Amount = apply_filters(self::$author . "_gform_form_zarinpal_price_{$form['id']}", apply_filters(self::$author . "_gform_form_zarinpal_price", $Amount, $form, $entry), $form, $entry);
                $Amount = apply_filters(self::$author . "_gform_gateway_price_{$form['id']}", apply_filters(self::$author . "_gform_gateway_price", $Amount, $form, $entry), $form, $entry);
                $Amount = apply_filters(self::$author . "_gform_zarinpal_price_{$form['id']}", apply_filters(self::$author . "_gform_zarinpal_price", $Amount, $form, $entry), $form, $entry);

                if (empty($Amount) || !$Amount || $Amount == 0) {
                    return self::redirect_confirmation(add_query_arg(array('no' => 'true'), self::Return_URL($form['id'], $entry['id'])), $ajax);
                } else {

                    $Desc1 = '';
                    if (!empty($config["meta"]["desc_pm"]))
                        $Desc1 = str_replace(array('{entry_id}', '{form_title}', '{form_id}'), array($entry['id'], $form['title'], $form['id']), $config["meta"]["desc_pm"]);
                    $Desc2 = '';
                    if (rgpost('input_' . str_replace(".", "_", $config["meta"]["customer_fields_desc"])))
                        $Desc2 = rgpost('input_' . str_replace(".", "_", $config["meta"]["customer_fields_desc"]));

                    if (!empty($Desc1) && !empty($Desc2))
                        $Description = $Desc1 . ' - ' . $Desc2;
                    else if (!empty($Desc1) && empty($Desc2))
                        $Description = $Desc1;
                    else if (!empty($Desc2) && empty($Desc1))
                        $Description = $Desc2;
                    else
                        $Description = ' ';
					$Description = sanitize_text_field($Description);

                    $Email = '';
                    if (rgpost('input_' . str_replace(".", "_", $config["meta"]["customer_fields_email"])))
                        $Email = sanitize_text_field(rgpost('input_' . str_replace(".", "_", $config["meta"]["customer_fields_email"])));

                    $Mobile = '';
                    if (rgpost('input_' . str_replace(".", "_", $config["meta"]["customer_fields_mobile"])))
                        $Mobile = sanitize_text_field(rgpost('input_' . str_replace(".", "_", $config["meta"]["customer_fields_mobile"])));

                }

                self::send_notification("form_submission", $form, $entry, 'submit', $config);
            } else {

                $Amount = gform_get_meta(rgar($entry, 'id'), 'hannanstd_part_price_' . $form['id']);
                $Amount = apply_filters(self::$author . "_gform_custom_gateway_price_{$form['id']}", apply_filters(self::$author . "_gform_custom_gateway_price", $Amount, $form, $entry), $form, $entry);
                $Amount = apply_filters(self::$author . "_gform_custom_zarinpal_price_{$form['id']}", apply_filters(self::$author . "_gform_custom_zarinpal_price", $Amount, $form, $entry), $form, $entry);
                $Amount = apply_filters(self::$author . "_gform_gateway_price_{$form['id']}", apply_filters(self::$author . "_gform_gateway_price", $Amount, $form, $entry), $form, $entry);
                $Amount = apply_filters(self::$author . "_gform_zarinpal_price_{$form['id']}", apply_filters(self::$author . "_gform_zarinpal_price", $Amount, $form, $entry), $form, $entry);

                $Description = gform_get_meta(rgar($entry, 'id'), 'hannanstd_part_desc_' . $form['id']);
                $Description = apply_filters(self::$author . '_gform_zarinpal_gateway_desc_', apply_filters(self::$author . '_gform_custom_gateway_desc_', $Description, $form, $entry), $form, $entry);

                $Paymenter = gform_get_meta(rgar($entry, 'id'), 'hannanstd_part_name_' . $form['id']);
                $Email = gform_get_meta(rgar($entry, 'id'), 'hannanstd_part_email_' . $form['id']);
                $Mobile = gform_get_meta(rgar($entry, 'id'), 'hannanstd_part_mobile_' . $form['id']);


                unset($entry["payment_status"]);
                unset($entry["payment_amount"]);
                unset($entry["transaction_id"]);
                unset($entry["payment_date"]);
                unset($entry["transaction_type"]);
                unset($entry["is_fulfilled"]);
                $entry_id = GFAPI::add_entry($entry);
                $entry = RGFormsModel::get_lead($entry_id);

                do_action('gf_gateway_request_add_entry', $confirmation, $form, $entry, $ajax);
                do_action('gf_zarinpal_request_add_entry', $confirmation, $form, $entry, $ajax);

                //-----------------------------------------------------------------
                gform_update_meta($entry_id, 'payment_gateway', self::get_gname());
                gform_update_meta($entry_id, 'payment_type', 'custom');
                GFAPI::update_entry_property($entry_id, "payment_method", "zarinpal");
                GFAPI::update_entry_property($entry_id, "payment_status", 'Processing');
                GFAPI::update_entry_property($entry_id, "is_fulfilled", 0);
                GFAPI::update_entry_property($entry_id, "transaction_type", 1);
            }

            $ReturnPath = self::Return_URL($form['id'], $entry_id);
            $ResNumber = apply_filters('gf_zarinpal_res_number', apply_filters('gf_gateway_res_number', $entry_id, $entry, $form), $entry, $form);
        } else {

            $Amount = 2000;
            $ReturnPath = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
            $Email = '';
            $Mobile = '';
            $ResNumber = rand(1000, 9999);
            $Description = __('جهت بررسی تنظیمات صحیح بودن تنظیمات درگاه گرویتی فرم زرین پال', 'gravityformszarinpal');

        }
        $Mobile = self::fix_mobile($Mobile);

        do_action('gf_gateway_request_2', $confirmation, $form, $entry, $ajax);
        do_action('gf_zarinpal_request_2', $confirmation, $form, $entry, $ajax);

        $currency = GFCommon::get_currency();
        if ($currency != 'IRT' && !$custom) {
            $Amount = $Amount / 10;
        }

        //$Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
        //$Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';

        try {

            $server = self::get_server();
            if (strtolower($server) == "german") {
                $client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8'));
            } else {
                $client = new SoapClient('https://ir.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8'));
            }

            $MerchantID = self::get_merchent();

            $Result = $client->PaymentRequest(
                array(
                    'MerchantID' => $MerchantID,
                    'Amount' => $Amount,
                    'Description' => $Description,
                    'Email' => $Email,
                    'Mobile' => $Mobile,
                    'CallbackURL' => $ReturnPath
                )
            );

            if ($Result->Status == 100) {

                $Payment_URL = 'https://www.zarinpal.com/pg/StartPay/' . $Result->Authority;

                if ($valid_checker) {
                    return true;
                } else {
                    return self::redirect_confirmation($Payment_URL, $ajax);
                }

            } else {
                $Message = self::Fault($Result->Status);
            }
        } catch (Exception $ex) {
            $Message = $ex->getMessage();
        }

        if (!empty($Message) && $Message) {

            $confirmation = $Fault_Response = $Message;

            if ($valid_checker)
                return $Fault_Response;

            GFAPI::update_entry_property($entry_id, 'payment_status', 'Failed');
            RGFormsModel::add_note($entry_id, $user_id, $user_name, sprintf(__('خطا در اتصال به درگاه رخ داده است : %s', "gravityformszarinpal"), $Fault_Response));

            if (!$custom)
                self::send_notification("form_submission", $form, $entry, 'failed', $config);
        }

        $default_anchor = 0;
        $anchor = gf_apply_filters('gform_confirmation_anchor', $form['id'], $default_anchor) ? "<a id='gf_{$form['id']}' name='gf_{$form['id']}' class='gform_anchor' ></a>" : '';
        $nl2br = !empty($form['confirmation']) && rgar($form['confirmation'], 'disableAutoformat') ? false : true;
        $cssClass = rgar($form, 'cssClass');
        return $confirmation = empty($confirmation) ? "{$anchor} " : "{$anchor}<div id='gform_confirmation_wrapper_{$form['id']}' class='gform_confirmation_wrapper {$cssClass}'><div id='gform_confirmation_message_{$form['id']}' class='gform_confirmation_message_{$form['id']} gform_confirmation_message'>" . GFCommon::replace_variables($confirmation, $form, $entry, false, true, $nl2br) . '</div></div>';
    }


    // #5
    public static function Verify()
    {

        if (apply_filters('gf_gateway_zarinpal_return', apply_filters('gf_gateway_verify_return', false)))
            return;

        if (!self::is_gravityforms_supported())
            return;

        if (empty($_GET['id']) || empty($_GET['lead']) || !is_numeric(rgget('id')) || !is_numeric(rgget('lead')))
            return;

        $form_id = $_GET['id'];
        $lead_id = $_GET['lead'];

        $lead = RGFormsModel::get_lead($lead_id);

        if (isset($lead["payment_method"]) && $lead["payment_method"] == 'zarinpal') {

            $form = RGFormsModel::get_form_meta($form_id);

            $payment_type = gform_get_meta($lead["id"], 'payment_type');
            gform_delete_meta($lead['id'], 'payment_type');

            if ($payment_type != 'custom') {
                $config = self::get_config_by_entry($lead);
                if (empty($config)) {
                    return;
                }
            } else {
                $config = apply_filters(self::$author . '_gf_zarinpal_config', apply_filters(self::$author . '_gf_gateway_config', array(), $form, $lead), $form, $lead);
            }


            if (!empty($lead["payment_date"])) {
                /*
                if( ! class_exists("GFFormDisplay") )
                    require_once(GFCommon::get_base_path() . "/form_display.php");

                $default_anchor = 0;
                $anchor         = gf_apply_filters( 'gform_confirmation_anchor', $form['id'], $default_anchor ) ? "<a id='gf_{$form['id']}' name='gf_{$form['id']}' class='gform_anchor' ></a>" : '';
                $nl2br          = !empty( $form['confirmation'] ) && rgar( rgar( $form, 'confirmation' ), 'disableAutoformat' ) ? false : true;
                $cssClass       = rgar( $form, 'cssClass' );
                $confirmation = __('نتیجه تراکنش قبلا مشخص شده است.' , 'gravityformszarinpal');
                $confirmation   = empty( $confirmation ) ? "{$anchor} " : "{$anchor}<div id='gform_confirmation_wrapper_{$form['id']}' class='gform_confirmation_wrapper {$cssClass}'><div id='gform_confirmation_message_{$form['id']}' class='gform_confirmation_message_{$form['id']} gform_confirmation_message'>" . GFCommon::replace_variables( $confirmation, $form, $lead, false, true, $nl2br ) . '</div></div>';
                GFFormDisplay::$submission[$form_id] = array("is_confirmation" => true, "confirmation_message" => $confirmation, "form" => $form, "lead" => $lead);
				*/
                return;
            }

            global $current_user;
            $user_id = 0;
            $user_name = __("مهمان", "gravityformszarinpal");
            if ($current_user && $user_data = get_userdata($current_user->ID)) {
                $user_id = $current_user->ID;
                $user_name = $user_data->display_name;
            }

            $transaction_type = 1;
            if (!empty($config["meta"]["type"]) && $config["meta"]["type"] == 'subscription') {
                $transaction_type = 2;
            }

            if ($payment_type == 'custom') {
                $Amount = $Total = gform_get_meta($lead["id"], 'hannanstd_part_price_' . $form_id);
            } else {
                $Amount = $Total = self::get_order_total($form, $lead);
            }
            $Total_Money = GFCommon::to_money($Total, $lead["currency"]);
            $currency = GFCommon::get_currency();

            $free = false;
            if (empty($_GET['no']) || $_GET['no'] != 'true') {

                //Start of ZarinPal
                if ($currency != 'IRT' && $payment_type != 'custom')
                    $Amount = $Amount / 10;


                if (isset($_GET['Status']) && strtolower($_GET['Status']) == 'ok') {

                    $Authority = isset($_GET['Authority']) ? sanitize_text_field($_GET['Authority']) : '';
                    $MerchantID = self::get_merchent();

                    try {

                        $server = self::get_server();
                        if (strtolower($server) == "german") {
                            $client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8'));
                        } else {
                            $client = new SoapClient('https://ir.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8'));
                        }

                        $Result = $client->PaymentVerification(
                            array(
                                'MerchantID' => $MerchantID,
                                'Authority' => $Authority,
                                'Amount' => $Amount
                            )
                        );

                        if ($Result->Status == 100) {
                            $Message = '';
                            $Status = 'completed';
                        } else {
                            $Message = self::Fault($Result->Status);
                            $Status = 'failed';
                        }


                    } catch (Exception $ex) {
                        $Message = $ex->getMessage();
                        $Status = 'failed';
                    }
                } else {
                    $Message = '';
                    $Status = 'cancelled';
                }
                $Transaction_ID = !empty($Result->RefID) ? $Result->RefID : '-';
                //End of ZarinPal
            } else {
                $Status = 'completed';
                $Message = '';
                $Transaction_ID = apply_filters(self::$author . '_gf_rand_transaction_id', rand(1000000000, 9999999999), $form, $lead);
                $free = true;
            }

            $Status = !empty($Status) ? $Status : 'failed';
            $transaction_id = !empty($Transaction_ID) ? $Transaction_ID : '';
            $transaction_id = apply_filters(self::$author . '_gf_real_transaction_id', $transaction_id, $Status, $form, $lead);

            //----------------------------------------------------------------------------------------
            $lead["payment_date"] = gmdate("Y-m-d H:i:s");
            $lead["transaction_id"] = $transaction_id;
            $lead["transaction_type"] = $transaction_type;

            if ($Status == 'completed') {

                $lead["is_fulfilled"] = 1;
                $lead["payment_amount"] = $Total;

                if ($transaction_type == 2) {
                    $lead["payment_status"] = "Active";
                    if (apply_filters(self::$author . '_gf_zarinpal_create_user', apply_filters(self::$author . '_gf_zarinpal_create_user', ($payment_type != 'custom'), $form, $lead), $form, $lead)) {
                        self::Creat_User($form, $lead);
                    }
                    RGFormsModel::add_note($lead["id"], $user_id, $user_name, __("تغییرات اطلاعات فیلدها فقط در همین پیام ورودی اعمال خواهد شد و بر روی وضعیت کاربر تاثیری نخواهد داشت .", "gravityformszarinpal"));
                } else {
                    $lead["payment_status"] = "Paid";
                }

                if ($free == true) {
                    unset($lead["payment_status"]);
                    unset($lead["payment_amount"]);
                    unset($lead["payment_method"]);
                    unset($lead["is_fulfilled"]);
                    gform_delete_meta($lead['id'], 'payment_gateway');
                    $Note = sprintf(__('وضعیت پرداخت : رایگان - بدون نیاز به درگاه پرداخت', "gravityformszarinpal"));
                } else {
                    $Note = sprintf(__('وضعیت پرداخت : موفق - مبلغ پرداختی : %s - کد تراکنش : %s', "gravityformszarinpal"), $Total_Money, $transaction_id);
                }

                GFAPI::update_entry($lead);


                if (apply_filters(self::$author . '_gf_zarinpal_post', apply_filters(self::$author . '_gf_gateway_post', ($payment_type != 'custom'), $form, $lead), $form, $lead)) {

                    $has_post = GFCommon::has_post_field($form["fields"]) ? true : false;

                    if (empty($lead["post_id"]) && $has_post) {
                        RGFormsModel::create_post($form, $lead);
                        $lead = RGFormsModel::get_lead($lead_id);
                    }

                    if (!empty($lead["post_id"]) && $has_post) {

                        $post = get_post($lead["post_id"]);
                        $old_status = $post->post_status;

                        if (!empty($config["meta"]["update_post_action1"])) {

                            switch ($config["meta"]["update_post_action1"]) {

                                case "publish" :
                                    $new_status = 'publish';
                                    break;

                                case "draft" :
                                    $new_status = 'draft';
                                    break;

                                case "pending" :
                                    $new_status = 'pending';
                                    break;

                                case "private" :
                                    $new_status = 'private';
                                    break;

                                default:
                                    $new_status = rgar($form, 'postStatus');
                                    break;
                            }
                        } else {
                            $new_status = rgar($form, 'postStatus');
                        }

                        if ($new_status != $old_status) {
                            global $wpdb;
                            $wpdb->update($wpdb->posts, array('post_status' => $new_status), array('ID' => $post->ID));
                            clean_post_cache($post->ID);
                            wp_transition_post_status($new_status, $old_status, $post);
                        }
                    }
                }

                if (class_exists('GFHANNANSMS') && method_exists('GFHANNANSMS', 'sendsms_By_HANNANStd'))
                    GFHANNANSMS::sendsms_By_HANNANStd($lead, $form);

                do_action("gform_zarinpal_fulfillment", $lead, $config, $transaction_id, $Total);
                do_action("gform_gateway_fulfillment", $lead, $config, $transaction_id, $Total);
                do_action("gform_paypal_fulfillment", $lead, $config, $transaction_id, $Total);
            } else if ($Status == 'cancelled') {
                $lead["payment_status"] = "Cancelled";
                $lead["payment_amount"] = 0;
                $lead["is_fulfilled"] = 0;
                GFAPI::update_entry($lead);

                $Note = sprintf(__('وضعیت پرداخت : منصرف شده - مبلغ قابل پرداخت : %s - کد تراکنش : %s', "gravityformszarinpal"), $Total_Money, $transaction_id);
            } else {
                $lead["payment_status"] = "Failed";
                $lead["payment_amount"] = 0;
                $lead["is_fulfilled"] = 0;
                GFAPI::update_entry($lead);

                $Note = sprintf(__('وضعیت پرداخت : ناموفق - مبلغ قابل پرداخت : %s - کد تراکنش : %s - علت خطا : %s', "gravityformszarinpal"), $Total_Money, $transaction_id, $Message);
            }

            $lead = RGFormsModel::get_lead($lead_id);
            RGFormsModel::add_note($lead["id"], $user_id, $user_name, $Note);
            do_action('gform_post_payment_status', $config, $lead, strtolower($Status), $transaction_id, '', $Total, '', '');
            do_action('gform_post_payment_status_' . __CLASS__, $config, $form, $lead, strtolower($Status), $transaction_id, '', $Total, '', '');


            if (apply_filters(self::$author . '_gf_zarinpal_verify', apply_filters(self::$author . '_gf_gateway_verify', ($payment_type != 'custom'), $form, $lead), $form, $lead)) {
                self::send_notification("form_submission", $form, $lead, strtolower($Status), $config);
                self::confirmation($form, $lead, '', strtolower($Status), $Message, $config);
            }
        }
    }


    // #6
    private static function Fault($err_code)
    {
        $message = ' ';
        switch ($err_code) {

            case '-1' :
                $message = __('اطلاعات ارسال شده ناقص است .', 'gravityformszarinpal');
                break;

            case '-2' :
                $message = __('آی پی یا مرچنت زرین پال اشتباه است .', 'gravityformszarinpal');
                break;

            case '-3' :
                $message = __('با توجه به محدودیت های شاپرک امکان پرداخت با رقم درخواست شده میسر نمیباشد .', 'gravityformszarinpal');
                break;

            case '-4' :
                $message = __('سطح تایید پذیرنده پایین تر از سطح نقره ای میباشد .', 'gravityformszarinpal');
                break;

            case '-11' :
                $message = __('درخواست مورد نظر یافت نشد .', 'gravityformszarinpal');
                break;

            case '-21' :
                $message = __('هیچ نوع عملیات مالی برای این تراکنش یافت نشد .', 'gravityformszarinpal');
                break;

            case '-22' :
                $message = __('تراکنش نا موفق میباشد .', 'gravityformszarinpal');
                break;

            case '-33' :
                $message = __('رقم تراکنش با رقم وارد شده مطابقت ندارد .', 'gravityformszarinpal');
                break;

            case '-40' :
                $message = __('اجازه دسترسی به متد مورد نظر وجود ندارد .', 'gravityformszarinpal');
                break;

            case '-54' :
                $message = __('درخواست مورد نظر آرشیو شده است .', 'gravityformszarinpal');
                break;

            case '100' :
                $message = __('اتصال با زرین پال به خوبی برقرار شد و همه چیز صحیح است .', 'gravityformszarinpal');
                break;

            case '101' :
                $message = __('تراکنش با موفقیت به پایان رسیده بود و تاییدیه آن نیز انجام شده بود .', 'gravityformszarinpal');
                break;

        }
        return $message;
    }

}