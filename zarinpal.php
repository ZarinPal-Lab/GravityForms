<?php
/*
Plugin Name: درگاه زرین پال Gravity Forms
Plugin URI: http://gravityforms.ir/
Description: افزونه درگاه پرداخت زرین پال برای فرم ساز فوق پیشرفته Gravity Forms 
Version: 1.10.0
Author: حنان ابراهیمی ستوده
Author URI: http://webforest.ir/
*/
add_action('wp',  array('GFZarinPal', 'zarinpal_verify'), 5);
add_action('init',  array('GFZarinPal', 'init'));
register_activation_hook( __FILE__, array("GFZarinPal", "add_permissions"));
class GFZarinPal {
    private static $path = "gravityformszarinpal/zarinpal.php";
    private static $slug = "gravityformszarinpal";
    private static $version = "1.10";
    private static $min_gravityforms_version = "1.8.9";
    private static $supported_fields = array("checkbox", "radio", "select", "text", "website", "textarea", "email", "hidden", "number", "phone", "multiselect", "post_title","post_tags", "post_custom_field", "post_content", "post_excerpt");
    public static function init(){
		add_filter("gform_logging_supported", array("GFZarinPal", "set_logging_supported"));
        if(!self::is_gravityforms_supported() || !class_exists("GravityFormsPersian"))
        return;
        if(is_admin()){
            if(function_exists('members_get_capabilities'))
            add_filter('members_get_capabilities', array("GFZarinPal", "members_get_capabilities"));
            add_filter("gform_addon_navigation", array('GFZarinPal', 'create_menu_by_HANNANStd'));
            add_action('gform_entry_info', array('GFZarinPal','admin_edit_payment_status_details_by_HANNANStd'), 4, 2);
            add_action('gform_after_update_entry', array('GFZarinPal','admin_update_payment_by_HANNANStd'), 4, 2);
            if(self::is_zarinpal_page()){
				wp_enqueue_script(array("sack"));
				self::setup();
            }
            else if(in_array(RG_CURRENT_PAGE, array("admin-ajax.php"))){
                add_action('wp_ajax_gf_zarinpal_update_feed_active', array('GFZarinPal', 'update_feed_active_By_HANNANStd'));
                add_action('wp_ajax_gf_select_zarinpal_form', array('GFZarinPal', 'select_zarinpal_form_By_HANNANStd'));
                add_action('wp_ajax_gf_zarinpal_confirm_settings', array('GFZarinPal', 'confirm_settings_By_HANNANStd'));
            }
            else if(RGForms::get("page") == "gf_settings"){
                RGForms::add_settings_page("حساب زرین پال", array("GFZarinPal", "settings_page_By_HANNANStd"), self::get_base_url() . "/static/zarinpal.png");
            }
        }
        else{
            add_filter("gform_confirmation", array("GFZarinPal", "send_to_zarinpal_By_HANNANStd"), 1000, 4);
			add_action("gform_enqueue_scripts", array("GFZarinPal", "shaparak_ing_By_HANNANStd"), 10, 2);
            add_filter("gform_disable_post_creation", array("GFZarinPal", "delay_post_By_HANNANStd"), 10, 3);
            add_filter("gform_disable_notification", array("GFZarinPal", "delay_notification_By_HANNANStd"), 10, 4);
			add_filter("gform_disable_registration", array("GFZarinPal", "disable_registration_By_HANNANStd"), 10, 4); 
        }
    }
    public static function update_feed_active_By_HANNANStd(){
        check_ajax_referer('gf_zarinpal_update_feed_active','gf_zarinpal_update_feed_active');
        $id = $_POST["feed_id"];
        $feed = GFZarinPalData::get_feed($id);
        GFZarinPalData::update_feed($id, $feed["form_id"], $_POST["is_active"], $feed["meta"]);
    }
    public static function create_menu_by_HANNANStd($menus){
        $permission = self::has_access("gravityforms_zarinpal");
        if(!empty($permission))
            $menus[] = array("name" => "gf_zarinpal", "label" => __("فرم های زرین پال", "gravityformszarinpal"), "callback" =>  array("GFZarinPal", "zarinpal_page"), "permission" => $permission);
        return $menus;
    }
	private static function setup(){
        if(get_option("gf_zarinpal_version") != self::$version)
            GFZarinPalData::update_table();
        update_option("gf_zarinpal_version", self::$version);
    }

    public static function delay_post_By_HANNANStd($is_disabled, $form, $lead){
    $config = GFZarinPalData::get_feed_by_form($form["id"]);
    if(!$config)
        return $is_disabled;
    $config = $config[0];
    if(!self::has_zarinpal_condition($form, $config))
        return $is_disabled;
    return true;
	}
	public static function delay_notification_By_HANNANStd($is_disabled, $notification, $form, $lead){
        $config = self::get_active_config($form);
        if(!$config){
            return $is_disabled;
        }
		$config = $config[0];
		if(!self::has_zarinpal_condition($form, $config))
        return $is_disabled;
        return true;
    }
	public function disable_registration_By_HANNANStd($is_disabled, $form, $entry, $fulfilled){
	 $config = self::get_active_config($form);
        if(!$config){
            return $is_disabled;
        }
		$config = $config[0];
		if(!self::has_zarinpal_condition($form, $config))
        return $is_disabled;
		$config = self::get_config_by_entry($entry);
		if($config["meta"]["type"] != "subscription" )
		return $is_disabled;	
        return true;	
}
	public function delay_addons_By_HANNANStd($form){
	$config = self::get_active_config($form);
	if(!$config){
	return;
	}
	$config = $config[0];
	if(!self::has_zarinpal_condition($form, $config))
	return $is_disabled;
	if(class_exists('GFTwilio'))remove_action("gform_post_submission", array('GFTwilio', 'handle_form_submission'), 10, 2);
	if(class_exists('GFHANNANSMS'))remove_action("gform_post_submission", array('GFHANNANSMS', 'sendsms_By_HANNANStd'), 10, 2);
	}
	public static function has_payment($form, $entry, $zarinpal_config){
        $products = GFCommon::get_product_fields($form, $entry, true);
        $recurring_field = rgar($zarinpal_config["meta"], "recurring_amount_field");
        $total = 0;
        foreach($products["products"] as $id => $product){
            if($zarinpal_config["meta"]["type"] != "subscription" || $recurring_field == $id || $recurring_field == "all"){
                $price = GFCommon::to_number($product["price"]);
                if(is_array(rgar($product,"options"))){
                    foreach($product["options"] as $option){
                        $price += GFCommon::to_number($option["price"]);
                    }
                }
                $total += $price * $product['quantity'];
            }
        }
        if($recurring_field == "all" && !empty($products["shipping"]["price"]))
            $total += floatval($products["shipping"]["price"]);
        return $total > 0;
    }	
	private static function send_notification ( $event, $form, $lead ) {
	$notifications = GFCommon::get_notifications_to_send( $event, $form, $lead );
	$notifications_to_send = array();
	foreach ( $notifications as $notification ) {
	$notifications_to_send[] = $notification['id'];}
	GFCommon::send_notifications( $notifications_to_send, $form, $lead, true, $event );
	}
    public static function zarinpal_page(){
        $view = rgget("view");
        if($view == "edit")
            self::edit_page(rgget("id"));
        else if($view == "stats")
            self::stats_page(rgget("id"));
        else
            self::list_page();
    }
    private static function list_page(){
        if(!self::is_gravityforms_supported()){
            die(__(sprintf("درگاه زرین پال نیاز به گراویتی فرم نسخه %s دارد . برای به روز رسانی به %sصفحه افزونه%s مراجعه نمایید .", self::$min_gravityforms_version, "<a href='plugins.php'>", "</a>"), "gravityformszarinpal"));
        }
        if(rgpost('action') == "delete"){
            check_admin_referer("list_action", "gf_zarinpal_list");
            $id = absint($_POST["action_argument"]);
            GFZarinPalData::delete_feed($id);
            ?>
            <div class="updated fade" style="padding:6px"><?php _e("فید حذف شد", "gravityformszarinpal") ?></div>
            <?php
        }
        else if (!empty($_POST["bulk_action"])){
            check_admin_referer("list_action", "gf_zarinpal_list");
            $selected_feeds = $_POST["feed"];
            if(is_array($selected_feeds)){
                foreach($selected_feeds as $feed_id)
                    GFZarinPalData::delete_feed($feed_id);
            }
            ?>
            <div class="updated fade" style="padding:6px"><?php _e("فید ها حذف شدند", "gravityformszarinpal") ?></div>
            <?php
        }
        ?>
        <div class="wrap">
            <img alt="<?php _e("تراکنشهای زرین پال", "gravityformszarinpal") ?>" src="<?php echo self::get_base_url()?>/static/zarinpal.png" style="float:left; margin:15px 7px 0 0;"/>
            <h2><?php
            _e("فرم های زرین پال", "gravityformszarinpal");
            if(get_option("gf_zarinpal_configured")){
                ?>
                <a class="add-new-h2"  href="admin.php?page=gf_zarinpal&view=edit&id=0"><?php _e("افزودن جدید", "gravityformszarinpal") ?></a>
                <?php
            }
            ?>
            </h2>
            <form id="feed_form" method="post">
                <?php wp_nonce_field('list_action', 'gf_zarinpal_list') ?>
                <input type="hidden" id="action" name="action"/>
                <input type="hidden" id="action_argument" name="action_argument"/>
                <div class="tablenav">
                    <div class="alignleft actions" style="padding:8px 0 7px 0;">
                        <label class="hidden" for="bulk_action"><?php _e("اقدام دسته جمعی", "gravityformszarinpal") ?></label>
                        <select name="bulk_action" id="bulk_action">
                            <option value=''> <?php _e("اقدامات دسته جمعی", "gravityformszarinpal") ?> </option>
                            <option value='delete'><?php _e("حذف", "gravityformszarinpal") ?></option>
                        </select>
                        <?php
                        echo '<input type="submit" class="button" value="' . __("اعمال", "gravityformszarinpal") . '" onclick="if( jQuery(\'#bulk_action\').val() == \'delete\' && !confirm(\'' . __("فید حذف شود ؟ ", "gravityformszarinpal") . __("\'Cancel\' برای منصرف شدن, \'OK\' برای حذف کردن", "gravityformszarinpal") .'\')) { return false; } return true;"/>';
                        ?>
						
					<a class="button add-new-h2" style="margin:3px 9px; font-family: byekan !important;font-weight: normal !important;"" href="admin.php?page=gf_settings&addon=حساب زرین پال" target="_blank">تنظیمات حساب زرین پال</a>
                    </div>
                </div>
                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
                            <th scope="col" id="active" class="manage-column check-column"></th>
							<th scope="col" class="manage-column" style="width:50px;cursor:pointer;"><?php _e("آیدی", "gravityformszarinpal") ?></th>
                            <th scope="col" class="manage-column"><?php _e("فرم متصل به درگاه", "gravityformszarinpal") ?></th>
                            <th scope="col" class="manage-column"><?php _e("نوع تراکنش", "gravityformszarinpal") ?></th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
                            <th scope="col" id="active" class="manage-column check-column"></th>
							<th scope="col" class="manage-column" style="width:50px;cursor:pointer;"><?php _e("آیدی", "gravityformszarinpal") ?></th>
                            <th scope="col" class="manage-column"><?php _e("فرم متصل به درگاه", "gravityformszarinpal") ?></th>
                            <th scope="col" class="manage-column"><?php _e("نوع تراکنش", "gravityformszarinpal") ?></th>
                        </tr>
                    </tfoot>
                    <tbody class="list:user user-list">
                        <?php
						$currency = GFCommon::get_currency();
                        $settings = GFZarinPalData::get_feeds();
                        if(!get_option("gf_zarinpal_configured")){
                            ?>
                                <td colspan="4" style="padding:20px;">
                                    <?php echo sprintf(__("برای شروع باید درگاه را فعال نمایید . به %sتنظیمات زرین پال%s بروید . ", "gravityformszarinpal"), '<a href="admin.php?page=gf_settings&addon=حساب زرین پال">', "</a>"); ?>
                                </td>
                            </tr>
                            <?php
                        }
						else if ($currency != 'IRR' && $currency != 'IRT') { ?>
						<tr>
                        <td colspan="4" style="padding:20px;">
                        <?php echo sprintf(__("برای استفاده از این درگاه باید واحد پول را بر روی « تومان » یا « ریال ایران » تنظیم کنید . %sبرای تنظیم واحد پول کلیک نمایید%s . ", "gravityformszarinpal"), '<a href="admin.php?page=gf_settings">', "</a>"); ?>
                        </td>
                        </tr>
						<?php }
                        else if(is_array($settings) && sizeof($settings) > 0){
                            foreach($settings as $setting){
                                ?>
                                <tr class='author-self status-inherit' valign="top">
                                    <th scope="row" class="check-column"><input type="checkbox" name="feed[]" value="<?php echo $setting["id"] ?>"/></th>
                                    <td><img style="cursor:pointer;" src="<?php echo self::get_base_url() ?>/static/active<?php echo intval($setting["is_active"]) ?>.png" alt="<?php echo $setting["is_active"] ? __("درگاه فعال است", "gravityformszarinpal") : __("درگاه غیر فعال است", "gravityformszarinpal");?>" title="<?php echo $setting["is_active"] ? __("درگاه فعال است", "gravityformszarinpal") : __("درگاه غیر فعال است", "gravityformszarinpal");?>" onclick="ToggleActive(this, <?php echo $setting['id'] ?>); " /></td>
                                    
									<td><?php echo $setting["form_id"] ?> </td>
                                    
									<td class="column-title">
                                        <strong><a class="row-title"  href="admin.php?page=gf_zarinpal&view=edit&id=<?php echo $setting["id"] ?>" title="<?php _e("تنظیم مجدد درگاه", "gravityformszarinpal") ?>"><?php echo $setting["form_title"] ?></a></strong>
                                        <div class="row-actions">
										<span class="view">
                                            <span class="edit">
                                            <a title="<?php _e("ویرایش تنظیمات درگاه", "gravityformszarinpal")?>" href="admin.php?page=gf_zarinpal&view=edit&id=<?php echo $setting["id"] ?>" ><?php _e("ویرایش تنظیمات درگاه", "gravityformszarinpal") ?></a>
                                            |
                                            </span>
                                            <a title="<?php _e("ویرایش فرم", "gravityformszarinpal")?>" href="admin.php?page=gf_edit_forms&id=<?php echo $setting["form_id"] ?>" ><?php _e("ویرایش فرم", "gravityformszarinpal") ?></a>
                                            |
                                            </span>
                                            <span class="view">
                                            <a title="<?php _e("مشاهده گزارشات", "gravityformszarinpal")?>" href="admin.php?page=gf_zarinpal&view=stats&id=<?php echo $setting["id"] ?>"><?php _e("گزارشات", "gravityformszarinpal") ?></a>
                                            |
                                            </span>
                                            <span class="view">
                                            <a title="<?php _e("مشاهده صندوق ورودی", "gravityformszarinpal")?>" href="admin.php?page=gf_entries&view=entries&id=<?php echo $setting["form_id"] ?>"><?php _e("صندوق ورودی", "gravityformszarinpal") ?></a>
                                            |
                                            </span>
                                            <span class="trash">
                                            <a title="<?php _e("حذف", "gravityformszarinpal") ?>" href="javascript: if(confirm('<?php _e("فید حذف شود؟ ", "gravityformszarinpal") ?> <?php _e("\'Cancel\' برای انصراف, \'OK\' برای حذف کردن.", "gravityformszarinpal") ?>')){ DeleteSetting(<?php echo $setting["id"] ?>);}"><?php _e("حذف", "gravityformszarinpal")?></a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="column-date">
                                        <?php
                                            switch($setting["meta"]["type"]){
                                                case "product" :
                                                    _e("محصول معمولی یا فرم ارسال پست", "gravityformszarinpal");
                                                break;
												case "subscription" :
                                                    _e("عضویت", "gravityformszarinpal");
                                                break;
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        else{
                            ?>
                            <tr>
                                <td colspan="4" style="padding:20px;">
                                    <?php echo sprintf(__("شما هیچ فید زرین پالی ندارید . %sیکی بسازید%s .", "gravityformszarinpal"), '<a href="admin.php?page=gf_zarinpal&view=edit&id=0">', "</a>"); ?>
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
            function DeleteSetting(id){
                jQuery("#action_argument").val(id);
                jQuery("#action").val("delete");
                jQuery("#feed_form")[0].submit();
            }
            function ToggleActive(img, feed_id){
                var is_active = img.src.indexOf("active1.png") >=0
                if(is_active){
                    img.src = img.src.replace("active1.png", "active0.png");
                    jQuery(img).attr('title','<?php _e("درگاه غیر فعال است", "gravityformszarinpal") ?>').attr('alt', '<?php _e("درگاه غیر فعال است", "gravityformszarinpal") ?>');
                }
                else{
                    img.src = img.src.replace("active0.png", "active1.png");
                    jQuery(img).attr('title','<?php _e("درگاه فعال است", "gravityformszarinpal") ?>').attr('alt', '<?php _e("درگاه فعال است", "gravityformszarinpal") ?>');
                }
                var mysack = new sack(ajaxurl);
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar( "action", "gf_zarinpal_update_feed_active" );
                mysack.setVar( "gf_zarinpal_update_feed_active", "<?php echo wp_create_nonce("gf_zarinpal_update_feed_active") ?>" );
                mysack.setVar( "feed_id", feed_id );
                mysack.setVar( "is_active", is_active ? 0 : 1 );
                mysack.onError = function() { alert('<?php _e("Ajax error while updating feed", "gravityformszarinpal" ) ?>' )};
                mysack.runAJAX();
                return true;
            }
        </script>
        <?php
    }
    public static function confirm_settings_By_HANNANStd(){
        update_option("gf_zarinpal_configured", $_POST["is_confirmed"]);
    }
	 public static function settings_page_By_HANNANStd(){

        if(rgpost("uninstall")){
            check_admin_referer("uninstall", "gf_zarinpal_uninstall");
            self::uninstall();

            ?>
            <div class="updated fade" style="padding:20px;"><?php _e(sprintf("درگاه با موفقیت غیرفعال شد و اطلاعات مربوط به آن نیز از بین رفت برای فعالسازی مجدد میتوانید از طریق افزونه های وردپرس اقدام نمایید ."), "gravityformszarinpal")?></div>
            <?php
            return;
        }
        else if(isset($_POST["gf_zarinpal_submit"])){
            check_admin_referer("update", "gf_zarinpal_update");
            $settings = array("merchent" => $_POST["gf_zarinpal_merchent"],
                                "server" => $_POST["gf_zarinpal_server"],
								"gname" => $_POST["gf_zarinpal_gname"]
							);
            update_option("gf_zarinpal_settings", $settings);
        }
        else{
            $settings = get_option("gf_zarinpal_settings");
        }        
		if(empty($settings["merchent"]))
		$message = " ";	
		else {
        $is_valid = self::is_valid_key();
        $message = self:: khata_nama($is_valid);
		}
		$is_configured = get_option("gf_zarinpal_configured");		
        ?>
        <style>
            .valid_credentials{color:green;}
            .invalid_credentials{color:red;}
            .size-1{width:400px;}
        </style>
        <form action="" method="post">
            <?php wp_nonce_field("update", "gf_zarinpal_update") ?>
            <table class="form-table">
                <tr>
                    <td colspan="2">
                        <h3 style="font-size:19px !important;"><?php _e("تنظیمات کلی حساب زرین پال", "gravityformszarinpal") ?></h3>
                        <label>
                            <?php _e(sprintf("مرچنت خود را وارد نمایید . در صورتی که هاستینگ شما خارجی است پیشنهاد میکنیم که سرور آلمان را استفاده نمایید ."), "gravityformszarinpal") ?>
                        </label>
			<?php if(!empty($settings["merchent"])) { ?>
						<div>
						<br>
						<img src="<?php echo self::get_base_url() ?>/static/<?php echo ( $is_valid == 100 ) ? "tick.png" : "stop.png" ?>" border="0" alt="<?php $message ?>" title="<?php echo $message ?>" style="display:<?php echo empty($message) ? 'none;' : 'inline;' ?>" />
                        <span style="font-size:12px"><?php echo $message ?></span>
						</div>
						<?php } ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row" nowrap="nowrap"><label for="gf_zarinpal_server"><?php _e("سرور زرین پال", "gravityformszarinpal"); ?></label> </th>
                    <td width="88%">
                        <input type="radio" name="gf_zarinpal_server" id="gf_zarinpal_server_production" value="Iran" <?php echo rgar($settings, 'server') != "German" ? "checked='checked'" : "" ?>/>
                        <label class="inline" for="gf_zarinpal_server_production"><?php _e("ایران", "gravityformszarinpal"); ?></label>
                        &nbsp;&nbsp;&nbsp;
                        <input type="radio" name="gf_zarinpal_server" id="gf_zarinpal_server_test" value="German" <?php echo rgar($settings, 'server') == "German" ? "checked='checked'" : "" ?>/>
                        <label class="inline" for="gf_zarinpal_server_test"><?php _e("آلمان", "gravityformszarinpal"); ?></label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="gf_zarinpal_merchent"><?php _e("مرچنت", "gravityformszarinpal"); ?></label> </th>
                    <td width="400px">
                        <input class="size-1" id="gf_zarinpal_merchent" name="gf_zarinpal_merchent" value="<?php echo esc_attr($settings["merchent"]) ?>" />
                       
                    </td>
                </tr>
				<?php
				if(esc_attr($settings["gname"]))
                $gname = esc_attr($settings["gname"]);
		        else
		        $gname = "زرین پال";
				?>
				  <tr>
                    <th scope="row"><label for="gf_zarinpal_gname"><?php _e("نام نمایشی درگاه", "gravityformszarinpal"); ?></label> </th>
                    <td width="400px">
                        <input class="size-1" id="gf_zarinpal_gname" name="gf_zarinpal_gname" value="<?php echo $gname; ?>" />
                       <br/>
				<label>تذکر مهم : این قسمت برای نمایش به بازدید کننده می باشد و لطفا جهت جلوگیری از</label><br/>
				<label>مشکل و تداخل آن را فقط یکبار تنظیم نمایید و از تنظیم مکرر آن خود داری نمایید .</label>
                    </td>
                </tr>
				
				
                <tr>
                    <td colspan="2" ><input  style="font-family:tahoma !important;" type="submit" name="gf_zarinpal_submit" class="button-primary" value="<?php _e("ذخیره تنظیمات", "gravityformszarinpal") ?>" /></td>
                </tr>

            </table>
			<input type="checkbox" name="gf_zarinpal_configured" id="gf_zarinpal_configured" onclick="confirm_settings_By_HANNANStd()" <?php echo $is_configured ? "checked='checked'" : ""?>/>
            <label for="gf_zarinpal_configured" class="inline"><?php _e("برای فعالسازی درگاه تیک را بزنید . نیازی به زدن دکمه ذخیره تنظیمات نمی باشد", "gravityformszarinpal") ?></label>
            <script type="text/javascript">
                function confirm_settings_By_HANNANStd(){
                    var confirmed = jQuery("#gf_zarinpal_configured").is(":checked") ? 1 : 0;
                    jQuery.post(ajaxurl, {action:"gf_zarinpal_confirm_settings", is_confirmed: confirmed});
                }
            </script>
        </form>
        <form action="" method="post">
            <?php wp_nonce_field("uninstall", "gf_zarinpal_uninstall") ?>
            <?php if(GFCommon::current_user_can_any("gravityforms_zarinpal_uninstall")){ ?>
                <div class="hr-divider"></div>

                 <h3 style="font-size:19px !important;"><?php _e("غیر فعالسازی افزونه دروازه پرداخت زرین پال", "gravityformszarinpal") ?></h3>
                <div class="delete-alert"><?php _e("تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به زرین پال حذف خواهد شد", "gravityformszarinpal") ?>
                    <?php
                    $uninstall_button = '<input  style="font-family:tahoma !important;" type="submit" name="uninstall" value="' . __("غیر فعال سازی درگاه زرین پال", "gravityformszarinpal") . '" class="button" onclick="return confirm(\'' . __("تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به زرین پال حذف خواهد شد . آیا همچنان مایل به غیر فعالسازی میباشید؟", "gravityformszarinpal") . '\');"/>';
                    echo apply_filters("gform_zarinpal_uninstall_button", $uninstall_button);
                    ?>
                </div>
            <?php } ?>
        </form>
        <?php
    }
	private static function is_valid_key(){
	$MerchantID = self::get_merchent(); 
	$Amount = 1000;
	$Description = 'جهت بررسی صحیح بودن تنظیمات افزونه'; 
	$Email = get_bloginfo("admin_email"); 
	$Mobile ='09123456789'; 
	$CallbackURL = 'http://'.$_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8')); 
	$result = $client->PaymentRequest(
						array(
								'MerchantID' 	=> $MerchantID,
								'Amount' 	=> $Amount,
								'Description' 	=> $Description,
								'Email' 	=> $Email,
								'Mobile' 	=> $Mobile,
								'CallbackURL' 	=> $CallbackURL
							)
	);
	return $result->Status;
	}
    private static function get_product_field_options($productFields, $selectedValue){
        $options = "<option value=''>" . __("یک محصول انتخاب نمایید", "gravityformszarinpal") . "</option>";
        foreach($productFields as $field){
            $label = GFCommon::truncate_middle($field["label"], 30);
            $selected = $selectedValue == $field["id"] ? "selected='selected'" : "";
            $options .= "<option value='{$field["id"]}' {$selected}>{$label}</option>";
        }
        return $options;
    }
    private static function stats_page(){
	$config = GFZarinPalData::get_feed(RGForms::get("id"));
	$form = RGFormsModel::get_form_meta($config["form_id"]);
	wp_dequeue_script('jquery-ui-datepicker');
	wp_dequeue_script('gform_datepicker_init');
	wp_enqueue_style("gform_datepicker_init", GFCommon::get_base_url() . "/css/datepicker.css", null, GFCommon::$version);
	?>
		<style>
        .zarinpal_graph_container{clear:both; padding-left:5px; min-width:789px; margin-right:50px;}
        .zarinpal_message_container{clear: both; padding-left:5px; text-align:center; padding-top:120px; border: 1px solid #CCC; background-color: #FFF; width:100%; height:200px;font-family:byekan !important;}
        .zarinpal_summary_container {margin:30px 60px; text-align: center; min-width:740px; margin-left:50px;}
        .zarinpal_summary_item {width:162px; height:70px; border-radius:5px; background-color: #FFF; border: 1px solid #CCC; padding:14px 8px; margin:6px 3px 6px 0; display: -moz-inline-stack; display: inline-block; zoom: 1; *display: inline; text-align:center;}
        .zarinpal_summary_value {font-size:20px; margin:5px 0; font-family:byekan;}
		.zarinpal_summary_title {font-family:byekan;}
		.tooltipbox_blue {background:#0074A2; padding:5px 10px 5px 5px; border-radius:4px; color:#fff;}
		.tooltipbox_green {background:#50B432; padding:5px 10px 5px 5px; border-radius:4px; color:#fff;}
		.tooltipbox_orang {background:#EDC240; padding:5px 10px 5px 5px; border-radius:4px; color:#fff;}
		.tooltipbox_red {background:#AA4643; padding:5px 10px 5px 5px; border-radius:4px; color:#fff;}
		.subsubsub a {font-family:Byekan !important;}
		.ui-datepicker-title select,.ui-datepicker-title option {font-family: byekan !important;font-size: 11px !important;}
		.ui-datepicker th {font-size: 12px !important;}
        </style>
		<script type='text/javascript' src='<?php echo GravityFormsPersian::get_base_url(); ?>/assets/js/Datepicker.js?ver=<?php echo GFCommon::$version; ?>'></script>                  	
		<script type="text/javascript" src="<?php echo GravityFormsPersian::get_base_url() ?>/assets/js/shamsi_chart.js"></script>
		<script type="text/javascript">
		var dp = jQuery.noConflict();
	    dp(document).ready(function() {
	        jQuery('.datepicker').datepicker({
	            dateFormat: 'yy-mm-dd',
				showButtonPanel: true,   
				changeMonth: true,
	            changeYear: true				
	        });
	    });
    </script>
	<div class="wrap">
		<img alt="<?php _e("زرین پال", "gravityformszarinpal") ?>" style="margin: 15px 7px 0pt 0pt; float: left;" src="<?php echo self::get_base_url() ?>/static/zarinpal.png"/>
		<ul class="subsubsub">
                    <li><a class="<?php echo (!RGForms::get("tab") || RGForms::get("tab") == "today") ? "current" : "" ?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>"><?php _e("امروز", "gravityformszarinpal"); ?></a> | </li>
					<li><a class="<?php echo RGForms::get("tab") == "yesterday" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=yesterday"><?php _e("دیروز", "gravityformszarinpal"); ?></a> | </li>
                    <li><a class="<?php echo RGForms::get("tab") == "last7days" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=last7days"><?php _e("هفت روز گذشته", "gravityformszarinpal"); ?></a> | </li>
                    <li><a class="<?php echo RGForms::get("tab") == "thisweek" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=thisweek"><?php _e("هفته جاری", "gravityformszarinpal"); ?></a> | </li>
                    <li><a class="<?php echo RGForms::get("tab") == "last30days" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=last30days"><?php _e("30 روز گذشته", "gravityformszarinpal"); ?></a> | </li>
                    <li><a class="<?php echo RGForms::get("tab") == "thismonth" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=thismonth"><?php _e("ماه جاری", "gravityformszarinpal"); ?></a>|</li>
					<li><a class="<?php echo RGForms::get("tab") == "lastmonth" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=lastmonth"><?php _e("ماه قبل", "gravityformszarinpal"); ?></a>|</li>
					<li><a class="<?php echo RGForms::get("tab") == "last2month" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=last2month"><?php _e("2 ماه اخیر", "gravityformszarinpal"); ?></a> | </li>
					<li><a class="<?php echo RGForms::get("tab") == "last3month" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=last3month"><?php _e("3 ماه اخیر", "gravityformszarinpal"); ?></a> | </li>
                    <li><a class="<?php echo RGForms::get("tab") == "last6month" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=last6month"><?php _e("6 ماه اخیر", "gravityformszarinpal"); ?></a> | </li>
                    <li><a class="<?php echo RGForms::get("tab") == "last9month" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=last9month"><?php _e("9 ماه اخیر", "gravityformszarinpal"); ?></a> | </li>
                    <li><a class="<?php echo RGForms::get("tab") == "last12month" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=last12month"><?php _e("یک سال اخیر", "gravityformszarinpal"); ?></a> | </li>
					<li><a class="<?php echo RGForms::get("tab") == "spring" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=spring"><?php _e("بهار", "gravityformszarinpal"); ?></a>|</li>
					<li><a class="<?php echo RGForms::get("tab") == "summer" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=summer"><?php _e("تابستان", "gravityformszarinpal"); ?></a>|</li>
					<li><a class="<?php echo RGForms::get("tab") == "fall" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=fall"><?php _e("پاییز", "gravityformszarinpal"); ?></a>|</li>
					<li><a class="<?php echo RGForms::get("tab") == "winter" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=winter"><?php _e("زمستان", "gravityformszarinpal"); ?></a>|</li>
					<li><a class="<?php echo RGForms::get("tab") == "thisyear" ? "current" : ""?>" href="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=thisyear"><?php _e("امسال", "gravityformszarinpal"); ?></a></li>      
					<br/><br/>
					<form method="post" action="?page=gf_zarinpal&view=stats&id=<?php echo $_GET["id"] ?>&tab=selection">
					<span style="font-family:byekan;">از تاریخ</span><input type="text" name="min"  class="datepicker" value="<?php echo $_POST['min']; ?>" autocomplete="off"/>
					<span style="font-family:byekan;margin-right:15px">تا تاریخ</span> <input type="text" name="max"  class="datepicker"  value="<?php echo $_POST['max']; ?>" autocomplete="off"/>
					<input type="submit" class="button-primary button" name="submit" value="انتخاب"><br>
					</form>
					</ul>
		<div class="clear"></div>
		<?php 
		switch(RGForms::get("tab")){
			//ok
			case "spring" :
            $chart_info = self::season_chart_info($config,1,1);
			$chart_info_gateways = self::season_chart_info($config,2,1);
            $chart_info_zarin = self::season_chart_info($config,3,1);
			$chart_info_site = self::season_chart_info($config,4,1);
			break;
			case "summer" :
            $chart_info = self::season_chart_info($config,1,2);
			$chart_info_gateways = self::season_chart_info($config,2,2);
            $chart_info_zarin = self::season_chart_info($config,3,2);
			$chart_info_site = self::season_chart_info($config,4,2);
			break;
			case "fall" :
            $chart_info = self::season_chart_info($config,1,3);
			$chart_info_gateways = self::season_chart_info($config,2,3);
            $chart_info_zarin = self::season_chart_info($config,3,3);
			$chart_info_site = self::season_chart_info($config,4,3);
			break;
			case "winter" :
            $chart_info = self::season_chart_info($config,1,4);
			$chart_info_gateways = self::season_chart_info($config,2,4);
            $chart_info_zarin = self::season_chart_info($config,3,4);
			$chart_info_site = self::season_chart_info($config,4,4);
			break;
			//ok
			case "thisyear" :
            $chart_info = self::yearly_chart_info($config,1);
			$chart_info_gateways = self::yearly_chart_info($config,2);
            $chart_info_zarin = self::yearly_chart_info($config,3);
			$chart_info_site = self::yearly_chart_info($config,4);
			break;
			//
			//ok
            case "last7days" :
            $chart_info = self::lastxdays_chart_info($config,1,7);
            $chart_info_gateways = self::lastxdays_chart_info($config,2,7);
            $chart_info_zarin = self::lastxdays_chart_info($config,3,7);
            $chart_info_site = self::lastxdays_chart_info($config,4,7);
            break;
			case "thisweek" :
            $chart_info = self::thisweek_chart_info($config,1);
            $chart_info_gateways = self::thisweek_chart_info($config,2);
            $chart_info_zarin = self::thisweek_chart_info($config,3);
            $chart_info_site = self::thisweek_chart_info($config,4);
            break;
			case "last30days" :
            $chart_info = self::lastxdays_chart_info($config,1,30);
            $chart_info_gateways = self::lastxdays_chart_info($config,2,30);
            $chart_info_zarin = self::lastxdays_chart_info($config,3,30);
            $chart_info_site = self::lastxdays_chart_info($config,4,30);
            break;
			//
			case "thismonth" :
            $chart_info = self::targetmdays_chart_info($config,1,1);
            $chart_info_gateways = self::targetmdays_chart_info($config,2,1);
            $chart_info_zarin = self::targetmdays_chart_info($config,3,1);
            $chart_info_site = self::targetmdays_chart_info($config,4,1);
            break;
			//
			case "lastmonth" :
            $chart_info = self::targetmdays_chart_info($config,1,2);
            $chart_info_gateways = self::targetmdays_chart_info($config,2,2);
            $chart_info_zarin = self::targetmdays_chart_info($config,3,2);
            $chart_info_site = self::targetmdays_chart_info($config,4,2);
            break;			
			case "last2month" :
            $chart_info = self::targetmdays_chart_info($config,1,60);
            $chart_info_gateways = self::targetmdays_chart_info($config,2,60);
            $chart_info_zarin = self::targetmdays_chart_info($config,3,60);
            $chart_info_site = self::targetmdays_chart_info($config,4,60);
            break;
			case "last3month" :
            $chart_info = self::targetmdays_chart_info($config,1,3);
            $chart_info_gateways = self::targetmdays_chart_info($config,2,3);
            $chart_info_zarin = self::targetmdays_chart_info($config,3,3);
            $chart_info_site = self::targetmdays_chart_info($config,4,3);
            break;
			case "last6month" :
            $chart_info = self::targetmdays_chart_info($config,1,6);
            $chart_info_gateways = self::targetmdays_chart_info($config,2,6);
            $chart_info_zarin = self::targetmdays_chart_info($config,3,6);
            $chart_info_site = self::targetmdays_chart_info($config,4,6);
            break;
			case "last9month" :
            $chart_info = self::targetmdays_chart_info($config,1,9);
            $chart_info_gateways = self::targetmdays_chart_info($config,2,9);
            $chart_info_zarin = self::targetmdays_chart_info($config,3,9);
            $chart_info_site = self::targetmdays_chart_info($config,4,9);
            break;
			case "last12month" :
            $chart_info = self::targetmdays_chart_info($config,1,12);
            $chart_info_gateways = self::targetmdays_chart_info($config,2,12);
            $chart_info_zarin = self::targetmdays_chart_info($config,3,12);
            $chart_info_site = self::targetmdays_chart_info($config,4,12);
            break;
			case "selection" :
            $chart_info = self::selection_chart_info($config,1,$min,$max);
            $chart_info_gateways = self::selection_chart_info($config,2,$min,$max);
            $chart_info_zarin = self::selection_chart_info($config,3,$min,$max);
            $chart_info_site = self::selection_chart_info($config,4,$min,$max);
            break;
			case "yesterday" :
            $chart_info = self::tyday_chart_info($config,1,2);
			$chart_info_gateways = self::tyday_chart_info($config,2,2);
            $chart_info_zarin = self::tyday_chart_info($config,3,2);
			$chart_info_site = self::tyday_chart_info($config,4,2);
			break;
			default :
            $chart_info = self::tyday_chart_info($config,1,1);
			$chart_info_gateways = self::tyday_chart_info($config,2,1);
            $chart_info_zarin = self::tyday_chart_info($config,3,1);
			$chart_info_site = self::tyday_chart_info($config,4,1);
			break;
		}
		?>
		<hr>
		<div class="clear"></div>
		<h2><?php _e(" درآمد از درگاه زرین پال برای فرمِ ", "gravityformszarinpal") ?><?php echo '"'.$form["title"].'"'; ?></h2>
		<form method="post" action="">
		<?php
        if(!$chart_info["series"]){
        ?>
        <div class="zarinpal_message_container"><?php _e("موردی یافت نشد . ", "gravityformszarinpal") ?></div>
        <?php
        }
        else{
        ?>
            <div class="zarinpal_graph_container">
            <div id="graph_placeholder" style="width:100%;height:300px;"></div>
            </div>
            <?php
            }
            switch($config["meta"]["type"]){
            case "product" :
            $sales_label = __("تعداد کل پرداخت های  زرین پال این فرم", "gravityformszarinpal");
            break;
			case "subscription" :
            $sales_label = __("تعداد اشتراک های موفق زرین پال این فرم", "gravityformszarinpal");
            break;
            }
			$transaction_totals = GFZarinPalData::get_transaction_totals($config["form_id"]);
			$total_sales = empty($transaction_totals["active"]["transactions"]) ? 0 : $transaction_totals["active"]["transactions"];
            $total_revenue = empty($transaction_totals["active"]["revenue"]) ? 0 : $transaction_totals["active"]["revenue"];
            ?>
            <div class="zarinpal_summary_container">
            <div class="zarinpal_summary_item">
            <div class="zarinpal_summary_title">جمع پرداخت های  زرین پال این فرم</div>
            <div class="zarinpal_summary_value"><?php echo GF_tr_num(GFCommon::to_money($total_revenue),'fa') ?></div>
            </div>
            <div class="zarinpal_summary_item">
            <div class="zarinpal_summary_title"><?php echo $chart_info["revenue_label"]?></div>
            <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info["revenue"],'fa') ?></div>
            </div>
			
			<div class="zarinpal_summary_item">
            <div class="zarinpal_summary_title"><?php echo $chart_info["mid_label"] ?></div>
            <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info["mid"],'fa') ?></div>
            </div>
			
            <div class="zarinpal_summary_item">
			<div class="zarinpal_summary_title"><?php echo $sales_label?></div>
            <div class="zarinpal_summary_value"><?php echo GF_tr_num($total_sales,'fa') ?></div>
            </div>
            <div class="zarinpal_summary_item">
            <div class="zarinpal_summary_title"><?php echo $chart_info["sales_label"] ?></div>
            <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info["sales"],'fa') ?></div>
            </div>
			
			<div class="zarinpal_summary_item">
            <div class="zarinpal_summary_title"><?php echo $chart_info["midt_label"] ?></div>
            <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info["midt"],'fa') ?></div>
            </div>
			
			
            </div>
        </form>
		<hr>
		<div class="clear"></div>
		<h2><?php _e(" درآمد از همه روش ها برای فرمِ ", "gravityformszarinpal") ?><?php echo '"'.$form["title"].'"'; ?></h2>
        <form method="post" action="">
            <?php
            if(!$chart_info_gateways["series"]){
            ?>
            <div class="zarinpal_message_container"><?php _e("موردی یافت نشد . ", "gravityformszarinpal") ?></div>
            <?php
            }
            else{
            ?>
            <div class="zarinpal_graph_container">
			<div id="graph_placeholder2" style="width:100%;height:300px;"></div>
            </div>
            <?php
            }
			switch($config["meta"]["type"]){
            case "product" :
            $sales_label = __("تعداد کل پرداخت های  همه روش های این فرم", "gravityformszarinpal");
            break;
			case "subscription" :
            $sales_label = __("تعداد اشتراک های  همه روشهای این فرم", "gravityformszarinpal");
                    break;
                }
				$transaction_totals = GFZarinPalData::get_transaction_totals_gateways($config["form_id"]);
                $total_sales = empty($transaction_totals["active"]["transactions"]) ? 0 : $transaction_totals["active"]["transactions"];
                $total_revenue = empty($transaction_totals["active"]["revenue"]) ? 0 : $transaction_totals["active"]["revenue"];
                ?>
                <div class="zarinpal_summary_container">
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php _e("جمع پرداخت های  همه روشهای این فرم", "gravityformszarinpal")?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num(GFCommon::to_money($total_revenue),'fa') ?></div>
                    </div>
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php echo $chart_info_gateways["revenue_label"]?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_gateways["revenue"],'fa') ?></div>
                    </div>
					
				<div class="zarinpal_summary_item">
				<div class="zarinpal_summary_title"><?php echo $chart_info_gateways["mid_label"] ?></div>
				<div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_gateways["mid"],'fa') ?></div>
				</div>
					
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php echo $sales_label?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num($total_sales,'fa') ?></div>
                    </div>
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php echo $chart_info_gateways["sales_label"] ?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_gateways["sales"],'fa') ?></div>
                    </div>
					
			<div class="zarinpal_summary_item">
            <div class="zarinpal_summary_title"><?php echo $chart_info_gateways["midt_label"] ?></div>
            <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_gateways["midt"],'fa') ?></div>
            </div>
					
                </div>
        </form>
		<hr>
		<div class="clear"></div>
		 <h2><?php _e(" کل درآمد های زرین پال", "gravityformszarinpal") ?></h2>
            <form method="post" action="">
                <?php
                if(!$chart_info_zarin["series"]){
                    ?>
                    <div class="zarinpal_message_container"><?php _e("موردی یافت نشد . ", "gravityformszarinpal") ?></div>
                    <?php
                }
                else{
                    ?>
                    <div class="zarinpal_graph_container">
                        <div id="graph_placeholder1" style="width:100%;height:300px;"></div>
                    </div>
                <?php
                }

                switch($config["meta"]["type"]){
                    case "product" :
                        $sales_label = __("تعداد کل پرداخت های درگاه زرین پال", "gravityformszarinpal");
                    break;
					case "subscription" :
                        $sales_label = __("تعداد کل اشتراک های درگاه زرین پال", "gravityformszarinpal");
                    break;
                }
				$transaction_totals = GFZarinPalData::get_transaction_totals_zarin($config["form_id"]);
                $total_sales = empty($transaction_totals["active"]["transactions"]) ? 0 : $transaction_totals["active"]["transactions"];
                $total_revenue = empty($transaction_totals["active"]["revenue"]) ? 0 : $transaction_totals["active"]["revenue"];
                ?>
                <div class="zarinpal_summary_container">
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php _e("جمع پرداخت های  همه فرمهای زرین پال", "gravityformszarinpal")?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num(GFCommon::to_money($total_revenue),'fa') ?></div>
                    </div>
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php echo $chart_info_zarin["revenue_label"]?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_zarin["revenue"],'fa') ?></div>
                    </div>
										
				<div class="zarinpal_summary_item">
				<div class="zarinpal_summary_title"><?php echo $chart_info_zarin["mid_label"] ?></div>
				<div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_zarin["mid"],'fa') ?></div>
				</div>
				
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php echo $sales_label?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num($total_sales,'fa') ?></div>
                    </div>
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php echo $chart_info_zarin["sales_label"] ?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_zarin["sales"],'fa') ?></div>
                    </div>
			<div class="zarinpal_summary_item">
            <div class="zarinpal_summary_title"><?php echo $chart_info_zarin["midt_label"] ?></div>
            <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_zarin["midt"],'fa') ?></div>
            </div>
                </div>
        </form>
		<hr>
		<div class="clear"></div>
		 <h2><?php _e(" کل درآمد های سایت ( همه روش ها برای همه فرم ها)", "gravityformszarinpal") ?></h2>
            <form method="post" action="">
                <?php
                if(!$chart_info_site["series"]){
                    ?>
                    <div class="zarinpal_message_container"><?php _e("موردی یافت نشد . ", "gravityformszarinpal") ?></div>
                    <?php
                }
                else{
                    ?>
                    <div class="zarinpal_graph_container">
                        <div id="graph_placeholder3" style="width:100%;height:300px;"></div>
                    </div>
                <?php
                }

                switch($config["meta"]["type"]){
                    case "product" :
                        $sales_label = __("تعداد کل پرداخت های همه فرمهای سایت", "gravityformszarinpal");
                    break;
					case "subscription" :
                        $sales_label = __("تعداد کل اشتراک های همه فرمهای سایت", "gravityformszarinpal");
                    break;
                }
				$transaction_totals = GFZarinPalData::get_transaction_totals_site($config["form_id"]);
                $total_sales = empty($transaction_totals["active"]["transactions"]) ? 0 : $transaction_totals["active"]["transactions"];
                $total_revenue = empty($transaction_totals["active"]["revenue"]) ? 0 : $transaction_totals["active"]["revenue"];
                ?>
                <div class="zarinpal_summary_container">
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php _e("جمع کل پرداخت های همه فرمهای سایت", "gravityformszarinpal")?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num(GFCommon::to_money($total_revenue),'fa') ?></div>
                    </div>
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php echo $chart_info_site["revenue_label"]?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_site["revenue"],'fa') ?></div>
                    </div>
					
				<div class="zarinpal_summary_item">
				<div class="zarinpal_summary_title"><?php echo $chart_info_site["mid_label"] ?></div>
				<div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_site["mid"],'fa') ?></div>
				</div>
				
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php echo $sales_label?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num($total_sales,'fa') ?></div>
                    </div>
                    <div class="zarinpal_summary_item">
                        <div class="zarinpal_summary_title"><?php echo $chart_info_site["sales_label"] ?></div>
                        <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_site["sales"],'fa') ?></div>
                    </div>
			<div class="zarinpal_summary_item">
            <div class="zarinpal_summary_title"><?php echo $chart_info_site["midt_label"] ?></div>
            <div class="zarinpal_summary_value"><?php echo GF_tr_num($chart_info_site["midt"],'fa') ?></div>
            </div>
                </div>
            </form>
		</div>
		<script type="text/javascript">
						var zarinpal_graph_tooltips = <?php echo GF_tr_num($chart_info["tooltips"],'fa') ?>;
                        jQuery.plot(jQuery("#graph_placeholder"), [<?php echo $chart_info["series"] ?>], <?php echo $chart_info["options"] ?>);
                        jQuery(window).resize(function(){
                        jQuery.plot(jQuery("#graph_placeholder"), [<?php echo $chart_info["series"] ?>], <?php echo $chart_info["options"] ?>);
                        });
                        var previousPoint = null;
                        jQuery("#graph_placeholder").bind("plothover", function (event, pos, item) {
                        startShowTooltip(item);
                        });
                        function startShowTooltip(item){
                        if (item) {
                        if (!previousPoint || previousPoint[0] != item.datapoint[0]) {
                        previousPoint = item.datapoint;
                        jQuery("#zarinpal_graph_tooltip").remove();
                        var x = item.datapoint[0].toFixed(2),
                        y = item.datapoint[1].toFixed(2);
                        showTooltip(item.pageX, item.pageY, zarinpal_graph_tooltips[item.dataIndex]);
                        }
                        }
                        else {
                        jQuery("#zarinpal_graph_tooltip").remove();
                        previousPoint = null;
                        }
                        }
                        var zarinpal_graph_tooltip1s1 = <?php echo GF_tr_num($chart_info_zarin["tooltips"],'fa') ?>;
                        jQuery.plot(jQuery("#graph_placeholder1"), [<?php echo $chart_info_zarin["series"] ?>], <?php echo $chart_info["options"] ?>);
                        jQuery(window).resize(function(){
                        jQuery.plot(jQuery("#graph_placeholder1"), [<?php echo $chart_info_zarin["series"] ?>], <?php echo $chart_info["options"] ?>);
                        });
                        var previousPoint = null;
                        jQuery("#graph_placeholder1").bind("plothover", function (event, pos, item) {
                        startShowTooltip1(item);
                        });
                        function startShowTooltip1(item){
                        if (item) {
                        if (!previousPoint || previousPoint[0] != item.datapoint[0]) {
                        previousPoint = item.datapoint;
                        jQuery("#zarinpal_graph_tooltip").remove();
                        var x = item.datapoint[0].toFixed(2),
                        y = item.datapoint[1].toFixed(2);
                        showTooltip(item.pageX, item.pageY, zarinpal_graph_tooltip1s1[item.dataIndex]);
                        }
                        }
                        else {
                        jQuery("#zarinpal_graph_tooltip").remove();
                        previousPoint = null;
                        }
                        }
						var zarinpal_graph_tooltip2s2 = <?php echo GF_tr_num($chart_info_gateways["tooltips"],'fa') ?>;
                        jQuery.plot(jQuery("#graph_placeholder2"), [<?php echo $chart_info_gateways["series"] ?>], <?php echo $chart_info["options"] ?>);
                        jQuery(window).resize(function(){
                        jQuery.plot(jQuery("#graph_placeholder2"), [<?php echo $chart_info_gateways["series"] ?>], <?php echo $chart_info["options"] ?>);
                        });
                        var previousPoint = null;
                        jQuery("#graph_placeholder2").bind("plothover", function (event, pos, item) {
                        startShowTooltip2(item);
                        });
                        function startShowTooltip2(item){
                        if (item) {
                        if (!previousPoint || previousPoint[0] != item.datapoint[0]) {
                        previousPoint = item.datapoint;
                        jQuery("#zarinpal_graph_tooltip").remove();
                        var x = item.datapoint[0].toFixed(2),
                        y = item.datapoint[1].toFixed(2);
                        showTooltip(item.pageX, item.pageY, zarinpal_graph_tooltip2s2[item.dataIndex]);
                        }
                        }
                        else {
                        jQuery("#zarinpal_graph_tooltip").remove();
                        previousPoint = null;
                        }
                        }
						var zarinpal_graph_tooltip3s3 = <?php echo GF_tr_num($chart_info_site["tooltips"],'fa') ?>;
                        jQuery.plot(jQuery("#graph_placeholder3"), [<?php echo $chart_info_site["series"] ?>], <?php echo $chart_info["options"] ?>);
                        jQuery(window).resize(function(){
                        jQuery.plot(jQuery("#graph_placeholder3"), [<?php echo $chart_info_site["series"] ?>], <?php echo $chart_info["options"] ?>);
                        });
                        var previousPoint = null;
                        jQuery("#graph_placeholder3").bind("plothover", function (event, pos, item) {
                        startShowTooltip3(item);
                        });
                        function startShowTooltip3(item){
                        if (item) {
                        if (!previousPoint || previousPoint[0] != item.datapoint[0]) {
                        previousPoint = item.datapoint;
                        jQuery("#zarinpal_graph_tooltip").remove();
                        var x = item.datapoint[0].toFixed(2),
                        y = item.datapoint[1].toFixed(2);
                        showTooltip(item.pageX, item.pageY, zarinpal_graph_tooltip3s3[item.dataIndex]);
                        }
                        }
                        else {
                        jQuery("#zarinpal_graph_tooltip").remove();
                        previousPoint = null;
                        }
                        }
						function showTooltip(x, y, contents) {
                        jQuery('<div id="zarinpal_graph_tooltip">' + contents + '<div class="tooltip_tip1"></div></div>').css( {
                        position: 'absolute',
                        display: 'none',
                        opacity: 1,
                        width:'150px',
                        height:'60px',
                        top: y - 89,
                        left: x - 79
                        }).appendTo("body").fadeIn(200);
                        }
						function convertToMoney(number){
                        var currency = getCurrentCurrency();
                        return currency.toMoney(number);
                        }
                        function getCurrentCurrency(){
                        <?php
                        if(!class_exists("RGCurrency"))
                        require_once(ABSPATH . "/" . PLUGINDIR . "/gravityforms/currency.php");
                        $current_currency = RGCurrency::get_currency(GFCommon::get_currency());
                        ?>
                        var currency = new Currency(<?php echo GFCommon::json_encode($current_currency)?>);
                        return currency;
                        }
						function weekday (val, axis) {
						var g_y=new Date(val).getFullYear();
						var g_m=new Date(val).getMonth()+1;
						var g_d=new Date(val).getDate();
						shamsi=gregorian_to_jalali(g_y,g_m,g_d);
						sh_month=["-","فروردین","اردیبهشت","خرداد","تير","مرداد","شهريور","مهر","آبان","آذر","دی","بهمن","اسفند"];
						week=["يكشنبه","دوشنبه","سه شنبه","چهارشنبه","پنج شنبه","جمعه","شنبه"];
						week= week[new Date(val).getDay()];
						return week+' - '+shamsi[2]+' '+sh_month[shamsi[1]]+' '+shamsi[0];
						}
						function shamsi_1 (val, axis) {
						var g_y=new Date(val).getFullYear();
						var g_m=new Date(val).getMonth()+1;
						var g_d=new Date(val).getDate();
						shamsi=gregorian_to_jalali(g_y,g_m,g_d);
						sh_month=["-","فروردین","اردیبهشت","خرداد","تير","مرداد","شهريور","مهر","آبان","آذر","دی","بهمن","اسفند"];
						return shamsi[2]+' '+sh_month[shamsi[1]]+' '+shamsi[0];
						}
						function shamsi_2 (val, axis) {
						var g_y=new Date(val).getFullYear();
						var g_m=new Date(val).getMonth()+1;
						var g_d=new Date(val).getDate();
						shamsi=gregorian_to_jalali(g_y,g_m,g_d);
						sh_month=["-","فروردین","اردیبهشت","خرداد","تير","مرداد","شهريور","مهر","آبان","آذر","دی","بهمن","اسفند"];
						H=new Date(val).getHours();
						H=(H<10)?"0"+H:H;
						i=new Date(val).getMinutes();
						i=(i<10)?"0"+i:i;
						s=new Date(val).getSeconds();
						s=(s<10)?"0"+s:s;
						return ' ساعت '+H;
						}
                    </script>
        <?php
    }
    private function get_graph_timestamp($local_datetime){
        $local_timestamp = mysql2date("G", $local_datetime); 
        $local_date_timestamp = mysql2date("G", gmdate("Y-m-d 23:59:59", $local_timestamp));
        $timestamp = ($local_date_timestamp - (24 * 60 * 60) + 1) * 1000;
        return $timestamp;
    }
    private static function lastxdays_chart_info($config,$chart,$x){
		global $wpdb;
        $tz = GravityFormsPersian::get_mysql_tz_offset();
		$tz_offset = $tz[tz];
		$tday = $tz[today];
		if ($chart==1) {$c = 'blue'; $dt="points: { symbol: 'diamond', fillColor: '#058DC7' }, color: '#058DC7'}"; $t="زرین پال این فرم";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 60");
		}
		if ($chart==2) {$c = 'green'; $dt="points: { symbol: 'square', fillColor: '#50B432' }, color: '#50B432'}"; $t="همه روشهای این فرم";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 60");
		}
		if ($chart==3) {$c = 'orang'; $dt="}";    $t="همه فرمهای زرین پال";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 60");
		}
		if ($chart==4) {$c = 'red'; $dt="points: { symbol: 'triangle', fillColor: '#AA4643' }, color: '#AA4643'}";  $t="همه فرمهای سایت";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 60");
		}		
		$sales_week = 0;
        $revenue_week = 0;
        $tooltips = "";
        if(!empty($results)){
			$today = date('Y-m-d',$tday);
			$today_n = date('Ymd',$tday);
			$targetdb = $today_n;
			$data = "[";
            foreach($results as $result){
					//
					$timeX_tooltips = GF_jdate('l - d F',strtotime($result->date),'',date_default_timezone_get(),'fa');
					$timeX = self::get_graph_timestamp($result->date);
					//  
					$target = date('Ymd',strtotime($result->date));  
					$target2 = date('Y-m-d',strtotime($result->date));  
					$date = new DateTime($today_n);
					if ($x==7){
					$date->sub(new DateInterval('P6DT0H0M'));
					$n = '7 روز';
					$mid = 7;
					}
					if ($x==30) {
					$date->sub(new DateInterval('P29DT0H0M'));
					$n = '30 روز';
					$mid = 30;
					}
					$lastxt = $date->format('Y-m-d');
					$lastxtf = $date->format('Ymd');
					if ($target > $targetdb){ 
					$targetdb = $target;
					$today = $target2; 
					}
				if($target >= $lastxtf && $today_n>=$target){
                    $sales_week += $result->new_sales;
                    $revenue_week += $result->amount_sold;
					$datat = $result->amount_sold;
                }
				if($target >= $lastxtf && $targetdb>=$target){
				$datat = $result->amount_sold;
				}
				$data .="[{$timeX},{$datat}],";
                if($config["meta"]["type"] == "subscription"){
                    $sales_line = " <div class='zarinpal_tooltip_subscription'><span class='zarinpal_tooltip_heading'>" . __("اشتراک های جدید", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                else{
                    $sales_line = "<div class='zarinpal_tooltip_sales'><span class='zarinpal_tooltip_heading'>" . __("تعداد پرداخت ", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                $tooltips .= "\"<div class='tooltipbox_".$c."'><div class='zarinpal_tooltip_date'>" . $timeX_tooltips . "</div>{$sales_line}<div class='zarinpal_tooltip_revenue'><span class='zarinpal_tooltip_heading'>" . __("پرداختی", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . GFCommon::to_money($result->amount_sold) . "</span></div></div>\",";
            }
			$data = substr($data, 0, strlen($data)-1);
            $tooltips = substr($tooltips, 0, strlen($tooltips)-1);
            $data .="]";
            $series = "{data:" . $data . ", ".$dt."";
            $options ="{
			series: {lines: {show: true},
			points: {show: true}},
			grid: {hoverable: true, clickable: true, tickColor: '#F1F1F1', backgroundColor:'#FFF', borderWidth: 1, borderColor: '#CCC'},
			xaxis: {mode: 'time', timeformat: '%d',tickFormatter: shamsi_1, minTickSize:[1, 'day'],min: (new Date('$lastxt')).getTime(),max: (new Date('$today')).getTime()},
			yaxis: {tickFormatter: convertToMoney}
			}";			
        }
        switch($config["meta"]["type"]){
            case "product" :
                $sales_label = "تعداد پرداخت های  ".$n." گذشته ".$t."";
            break;
            case "subscription" :
                $sales_label = "اشتراک های ".$n." گذشته ".$t."";
            break;
        }
        $midt = $sales_week/$mid;
		$midt= number_format($midt, 3, '.', '')." در روز";
		$midt_label = "میانگین تعداد پرداخت های  ".$n." گذشته ".$t."";
		$mid = GFCommon::to_money($revenue_week/$mid)." در روز";
		$mid_label = "میانگین پرداخت های  ".$n." گذشته ".$t."";
		$revenue_week = GFCommon::to_money($revenue_week);
		$revenue_label = "جمع پرداخت های  ".$n." گذشته ".$t."";
		return array("series" => $series, "options" => $options, "tooltips" => "[$tooltips]", "revenue_label" => $revenue_label, "revenue" => $revenue_week, "sales_label" => $sales_label, "sales" => $sales_week, "mid_label" => $mid_label, "mid" => $mid, "midt_label" => $midt_label, "midt" => $midt);
    }
	private static function thisweek_chart_info($config,$chart){
		global $wpdb;
        $tz = GravityFormsPersian::get_mysql_tz_offset();
		$tz_offset = $tz[tz];
		$tday = $tz[today];
		if ($chart==1) {$c = 'blue'; $dt="points: { symbol: 'diamond', fillColor: '#058DC7' }, color: '#058DC7'}"; $t = "زرین پال این فرم";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 15");
		}
		if ($chart==2) {$c = 'green'; $dt="points: { symbol: 'square', fillColor: '#50B432' }, color: '#50B432'}"; $t = "همه روشهای این فرم";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 15");
		}
		if ($chart==3) {$c = 'orang'; $dt="}"; 	$t = "همه فرمهای زرین پال";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 15");
		}
		if ($chart==4) {$c = 'red'; $dt="points: { symbol: 'triangle', fillColor: '#AA4643' }, color: '#AA4643'}"; $t = "همه فرم های سایت";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 15");
		}		
		$sales_week = 0;
        $revenue_week = 0;
        $tooltips = "";
        if(!empty($results)){
			$today_n = date('Y-m-d H:i:s',$tday);
			$today_w = date('w',$tday);
			if ($today_w<6){
			$today_w=$today_w+1;
			} else if ($today_w==6){
			$today_w=0;
			}
			switch($today_w) {				
					case "0" : // شنبه
					$date = new DateTime($today_n);
					$date->sub(new DateInterval('P0DT0H0M'));
					$abz = $date->format('m d, Y');
					$abz_t = $date->format('Ymd');
					$date = new DateTime($today_n);
					$date->add(new DateInterval('P6DT0H0M'));
					$ebz = $date->format('m d, Y');
					$ebz_t = $date->format('Ymd');
					break;
					case "1" : //یکشنبه
					$date = new DateTime($today_n);
					$date->sub(new DateInterval('P1DT0H0M'));
					$abz = $date->format('m d, Y');
					$abz_t = $date->format('Ymd');
					$date = new DateTime($today_n);
					$date->add(new DateInterval('P5DT0H0M'));
					$ebz = $date->format('m d, Y');
					$ebz_t = $date->format('Ymd');
					break;
					case "2" : //دوشنبه
					$date = new DateTime($today_n);
					$date->sub(new DateInterval('P2DT0H0M'));
					$abz = $date->format('m d, Y');
					$abz_t = $date->format('Ymd');
					$date = new DateTime($today_n);
					$date->add(new DateInterval('P4DT0H0M'));
					$ebz = $date->format('m d, Y');
					$ebz_t = $date->format('Ymd');
					break;
					case "3" : // سه شنبه
					$date = new DateTime($today_n);
					$date->sub(new DateInterval('P3DT0H0M'));
					$abz = $date->format('m d, Y');
					$abz_t = $date->format('Ymd');
					$date = new DateTime($today_n);
					$date->add(new DateInterval('P3DT0H0M'));
					$ebz = $date->format('m d, Y');
					$ebz_t = $date->format('Ymd');
					break;
					case "4" : // چهارشنبه
					$date = new DateTime($today_n);
					$date->sub(new DateInterval('P4DT0H0M'));
					$abz = $date->format('m d, Y');
					$abz_t = $date->format('Ymd');
					$date = new DateTime($today_n);
					$date->add(new DateInterval('P2DT0H0M'));
					$ebz = $date->format('m d, Y');
					$ebz_t = $date->format('Ymd');
					break;
					case "5" : // پنجشنبه
					$date = new DateTime($today_n);
					$date->sub(new DateInterval('P5DT0H0M'));
					$abz = $date->format('m d, Y');
					$abz_t = $date->format('Ymd');
					$date = new DateTime($today_n);
					$date->add(new DateInterval('P1DT0H0M'));
					$ebz = $date->format('m d, Y');
					$ebz_t = $date->format('Ymd');
					break;
					case "6" : // جمعه
					$date = new DateTime($today_n);
					$date->sub(new DateInterval('P6DT0H0M'));
					$abz = $date->format('m d, Y');
					$abz_t = $date->format('Ymd');
					$date = new DateTime($today_n);
					$date->add(new DateInterval('P0DT0H0M'));
					$ebz = $date->format('m d, Y');
					$ebz_t = $date->format('Ymd');
					break;
				}	
			$data = "[";
            foreach($results as $result){
					//
					$timeX_tooltips = GF_jdate('l - d F Y',strtotime($result->date),'',date_default_timezone_get(),'fa');
					$timeX = self::get_graph_timestamp($result->date);
					//
					$target = date('Ymd',strtotime($result->date));  
					if($target >= $abz_t && $ebz_t>=$target){
                    $sales_week += $result->new_sales;
                    $revenue_week += $result->amount_sold;
					$datat = $result->amount_sold;
					}
				$data .="[{$timeX},{$datat}],";
                if($config["meta"]["type"] == "subscription"){
                    $sales_line = " <div class='zarinpal_tooltip_subscription'><span class='zarinpal_tooltip_heading'>" . __("اشتراک های جدید", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                else{
                    $sales_line = "<div class='zarinpal_tooltip_sales'><span class='zarinpal_tooltip_heading'>" . __("تعداد پرداخت ", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                $tooltips .= "\"<div class='tooltipbox_".$c."'><div class='zarinpal_tooltip_date'>" . $timeX_tooltips . "</div>{$sales_line}<div class='zarinpal_tooltip_revenue'><span class='zarinpal_tooltip_heading'>" . __("پرداختی", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . GFCommon::to_money($result->amount_sold) . "</span></div></div>\",";
            }
			$data = substr($data, 0, strlen($data)-1);
            $tooltips = substr($tooltips, 0, strlen($tooltips)-1);
            $data .="]";
            $series = "{data:" . $data . ", ".$dt."";
            $options ="{
			series: {lines: {show: true},
			points: {show: true}},
			grid: {hoverable: true, clickable: true, tickColor: '#F1F1F1', backgroundColor:'#FFF', borderWidth: 1, borderColor: '#CCC'},
			xaxis: {mode: 'time',timeformat: '%d',tickFormatter: weekday, tickSize:[1, 'day'],min: (new Date('$abz 00:00:00')).getTime(),max: (new Date('$ebz 23:59:59')).getTime()},
			yaxis: {tickFormatter: convertToMoney}
			}";			
        }
        switch($config["meta"]["type"]){
            case "product" :
                $sales_label = "تعداد پرداخت های  این هفته ".$t;
            break;
            case "subscription" :
                $sales_label = "اشتراک های این هفته ".$t;
            break;
        }
		$midt = $sales_week/7;
		$midt= number_format($midt, 3, '.', '')." در روز";
		$midt_label = "میانگین تعداد پرداخت های  این هفته ".$t;
		$mid = GFCommon::to_money($revenue_week/7)." در روز";
		$mid_label = "میانگین پرداخت های این هفته ".$t;
		$revenue_week = GFCommon::to_money($revenue_week);
		$revenue_label = "جمع پرداخت های  این هفته ".$t;
       return array("series" => $series, "options" => $options, "tooltips" => "[$tooltips]", "revenue_label" => $revenue_label, "revenue" => $revenue_week, "sales_label" => $sales_label, "sales" => $sales_week, "mid_label" => $mid_label, "mid" => $mid, "midt_label" => $midt_label, "midt" => $midt);
    }
	private static function targetmdays_chart_info($config,$chart,$xmonth){
		global $wpdb;
        $tz = GravityFormsPersian::get_mysql_tz_offset();
		$tz_offset = $tz[tz];
		$tday = $tz[today];
		if ($chart==1) {$c = 'blue'; $dt="points: { symbol: 'diamond', fillColor: '#058DC7' }, color: '#058DC7'}"; $t="زرین پال این فرم";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 366");
		}
		if ($chart==2) {$c = 'green'; $dt="points: { symbol: 'square', fillColor: '#50B432' }, color: '#50B432'}";  $t="همه روشهای این فرم";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 366");
		}
		if ($chart==3) {$c = 'orang'; $dt="}";   $t="همه فرمهای زرین پال";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 366");
		}
		if ($chart==4) {$c = 'red'; $dt="points: { symbol: 'triangle', fillColor: '#AA4643' }, color: '#AA4643'}";  $t="همه فرم های سایت";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
                                        LIMIT 366");
		}		
		$sales_thistday = 0;
        $revenue_thistday = 0;
        $tooltips = "";
        if(!empty($results)){
		$today = date('Y-m-d',$tday);
					$saremaheshamsi=strtotime($today) + ( ( GF_jdate('t',strtotime($today),'',date_default_timezone_get(),'en') - GF_jdate('j',strtotime($today),'',date_default_timezone_get(),'en') ) * 86400 );
					$entbaz = date('m d , Y',$saremaheshamsi);
					$endd = date('Y-m-d',$saremaheshamsi);
					if ($xmonth==2) {
					$date = new DateTime($today);
					$date->sub(new DateInterval('P1M'));
					$today = $date->format('Y-m-d');
					$saremaheshamsi=strtotime($today) + ( ( GF_jdate('t',strtotime($today),'',date_default_timezone_get(),'en') - GF_jdate('j',strtotime($today),'',date_default_timezone_get(),'en') ) * 86400 );
					$entbaz = date('m d , Y',$saremaheshamsi);
					$endd = date('Y-m-d',$saremaheshamsi);
					$saremaheshamsi=strtotime($today) - ( ( GF_jdate('j',strtotime($today),'',date_default_timezone_get(),'en') ) * 86400 ) + 86400;
					$ebtbaz = date('m d , Y',$saremaheshamsi);
					$strd = date('Y-m-d',$saremaheshamsi);
					}
					if ($xmonth==1) {
					$saremaheshamsi=strtotime($today) - ( ( GF_jdate('j',strtotime($today),'',date_default_timezone_get(),'en') ) * 86400 ) + 86400;
					$ebtbaz = date('m d , Y',$saremaheshamsi);
					$strd = date('Y-m-d',$saremaheshamsi);
					}
					if ($xmonth==60) {
					$date = new DateTime($today);
					$date->sub(new DateInterval('P1M'));
					$today = $date->format('Y-m-d');
					$saremaheshamsi=strtotime($today) - ( ( GF_jdate('j',strtotime($today),'',date_default_timezone_get(),'en') ) * 86400 ) + 86400;
					$ebtbaz = date('m d , Y',$saremaheshamsi);
					$strd = date('Y-m-d',$saremaheshamsi);
					}
					if ($xmonth==3) {
					$date = new DateTime($today);
					$date->sub(new DateInterval('P2M'));
					$today = $date->format('Y-m-d');
					$saremaheshamsi=strtotime($today) - ( ( GF_jdate('j',strtotime($today),'',date_default_timezone_get(),'en') ) * 86400 ) + 86400;
					$ebtbaz = date('m d , Y',$saremaheshamsi);
					$strd = date('Y-m-d',$saremaheshamsi);
					}
					if ($xmonth==6) {
					$date = new DateTime($today);
					$date->sub(new DateInterval('P5M'));
					$today = $date->format('Y-m-d');
					$saremaheshamsi=strtotime($today) - ( ( GF_jdate('j',strtotime($today),'',date_default_timezone_get(),'en') ) * 86400 ) + 86400;
					$ebtbaz = date('m d , Y',$saremaheshamsi);
					$strd = date('Y-m-d',$saremaheshamsi);
					}
					if ($xmonth==9) {
					$date = new DateTime($today);
					$date->sub(new DateInterval('P8M'));
					$today = $date->format('Y-m-d');
					$saremaheshamsi=strtotime($today) - ( ( GF_jdate('j',strtotime($today),'',date_default_timezone_get(),'en') ) * 86400 ) + 86400;
					$ebtbaz = date('m d , Y',$saremaheshamsi);
					$strd = date('Y-m-d',$saremaheshamsi);
					}
					if ($xmonth==12) {
					$date = new DateTime($today);
					$date->sub(new DateInterval('P11M'));
					$today = $date->format('Y-m-d');
					$saremaheshamsi=strtotime($today) - ( ( GF_jdate('j',strtotime($today),'',date_default_timezone_get(),'en') ) * 86400 ) + 86400;
					$ebtbaz = date('m d , Y',$saremaheshamsi);
					$strd = date('Y-m-d',$saremaheshamsi);
					}
					list($m,$d,$n,$y) = explode(" ",$ebtbaz);
					$date = new DateTime("$y-$m-$d");
					$ebtbaz_w = $date->format('Ymd');
					list($m,$d,$n,$y) = explode(" ",$entbaz);
					$date = new DateTime("$y-$m-$d");
					$entbaz_w = $date->format('Ymd');
				$data = "[";
				foreach($results as $result){
					//
					$timeX_tooltips = GF_jdate('l - d F Y',strtotime($result->date),'',date_default_timezone_get(),'fa'); 
					$timeX = self::get_graph_timestamp($result->date);
					//
					$target = date('Ymd',strtotime($result->date));  
					if(  $entbaz_w >= $target && $target >= $ebtbaz_w  ){
					$sales_thistday += $result->new_sales;
                    $revenue_thistday += $result->amount_sold;
					$datat = $result->amount_sold;
					}
				$data .="[{$timeX},{$datat}],";
                if($config["meta"]["type"] == "subscription"){
                    $sales_line = " <div class='zarinpal_tooltip_subscription'><span class='zarinpal_tooltip_heading'>" . __("اشتراک های جدید", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                else{
                    $sales_line = "<div class='zarinpal_tooltip_sales'><span class='zarinpal_tooltip_heading'>" . __("تعداد پرداخت ", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                $tooltips .= "\"<div class='tooltipbox_".$c."'><div class='zarinpal_tooltip_date'>" . $timeX_tooltips . "</div>{$sales_line}<div class='zarinpal_tooltip_revenue'><span class='zarinpal_tooltip_heading'>" . __("پرداختی", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . GFCommon::to_money($result->amount_sold) . "</span></div></div>\",";
            }
			$data = substr($data, 0, strlen($data)-1);
            $tooltips = substr($tooltips, 0, strlen($tooltips)-1);
            $data .="]";
			if ($xmonth==1 || $xmonth==2) {
			$n = GF_jdate('F',strtotime($today),'',date_default_timezone_get(),'en');
			$n = $n." ماه";
			}
			if ( $xmonth==60 || $xmonth==3 || $xmonth==6 || $xmonth==9 || $xmonth==12 ){
			$n = $xmonth;
			if ($xmonth == 60) $n = 2;
			$n = $n.' ماه اخیر';
			if ($xmonth == 12) $n = 'یک سال اخیر';
			}
			if ($xmonth==1 || $xmonth==2 || $xmonth==60) {
			$mt = 1;
			}
			if ($xmonth==3 || $xmonth==6 ) {
			$mt = 5;
			}
			if ($xmonth==9 || $xmonth==12 ) {
			$mt = 10;
			}
			$series = "{data:" . $data . ", ".$dt."";
            $options ="{
			series: {lines: {show: true},
			points: {show: true}},
			grid: {hoverable: true, clickable: true, tickColor: '#F1F1F1', backgroundColor:'#FFF', borderWidth: 1, borderColor: '#CCC'},
			xaxis: {mode: 'time',timeformat: '%d',tickFormatter: shamsi_1, minTickSize:[$mt, 'day'],min: (new Date('$ebtbaz 00:00:00')).getTime(),max: (new Date('$entbaz 23:59:59')).getTime()},
			yaxis: {tickFormatter: convertToMoney}
			}";			
        }
        switch($config["meta"]["type"]){
            case "product" :
                $sales_label = 'تعداد پرداخت های  '.$n. ' '.$t;
            break;
            case "subscription" :
                $sales_label = 'اشتراک های  '.$n. ' '.$t;
            break;
        }
		$strd = date_create($strd);
		$endd = date_create($endd);
		$diff=date_diff($strd,$endd);
		$midd =  $diff->format("%a")+1;
		$midt = $sales_thistday/$midd;
		$midt= number_format($midt, 3, '.', '')." در روز";
		$midt_label = 'میانگین تعداد پرداخت های '.$n. ' '.$t;
		$mid = GFCommon::to_money($revenue_thistday/$midd)." در روز";
		$mid_label = 'میانگین پرداخت های '.$n. ' '.$t;
		$revenue_label = 'جمع پرداخت های  '.$n. ' '.$t;
        $revenue_thistday = GFCommon::to_money($revenue_thistday);
        return array("series" => $series, "options" => $options, "tooltips" => "[$tooltips]", "revenue_label" => $revenue_label, "revenue" => $revenue_thistday, "sales_label" => $sales_label, "sales" => $sales_thistday, "mid_label" => $mid_label, "mid" => $mid, "midt_label" => $midt_label, "midt" => $midt);
    }
	private static function tyday_chart_info($config,$chart,$day){
        global $wpdb;
		$tz = GravityFormsPersian::get_mysql_tz_offset();
		$tz_offset = $tz[tz];
		$tday = $tz[today];
		if ($chart==1) {$c = 'blue'; $dt="points: { symbol: 'diamond', fillColor: '#058DC7' }, color: '#058DC7'}"; $t = "زرین پال این فرم";
        $results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY hour(date) , day(date)
                                        ORDER BY payment_date desc
                                        LIMIT 48");
		}	
		if ($chart==2) {$c = 'green'; $dt="points: { symbol: 'square', fillColor: '#50B432' }, color: '#50B432'}"; $t = "همه روشهای این فرم";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1
                                        GROUP BY hour(date) , day(date)
                                        ORDER BY payment_date desc
                                        LIMIT 48");
		}	
		if ($chart==3) {$c = 'orang'; $dt="color: '#EDC240'}"; 	$t = "همه فرمهای زرین پال";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY hour(date) , day(date)
                                        ORDER BY payment_date desc
                                        LIMIT 48");
		}
			
		if ($chart==4) {$c = 'red'; $dt="points: { symbol: 'triangle', fillColor: '#AA4643' }, color: '#AA4643'}"; $t = "همه فرم های سایت";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1
                                        GROUP BY hour(date) , day(date)
                                        ORDER BY payment_date desc
                                        LIMIT 48");
		}
        $sales_today = 0;
        $revenue_today = 0;
        $tooltips = "";
        if(!empty($results)){
		$today = date('Y-m-d H:i:s',$tday);
		if ($day==1) {
		$n = "امروز";
		$date = new DateTime($today);
		$baze = date('m d , Y',$tday);
		$ty = date('Ymd',$tday);
		}
		if ($day==2) {
		$n = "دیروز";
		$date = new DateTime($today);
		$date->sub(new DateInterval('P1DT0H0M'));
		$baze  = $date->format('m d , Y');
		$ty  = $date->format('Ymd');
		}
		$data = "[";
        foreach($results as $result){
					$h = GF_jdate('H',strtotime($result->date),'',date_default_timezone_get(),'en');
					$h = intval($h)+1;
					if ($h<10) $h="0".$h;
					$timeX_tooltips = GF_jdate("l - d F Y ساعت H تا $h",strtotime($result->date),'',date_default_timezone_get(),'fa');
					$target = date('Ymd',strtotime($result->date)); 
					$date = new DateTime($result->date);
					$H = $date->format('H');
					$H = intval($H)+1;
					if ($H<10) $H="0".$H;
					$d = $date->format('d');
					$m = $date->format('m');
					$y = $date->format('Y');
				if($target == $ty){
                    $sales_today += $result->new_sales;
                    $revenue_today += $result->amount_sold;
					$datat = $result->amount_sold;
                }
				$data .="[(new Date('$m $d , $y $H:00:30')).getTime(),{$datat}],";
                if($config["meta"]["type"] == "subscription"){
                    $sales_line = " <div class='zarinpal_tooltip_subscription'><span class='zarinpal_tooltip_heading'>" . __("اشتراک های جدید", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                else{
                    $sales_line = "<div class='zarinpal_tooltip_sales'><span class='zarinpal_tooltip_heading'>" . __("تعداد پرداخت ", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                $tooltips .= "\"<div class='tooltipbox_".$c."'><div class='zarinpal_tooltip_date'>" . $timeX_tooltips . "</div>{$sales_line}<div class='zarinpal_tooltip_revenue'><span class='zarinpal_tooltip_heading'>" . __("پرداختی", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . GFCommon::to_money($result->amount_sold) . "</span></div></div>\",";
            }
			$data = substr($data, 0, strlen($data)-1);
            $tooltips = substr($tooltips, 0, strlen($tooltips)-1);
            $data .="]";
            $series = "{data:" . $data . ", ".$dt."";
            $options ="{
			xaxis: {mode: 'time',timeformat: '%d',tickFormatter: shamsi_2, tickSize:[1, 'hour'],
			min: (new Date('$baze 00:00:00')).getTime(),max: (new Date('$baze 24:59:00')).getTime()},
			yaxis: {tickFormatter: convertToMoney},
            bars: {show:true, align:'right', barWidth: (1 * 59 * 60 * 1000)},
            colors: ['#a3bcd3', '#14568a'],
            grid: {hoverable: true, clickable: true, tickColor: '#F1F1F1', backgroundColor:'#FFF', borderWidth: 1, borderColor: '#CCC'}
			}";			
        }
        switch($config["meta"]["type"]){
            case "product" :
                $sales_label = "تعداد پرداخت های ".$n." ".$t;
            break;
            case "subscription" :
                $sales_label = "تعداد اشتراک های ".$n." ".$t;
            break;
        }
		$midt = $sales_today/24;
		$midt= number_format($midt, 3, '.', '')." در ساعت";
		$midt_label ="میانگین تعداد پرداخت های ".$n." ".$t;
		$mid = GFCommon::to_money($revenue_today/24)." در ساعت";
		$mid_label = "میانگین پرداخت های ".$n." ".$t;
		$revenue_today = GFCommon::to_money($revenue_today);
		$revenue_label = "جمع پرداخت های ".$n." ".$t;
        return array("series" => $series, "options" => $options, "tooltips" => "[$tooltips]", "revenue_label" => $revenue_label, "revenue" => $revenue_today, "sales_label" => $sales_label, "sales" => $sales_today, "mid_label" => $mid_label, "mid" => $mid, "midt_label" => $midt_label, "midt" => $midt);
    }
	private static function yearly_chart_info($config,$chart){
            global $wpdb;
            $tz = GravityFormsPersian::get_mysql_tz_offset();
			$tz_offset = $tz[tz];
			$tday = $tz[today];
			if ($chart==1) {$c = 'blue'; $dt="points: { symbol: 'diamond', fillColor: '#058DC7' }, color: '#058DC7'}"; $t = "زرین پال این فرم";
            $results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
											FROM {$wpdb->prefix}rg_lead l
											WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                            group by date
                                            order by date desc
                                            LIMIT 366");
			}
			if ($chart==2) {$c = 'green'; $dt="points: { symbol: 'square', fillColor: '#50B432' }, color: '#50B432'}"; $t = "همه روشهای این فرم";
			$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
											FROM {$wpdb->prefix}rg_lead l
											WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1
                                            group by date
                                            order by date desc
                                            LIMIT 366");
			}
			if ($chart==3) {$c = 'orang'; $dt="}"; $t = "همه فرمهای زرین پال";
			$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
											FROM {$wpdb->prefix}rg_lead l
											WHERE l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                            group by date
                                            order by date desc
                                            LIMIT 366");
			}
			if ($chart==4){$c = 'red'; $dt="points: { symbol: 'triangle', fillColor: '#AA4643' }, color: '#AA4643'}"; $t = "همه فرمهای سایت";
			$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
											FROM {$wpdb->prefix}rg_lead l
											WHERE l.status='active' AND l.is_fulfilled=1
                                            group by date
                                            order by date desc
                                            LIMIT 366");
			}
			$sales_yearly = 0;
            $revenue_yearly = 0;
            $tooltips = "";
            if(!empty($results)){
			$today = date('Y-m-d',$tday);
			$emsal = date('Y',$tday);
			$kabise = GF_jdate('L',$tday,'',date_default_timezone_get(),'en');
			if ( $kabise == 1 ) {
			$avalesal = new DateTime("$emsal-03-20");
			$emsal++;
			$akharesal = new DateTime("$emsal-03-19");
			}
			else {
			$avalesal = new DateTime("$emsal-03-21");
			$emsal++;
			$akharesal = new DateTime("$emsal-03-20");
			}
			$avalesal_w = $avalesal->format('Ymd');
			$strd = $avalesal->format('Y-m-d');
			$avalesal = $avalesal->format('m d , Y');
			$akharesal_w = $akharesal->format('Ymd');
			$endd = $akharesal->format('Y-m-d');
			$akharesal = $akharesal->format('m d , Y');
			$data = "[";
                foreach($results as $result){
					//
					$timeX_tooltips = GF_jdate("d F Y",strtotime($result->date),'',date_default_timezone_get(),'fa');
					$timeX = self::get_graph_timestamp($result->date);
					//
					$target = date('Ymd',strtotime($result->date));  
					if(  $akharesal_w >= $target && $target >= $avalesal_w  ){
                        $sales_yearly += $result->new_sales;
                        $revenue_yearly += $result->amount_sold;
						$datat = $result->amount_sold;
                    }
                    $data .="[{$timeX},{$datat}],";
                    if($config["meta"]["type"] == "subscription"){
                        $sales_line = " <div class='zarinpal_tooltip_subscription'><span class='zarinpal_tooltip_heading'>" . __("اشتراک های جدید", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                    }
                    else{
                        $sales_line = "<div class='zarinpal_tooltip_sales'><span class='zarinpal_tooltip_heading'>" . __("تعداد پرداخت ", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                    }
                    $tooltips .= "\"<div class='tooltipbox_".$c."'><div class='zarinpal_tooltip_date'>" . $timeX_tooltips . "</div>{$sales_line}<div class='zarinpal_tooltip_revenue'><span class='zarinpal_tooltip_heading'>" . __("پرداختی", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . GFCommon::to_money($result->amount_sold) . "</span></div></div>\",";
                }
			    $data = substr($data, 0, strlen($data)-1);
                $tooltips = substr($tooltips, 0, strlen($tooltips)-1);
                $data .="]";
                $series = "{data:" . $data . ", ".$dt."";
                $options ="
                {	
				series: {lines: {show: true},
				points: {show: true}},
				grid: {hoverable: true, clickable: true, tickColor: '#F1F1F1', backgroundColor:'#FFF', borderWidth: 1, borderColor: '#CCC'},
				xaxis: {mode: 'time',timeformat: '%d',tickFormatter: shamsi_1,  minTickSize:[10, 'day'],min: (new Date('$avalesal 00:00:00')).getTime(),max: (new Date('$akharesal 00:00:00')).getTime()},
                yaxis: {tickFormatter: convertToMoney}
				}";																									
            }																					
            switch($config["meta"]["type"]){
                case "product" :
                    $sales_label = "تعداد پرداخت های امسال ".$t;
                break;
				case "subscription" :
                    $sales_label = "تعداد اشتراک های امسال ".$t;
                break;
            }
			$strd = date_create($strd);
			$endd = date_create($endd);
			$diff = date_diff($strd,$endd);
			$midd =  $diff->format("%a")+1;
			$midt = $sales_yearly/$midd;
			$midt= number_format($midt, 3, '.', '')." در روز";
			$midt_label = "میانگین تعداد پرداخت های امسال ".$t;
			$mid = GFCommon::to_money($revenue_yearly/$midd)." در روز";
			$mid_label = "میانگین پرداخت های امسال ".$t;
			$revenue_yearly = GFCommon::to_money($revenue_yearly);
			$revenue_label = "جمع پرداخت های امسال ".$t;
            return array("series" => $series, "options" => $options, "tooltips" => "[$tooltips]", "revenue_label" => $revenue_label , "revenue" => $revenue_yearly, "sales_label" => $sales_label, "sales" => $sales_yearly, "mid_label" => $mid_label, "mid" => $mid, "midt_label" => $midt_label, "midt" => $midt);
    }
	private static function season_chart_info($config,$chart,$season){
		global $wpdb;	
        $tz = GravityFormsPersian::get_mysql_tz_offset();
		$tz_offset = $tz[tz];
		$tday = $tz[today];
		
		if ($chart==1) {$c = 'blue'; $dt="points: { symbol: 'diamond', fillColor: '#058DC7' }, color: '#058DC7'}"; $t="زرین پال این فرم";
        $results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
										LIMIT 366");
		}
		if ($chart==2) {$c = 'green'; $dt="points: { symbol: 'square', fillColor: '#50B432' }, color: '#50B432'}"; $t="همه روشهای این فرم";
        $results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
										LIMIT 366");
		}
		if ($chart==3) {$c = 'orang'; $dt="}";  $t="همه فرمهای زرین پال";
        $results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
										LIMIT 366");
		}
		if ($chart==4) {$c = 'red'; $dt="points: { symbol: 'triangle', fillColor: '#AA4643' }, color: '#AA4643'}"; $t="همه فرم های سایت";
        $results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc
										LIMIT 366");
		}
        $sales_season = 0;
        $revenue_season = 0;
        $tooltips = "";
		if(!empty($results)){
			$today = date('Y-m-d',$tday);
			$avalesal=strtotime($today) - ( GF_jdate('z',$tday,'',date_default_timezone_get(),'en') * 86400 );
			$avalesal = date('m d , Y',$avalesal);
			$akharesal=strtotime($today) + ( GF_jdate('Q',$tday,'',date_default_timezone_get(),'en') * 86400 );
			$akharesal = date('m d , Y',$akharesal);
			list($m,$d,$n,$y) = explode(" ",$avalesal);
			$date = new DateTime("$y-$m-$d");
			$avalesal_w = $date->format('Ymd');	
			$avalesal_t = $date->format('Y-m-d');
			list($m,$d,$n,$y) = explode(" ",$akharesal);
			$date = new DateTime("$y-$m-$d");
			$akharesal_w = $date->format('Ymd');
			$akharesal_t = $date->format('Y-m-d');
			$endd = $akharesal_t;
			if ($season==1){
			$n='بهار';
			$ebtda = $avalesal_t;
			$enteha=strtotime($ebtda)+(93*86400)-86400;
			$enteha = date('m d , Y',$enteha);
			$ebtda = $avalesal;
			$midd = 93;
			}
			if ($season==2){
			$n='تابستان';
			$ebtda = $avalesal_t;
			$ebtda=strtotime($ebtda)+(93*86400);
			$ebtda = date('m d , Y',$ebtda);
			$enteha = $avalesal_t;
			$enteha=strtotime($enteha)+(186*86400)-86400;
			$enteha = date('m d , Y',$enteha);
			$midd = 93;
			}
			if ($season==3){
			$n='پاییز';
			$ebtda = $avalesal_t;
			$ebtda=strtotime($ebtda)+(186*86400);
			$ebtda = date('m d , Y',$ebtda);
			$enteha = $avalesal_t;
			$enteha=strtotime($enteha)+(276*86400)-86400;
			$enteha = date('m d , Y',$enteha);
			$midd = 90;
			}
			if ($season==4){
			$n='زمستان';
			$ebtda = $avalesal_t;
			$ebtda=strtotime($ebtda)+(276*86400);
			$strd = date('Y-m-d',$ebtda);
			$ebtda = date('m d , Y',$ebtda);
			$strd = date_create($strd);
			$endd = date_create($endd);
			$diff = date_diff($strd,$endd);
			$midd =  $diff->format("%a")+1;
			$enteha = $akharesal;			
			}
			$data = "[";
            foreach($results as $result){
					//
					$timeX_tooltips = GF_jdate('l - d F Y',strtotime($result->date),'',date_default_timezone_get(),'fa');
					$timeX = self::get_graph_timestamp($result->date);
					//
					$faslt = GF_jdate('b',strtotime($result->date),'',date_default_timezone_get(),'en');	
					$target = date('Ymd',strtotime($result->date));  
					if (  ($akharesal_w >= $target && $target >= $avalesal_w && $faslt==$season) ||  ($enteha >= $target && $target >= $ebtda)  ){ 
					$sales_season += $result->new_sales;
					$revenue_season += $result->amount_sold;
					$datat = $result->amount_sold;
					}	
				$data .="[{$timeX},{$datat}],";
                if($config["meta"]["type"] == "subscription"){
                    $sales_line = " <div class='zarinpal_tooltip_subscription'><span class='zarinpal_tooltip_heading'>" . __("اشتراک های جدید", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                else{
                    $sales_line = "<div class='zarinpal_tooltip_sales'><span class='zarinpal_tooltip_heading'>" . __("تعداد پرداخت ", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                $tooltips .= "\"<div class='tooltipbox_".$c."'><div class='zarinpal_tooltip_date'>" . $timeX_tooltips . "</div>{$sales_line}<div class='zarinpal_tooltip_revenue'><span class='zarinpal_tooltip_heading'>" . __("پرداختی", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . GFCommon::to_money($result->amount_sold) . "</span></div></div>\",";
            }
			$data = substr($data, 0, strlen($data)-1);
            $tooltips = substr($tooltips, 0, strlen($tooltips)-1);
            $data .="]";
            $series = "{data:" . $data . ", ".$dt."";
            $options ="{
			series: {lines: {show: true},
			points: {show: true}},
			grid: {hoverable: true, clickable: true, tickColor: '#F1F1F1', backgroundColor:'#FFF', borderWidth: 1, borderColor: '#CCC'},
			xaxis: {mode: 'time',timeformat: '%d',tickFormatter: shamsi_1, minTickSize:[3, 'day'],min: (new Date('$ebtda 00:00:00')).getTime(),max: (new Date('$enteha 23:59:59')).getTime()},
			yaxis: {tickFormatter: convertToMoney}
			}";			
        }
        switch($config["meta"]["type"]){
            case "product" :
                $sales_label = "تعداد پرداخت های  ".$n ." ".$t;
            break;
            case "subscription" :
                $sales_label = "اشتراک های ".$n ." ".$t;
            break;
        }
		$midt = $sales_season/$midd;
		$midt= number_format($midt, 3, '.', '')." در روز";
		$midt_label = "میانگین تعداد پرداخت های  ".$n ." ".$t;
		$mid = GFCommon::to_money($revenue_season/$midd)." در روز";
		$mid_label = "میانگین پرداخت های  ".$n ." ".$t;
		$revenue_label = "جمع پرداخت های  ".$n ." ".$t;
        $revenue_season = GFCommon::to_money($revenue_season);
        return array("series" => $series, "options" => $options, "tooltips" => "[$tooltips]", "revenue_label" => $revenue_label, "revenue" => $revenue_season, "sales_label" => $sales_label, "sales" => $sales_season, "mid_label" => $mid_label, "mid" => $mid, "midt_label" => $midt_label, "midt" => $midt);
    }
	private static function selection_chart_info($config,$chart,$min,$max){
        global $wpdb;
        $tz = GravityFormsPersian::get_mysql_tz_offset();
		$tz_offset = $tz[tz];
		$tday = $tz[today];
		if ($chart==1) {$c = 'blue'; $dt="points: { symbol: 'diamond', fillColor: '#058DC7' }, color: '#058DC7'}"; $t = "زرین پال این فرم";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc");
		}
		if ($chart==2) {$c = 'green'; $dt="points: { symbol: 'square', fillColor: '#50B432' }, color: '#50B432'}"; $t = "همه روشهای این فرم";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE form_id={$config["form_id"]} AND l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc");
		}
		if ($chart==3) {$c = 'orang'; $dt="}";  $t = "همه فرمهای زرین پال";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc");
		}
		if ($chart==4) {$c = 'red'; $dt="points: { symbol: 'triangle', fillColor: '#AA4643' }, color: '#AA4643'}"; $t = "همه فرمهای سایت";
		$results = $wpdb->get_results("SELECT CONVERT_TZ(l.payment_date, '+00:00', '" . $tz_offset . "') as date, sum(l.payment_amount) as amount_sold, count(l.id) as new_sales
                                        FROM {$wpdb->prefix}rg_lead l
                                        WHERE l.status='active' AND l.is_fulfilled=1
                                        GROUP BY date(date)
                                        ORDER BY payment_date desc");
		}
		$sales_today = 0;
        $revenue_today = 0;
        $tooltips = "";
        if(!empty($results) && isset($_POST['submit']) && $_POST['max'] && $_POST['min']){
		list($y2,$m2,$d2) = explode ("-", $_POST['max']);
		$max = GF_jalali_to_gregorian($y2,$m2,$d2);
		$date = new DateTime("$max[0]-$max[1]-$max[2]");
		$max_w = $date->format('Ymd');
		$max_t = $date->format('m d , Y');
		$endd = $date->format('Y-m-d');
		list($y1,$m1,$d1) = explode ("-", $_POST['min']);
		$min = GF_jalali_to_gregorian($y1,$m1,$d1);
		$date = new DateTime("$min[0]-$min[1]-$min[2]");
		$min_w = $date->format('Ymd');
		$min_t = $date->format('m d , Y');
		$strd = $date->format('Y-m-d');
            $data = "[";
            foreach($results as $result){
					//
					$timeX_tooltips = GF_jdate('l - d F Y',strtotime($result->date),'',date_default_timezone_get(),'fa'); 
					$timeX = self::get_graph_timestamp($result->date);
					//
					$target = date('Ymd',strtotime($result->date));  
					if(  $max_w >= $target && $target >= $min_w  ){
					$sales_today += $result->new_sales;
                    $revenue_today += $result->amount_sold;
					$datat = $result->amount_sold;
					}
                $data .="[{$timeX},{$datat}],";
                if($config["meta"]["type"] == "subscription"){
                    $sales_line = " <div class='zarinpal_tooltip_subscription'><span class='zarinpal_tooltip_heading'>" . __("اشتراک های جدید", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                else{
                    $sales_line = "<div class='zarinpal_tooltip_sales'><span class='zarinpal_tooltip_heading'>" . __("تعداد پرداخت ", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . $result->new_sales . "</span></div>";
                }
                $tooltips .= "\"<div class='tooltipbox_".$c."'><div class='zarinpal_tooltip_date'>" . $timeX_tooltips . "</div>{$sales_line}<div class='zarinpal_tooltip_revenue'><span class='zarinpal_tooltip_heading'>" . __("پرداختی", "gravityformszarinpal") . ": </span><span class='zarinpal_tooltip_value'>" . GFCommon::to_money($result->amount_sold) . "</span></div></div>\",";
            }
			$data = substr($data, 0, strlen($data)-1);
            $tooltips = substr($tooltips, 0, strlen($tooltips)-1);
            $data .="]";
            $series = "{data:" . $data . ", ".$dt."";

			$strd = date_create($strd);
			$endd = date_create($endd);
			$diff = date_diff($strd,$endd);
			$midd =  $diff->format("%a")+1;
			$mt = 1;
			$tt = 'day';
			if ($midd>30 ) {
			$mt = 5;
			}
			if ($midd>63 ) {
			$mt = 10;
			}
			if ($midd>100 ) {
			$mt = 20;
			}
			if ($midd>364 ) { 
			$mt = 1;
			$tt = 'month';
			}
            $options ="{
			series: {lines: {show: true},
			points: {show: true}},
			grid: {hoverable: true, clickable: true, tickColor: '#F1F1F1', backgroundColor:'#FFF', borderWidth: 1, borderColor: '#CCC'},
			xaxis: {mode: 'time',timeformat: '%d',tickFormatter: shamsi_1, minTickSize:[$mt, '$tt'],min: (new Date('$min_t 00:00:00')).getTime(),max: (new Date('$max_t 23:59:59')).getTime()},
			yaxis: {tickFormatter: convertToMoney}
			}";			
        }
        switch($config["meta"]["type"]){
            case "product" :
                $sales_label = "تعداد پرداخت های بازه انتخابی ".$t;
            break;
            case "subscription" :
                $sales_label = "تعداد اشتراکهای بازه انتخابی ".$t;
            break;
        }
		$midt = $sales_today/$midd;
		$midt= number_format($midt, 3, '.', '')." در روز";
		$midt_label = "میانگین تعداد پرداخت های  ".$t."";
		$mid = GFCommon::to_money($revenue_today/$midd)." در روز";
		$mid_label = "میانگین پرداخت های  ".$t."";
		$revenue_today = GFCommon::to_money($revenue_today);
		$revenue_label = "جمع پرداخت های بازه انتخابی ".$t;
        return array("series" => $series, "options" => $options, "tooltips" => "[$tooltips]", "revenue_label" => $revenue_label, "revenue" => $revenue_today, "sales_label" => $sales_label, "sales" => $sales_today, "mid_label" => $mid_label, "mid" => $mid, "midt_label" => $midt_label, "midt" => $midt);
    }
	private static function edit_page(){
        ?>
        <style>
            #zarinpal_submit_container{clear:both;}
            .zarinpal_col_heading{padding-bottom:2px; border-bottom: 1px solid #ccc; font-weight:bold; width:120px; text-align:center;}
            .zarinpal_field_cell {padding: 6px 17px 0 0; margin-right:15px;}
			.zarinpal_validation_error{ background-color:#FFDFDF; margin-top:4px; margin-bottom:6px; padding-top:6px; padding-bottom:6px; border:1px dotted #C89797;}
            .zarinpal_validation_error span {color: red;}
            .left_header{float:left; width:200px;}
            .margin_vertical_10{margin: 10px 0; padding-left:5px;}
            .margin_vertical_30{margin: 30px 0; padding-left:5px;}
            .width-1{width:300px;}
            .gf_zarinpal_invalid_form,.gf_zarinpal_invalid_form1,.gf_zarinpal_invalid_form2{margin-top:30px; background-color:#FFEBE8;border:1px solid #CC0000; padding:10px; width:auto; max-width:600px;  margin: 23px auto 0 !important; text-align: center;}
			.js .postbox .hndle, .js .widget .widget-top {cursor: initial !important;margin: 0 !important;padding: 7px 16px !important;color:#0074A2 !important;border-top:1px solid #d54e21 !important;border-right:2px solid #D54E21 !important;font-size: 14px !important;}
			.postbox, .stuffbox {margin-bottom: 10px;}
			#zarinpal_customer_field_tozihat,#zarinpal_customer_field_mobile_tozihat ,#zarinpal_customer_field_email_tozihat {width:198px !important;}
			.updated {font-family: byekan !important;margin: auto !important; max-width: 668px !important;padding: 13px !important;}
			.updated * {font-family: byekan !important;}
        </style>
		<div class="wrap  gatewayset">
            <img alt="<?php _e("زرین پال", "gravityformszarinpal") ?>" style="margin: 15px 7px 0pt 0pt; float: left;" src="<?php echo self::get_base_url() ?>/static/zarinpal.png"/>
            <h2><?php _e("تنظیمات درگاه زرین پال برای فرم ها", "gravityformszarinpal") ?></h2>
        <?php
        $id = !empty($_POST["zarinpal_setting_id"]) ? $_POST["zarinpal_setting_id"] : absint($_GET["id"]);
        $config = empty($id) ? array("meta" => array(), "is_active" => true) : GFZarinPalData::get_feed($id);
        $is_validation_error = false;
        $config["form_id"] = rgpost("gf_zarinpal_submit") ? absint(rgpost("gf_zarinpal_form")) : rgar($config, "form_id");
        $form = isset($config["form_id"]) && $config["form_id"] ? $form = RGFormsModel::get_form_meta($config["form_id"]) : array();
		if(rgpost("gf_zarinpal_submit")){
            $config["meta"]["shaparak"] = rgpost("gf_zarinpal_shaparak");
            $config["meta"]["type"] = rgpost("gf_zarinpal_type");
            $config["meta"]["cancel_pm"] = rgpost("gf_zarinpal_cancel_pm");
            $config["meta"]["desc_pm"] = rgpost("gf_zarinpal_desc_pm");
			$config["meta"]["update_post_action1"] = rgpost('gf_zarinpal_update_action1');
			$config["meta"]["update_post_action2"] = rgpost('gf_zarinpal_update_action2');
            if(isset($form["notifications"])){
                $config["meta"]["delay_notifications"] = rgpost('gf_zarinpal_delay_notifications');
                $config["meta"]["selected_notifications"] = rgpost('gf_zarinpal_selected_notifications');
            }
            else{
                if(isset($config["meta"]["delay_notifications"]))
                    unset($config["meta"]["delay_notifications"]);
                if(isset($config["meta"]["selected_notifications"]))
                    unset($config["meta"]["selected_notifications"]);
            }
            $config["meta"]["zarinpal_conditional_enabled"] = rgpost('gf_zarinpal_conditional_enabled');
            $config["meta"]["zarinpal_conditional_field_id"] = rgpost('gf_zarinpal_conditional_field_id');
            $config["meta"]["zarinpal_conditional_operator"] = rgpost('gf_zarinpal_conditional_operator');
            $config["meta"]["zarinpal_conditional_value"] = rgpost('gf_zarinpal_conditional_value');
            $config["meta"]["recurring_amount_field"] = rgpost("gf_zarinpal_recurring_amount");
            //-----------------
			$customer_fields = self::get_customer_fields();
            $config["meta"]["customer_fields"] = array();
            foreach($customer_fields as $field){
                $config["meta"]["customer_fields"][$field["name"]] = $_POST["zarinpal_customer_field_{$field["name"]}"];
            }
			
			$customer_fields_email = self::get_customer_fields();
            $config["meta"]["customer_fields_email"] = array();
            foreach($customer_fields_email as $field){
                $config["meta"]["customer_fields_email"][$field["name"]] = $_POST["zarinpal_customer_field_email_{$field["name"]}"];
            }
			
			$customer_fields_mobile = self::get_customer_fields();
            $config["meta"]["customer_fields_mobile"] = array();
            foreach($customer_fields_mobile as $field){
                $config["meta"]["customer_fields_mobile"][$field["name"]] = $_POST["zarinpal_customer_field_mobile_{$field["name"]}"];
            }
            $config = apply_filters('gform_zarinpal_save_config', $config);
            $is_validation_error = apply_filters("gform_zarinpal_config_validation", false, $config);
            $id = GFZarinPalData::update_feed($id, $config["form_id"], $config["is_active"], $config["meta"]);
                ?>
                <div class="updated fade" style="padding:6px"><?php echo sprintf(__("فید به روز شد . %sبازگشت به لیست%s . ", "gravityformszarinpal"), "<a href='?page=gf_zarinpal'>", "</a>") ?>
				<span> یا </span>
				<a href="<?php echo 'admin.php?page=gf_edit_forms&view=settings&subview=confirmation&id=' . $form[id]; ?>" target="_blank">برای تنظیم متن و اقدامات تراکنش موفق کلیک نمایید</a>
				<span> . </span>
				</div>
                <?php
                $is_validation_error = true;
        }
        ?>
        <form method="post" action="" style="width:auto; max-width:700px; margin:auto; margin-top:20px;">		
<div id="normal-sortables" class="meta-box-sortables ui-sortable">
<div id="meta_gfdpspxpay_feed_form" class="postbox ">
<h3 class="hndle">
انتخاب فرم مورد نظر
</h3>
<div class="inside">
            <input type="hidden" name="zarinpal_setting_id" value="<?php echo $id ?>" />
            <div class="margin_vertical_10">
                <label for="gf_zarinpal_type"><?php _e("نوع پرداخت", "gravityformszarinpal"); ?> </label>
                <select id="gf_zarinpal_type" name="gf_zarinpal_type" onchange="SelectType(jQuery(this).val());" style="width:270px;">
                    <option value=""><?php _e("یک نوع برای پرداخت انتخاب نمایید", "gravityformszarinpal") ?></option>
                    <option value="product" <?php echo rgar($config['meta'], 'type') == "product" ? "selected='selected'" : "" ?>><?php _e("پرداخت معمولی", "gravityformszarinpal") ?></option>
                    <option value="subscription" <?php echo rgar($config['meta'], 'type') == "subscription" ? "selected='selected'" : "" ?>><?php _e("پرداخت هزینه عضویت یا ویرایش کاربری", "gravityformszarinpal") ?></option>
                </select>
            </div>
            <div id="zarinpal_form_container" valign="top" class="margin_vertical_10" <?php echo empty($config["meta"]["type"]) ? "style='display:none;'" : "" ?>>
                <label for="gf_zarinpal_form"><?php _e("انتخاب فرم&nbsp;", "gravityformszarinpal"); ?> </label>
                <select id="gf_zarinpal_form" name="gf_zarinpal_form" style="width:272px;" onchange="SelectForm(jQuery('#gf_zarinpal_type').val(), jQuery(this).val(), '<?php echo rgar($config, 'id') ?>');">
                    <option value=""><?php _e("یک فرم انتخاب نمایید", "gravityformszarinpal"); ?> </option>
                    <?php
                    $active_form = rgar($config, 'form_id');
                    $available_forms = GFZarinPalData::get_available_forms($active_form);
                    foreach($available_forms as $current_form) {
                        $selected = absint($current_form->id) == rgar($config, 'form_id') ? 'selected="selected"' : '';
                        ?>
                         <option value="<?php echo absint($current_form->id) ?>" <?php echo $selected; ?>><?php echo esc_html($current_form->title) ?></option>
                        <?php
                    }
                    ?>
                </select>
                &nbsp;&nbsp;
                <img src="<?php echo GFZarinPal::get_base_url() ?>/static/loading.gif" id="zarinpal_wait" style="display: none;"/>
                <div id="gf_zarinpal_invalid_product_form" class="gf_zarinpal_invalid_form"  style="display:none;">
                    <?php _e("این فرم انتخاب شده شما ، هیچ فیلد مرتبط با محصول و مبلغ ندارد ، لطفا پس از افزودن فیلد مجددا انتخاب نمایید .", "gravityformszarinpal") ?>
                </div>
				   <div id="gf_zarinpal_invalid_product_form1" class="gf_zarinpal_invalid_form1"  style="display:none;">
                    <?php _e("این فرم انتخاب شده شما ، هیچ فیلد ایمیلی ندارد ، لطفا پس از افزودن فیلد مجددا انتخاب نمایید .", "gravityformszarinpal") ?>
                </div>
				 <div id="gf_zarinpal_invalid_product_form2" class="gf_zarinpal_invalid_form2"  style="display:none;">
                    <?php _e("تذکر : برای اینکه عملیات ثبت نام به درستی عمل نماید ، باید این فرم را قبل یا بعد از تکمیل این قسمت به افزودنی ثبت نام هم اضافه نمایید . این افزودنی را میتوانید از قسمت فرم ها ، افزودنی ها فعال نمایید . ", "gravityformszarinpal") ?>
                </div>
			</div>
			</div></div></div>
            <div id="zarinpal_field_group" valign="top" <?php echo empty($config["meta"]["type"]) || empty($config["form_id"]) ? "style='display:none;'" : "" ?>>
			<div id="normal-sortables" class="meta-box-sortables ui-sortable">
			<div id="meta_gfdpspxpay_feed_form" class="postbox ">
			<h3 class="hndle">
			توضیحات زرین پال
			</h3>
			<div class="inside">
			<div class="margin_vertical_10">

                        <div id="zarinpal_customer_fields">
                        <?php
                            if(!empty($form))
                                echo self::get_customer_information($form, $config);
                        ?>
				  </div> <br/>
					<label for="gf_zarinpal_desc_pm">اضافه کردن دستی توضیح</label>
						<input type="text" name="gf_zarinpal_desc_pm" id="gf_zarinpal_desc_pm" class="width-1" value="<?php echo rgars($config, "meta/desc_pm") ?>" style="max-width:198px !important;"/>
                </div>
				</div></div></div>
				
				
			<div id="normal-sortables" class="meta-box-sortables ui-sortable">
			<div id="meta_gfdpspxpay_feed_form" class="postbox ">
			<h3 class="hndle">
			جزییات خریدار
			</h3>
			<div class="inside">
			<p>این قسمت اختیاری بوده و نیازی به تنظیم کردن هر قسمت نمی باشد . این بخش مربوط به اطلاعات پرداخت کننده است که در سایت زرین پال داخل جزییات هر تراکنش قابل مشاهده است .</p>
			<p>تذکر : لطفا فیلد مربوط به هر قسمت را متناسب انتخاب نمایید . بعنوان مثال ؛ قسمت ایمیل را حتما با فیلد ایمیل ست نمایید و ....</p>
		

				 <div class="margin_vertical_10">
                        <div class="zarinpal_customer_fields_email">
                        <?php
                          if(!empty($form)) echo self::get_customer_information_email($form, $config);
                        ?>
                    </div>
				 </div>
				 <div class="margin_vertical_10">
                        <div class="zarinpal_customer_fields_mobile">
                        <?php
                              if(!empty($form))  echo self::get_customer_information_mobile($form, $config);
                        ?>
                    </div>
				 </div>
			<br>	 
			
			<p>فیلد تلفن همراه ، مربوط به شماره موبایلی است که در پنل سایت زرین پال ذخیره میشود و با شماره مربوط به افزونه پیامکی فرم متفاوت است .</p>
			</div></div></div>
				
				
			<div id="normal-sortables" class="meta-box-sortables ui-sortable">
			<div id="meta_gfdpspxpay_feed_form" class="postbox "><h3 class="hndle">تنظیمات تاییدیه و انصرف</h3><div class="inside">
                <div class="margin_vertical_10">
                    <label for="gf_zarinpal_cancel_pm"><?php _e("پیغام انصراف کاربر از صفحه پرداخت : (درصورتی که خالی بگذارید پیغام « تراکنش به دلیل انصراف کاربر ناتمام باقی ماند » به کاربر نمایش داده میشود)", "gravityformszarinpal"); ?>
					</label>
				<br>
                    <input type="text" name="gf_zarinpal_cancel_pm" id="gf_zarinpal_cancel_pm" class="width-1" value="<?php echo rgars($config, "meta/cancel_pm") ?>" style="max-width:100% !important; width:330px;"/>
                    <p>برای تعیین متن و اقدامات پس از تراکنش موفق به تنظیمات فرم ، قسمت تاییدیه ها مراجعه فرمایید .
					</p>
                </div>
				</div></div></div>
				<?php  $display_post_fields = !empty($form) ? GFCommon::has_post_field($form["fields"]) : false;	?>
				<div id="zarinpal_post_action"   class="meta-box-sortables ui-sortable postfield" <?php echo $display_post_fields ? "" : "style='display:none;'" ?>>
				<div id="meta_gfdpspxpay_feed_form" class="postbox "><h3 class="hndle">تنظیمات مربوط به فرم های ارسال پست</h3><div class="inside">
                <div class="margin_vertical_10">
						  <p>وضعیت پست پس از پرداخت موفق (تذکر : در صورتی که میخواهید از همان تنظیمات هنگام ساخت فرم استفاده شود ، گزینه "تنظیمات فرم" را انتخاب نمایید) :</p>
							<select id="gf_zarinpal_update_action1" name="gf_zarinpal_update_action1">
							<option value="">تنظیمات فرم</option>
                                <option value="publish" <?php echo rgar($config["meta"],"update_post_action1") == "publish" ? "selected='selected'" : ""?>><?php _e("منشتر شده", "gravityformszarinpal") ?></option>
								<option value="draft" <?php echo rgar($config["meta"],"update_post_action1") == "draft" ? "selected='selected'" : ""?>><?php _e("پیشنویس", "gravityformszarinpal") ?></option>
                                <option value="pending" <?php echo rgar($config["meta"],"update_post_action1") == "pending" ? "selected='selected'" : ""?>><?php _e("در انتظار بررسی", "gravityformszarinpal") ?></option>
								<option value="private" <?php echo rgar($config["meta"],"update_post_action1") == "private" ? "selected='selected'" : ""?>><?php _e("خصوصی", "gravityformszarinpal") ?></option>
                            </select>
							 <br>
							<p>وضعیت پست در صورت عدم تراکنش موفق (تذکر : در صورتی که میخواهید فقط در صورت تراکنش موفق پست ایجاد شود ، گزینه "عدم ایجاد پست" ایجاد پست را انتخاب نمایید) : </p>
							<select id="gf_zarinpal_update_action2" name="gf_zarinpal_update_action2">
							<option value="">عدم ایجاد پست</option>
                                <option value="publish" <?php echo rgar($config["meta"],"update_post_action2") == "publish" ? "selected='selected'" : ""?>><?php _e("منشتر شده", "gravityformszarinpal") ?></option>
								<option value="draft" <?php echo rgar($config["meta"],"update_post_action2") == "draft" ? "selected='selected'" : ""?>><?php _e("پیشنویس", "gravityformszarinpal") ?></option>
                                <option value="pending" <?php echo rgar($config["meta"],"update_post_action2") == "pending" ? "selected='selected'" : ""?>><?php _e("در انتظار بررسی", "gravityformszarinpal") ?></option>
								<option value="private" <?php echo rgar($config["meta"],"update_post_action2") == "private" ? "selected='selected'" : ""?>><?php _e("خصوصی", "gravityformszarinpal") ?></option>
                            </select>
							 <br>
						<p>تذکر : تنظیم قسمت های بالا ضروری نیست ولی در صورتی که بخواهید به ازای منطق شرطی درگاه ، یک وضعیت خاص برای هر پست در نظر بگیرید میتوانید از گزینه های بالا استفاده نمایید .</p>
                        </div>
			</div></div></div>
			<?php do_action("gform_zarinpal_action_fields", $config, $form) ?>
            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
			<div id="meta_gfdpspxpay_feed_form" class="postbox ">
			<h3 class="hndle">تنظیمات اعلان ها و ایمیل</h3><div class="inside">
            <div class="margin_vertical_10" id="gf_zarinpal_notifications" <?php echo !isset($form["notifications"]) ? "style='display:none;'" : "" ?>>
                    <?php
                    $has_delayed_notifications = rgar($config['meta'], 'delay_notifications');
                    ?>
                    <div style="overflow:hidden;">
                        <input type="checkbox" name="gf_zarinpal_delay_notifications" id="gf_zarinpal_delay_notifications" value="1" <?php checked("1", $has_delayed_notifications)?> />
                        <label class="inline" for="gf_zarinpal_delay_notifications"><?php _e("ارسال ایمیل فقط در صورت تراکنش موفق ", "gravityformszarinpal"); ?></label>
						<p>تذکر : در صورتی که تیک بالا را نزنید در صورت تراکنش ناموفق و انصرافی هم ایمیل ارسال خواهد شد که میتوانید از تگ های زیر برای نمایش وضعیت تراکنش در متن ایمیل های ارسالی استفاده نمایید .</p>
						<hr>
						<label style="font-size:18px !important;"> تگهای مورد استفاده در ویرایشگر متن ایمیل یا صفحه تراکنش موفق :</label><br><br>
						<span> نام درگاه پرداخت (ساده) :  </span><span>{payment_gateway}</span>
						<br>
						<span> نام درگاه پرداخت استایل بندی شده :  </span><span>{payment_gateway_css}</span>
						<br>
						<span> کد رهگیری (ساده) :  </span><span>{transaction_id}</span>
						<br>
						<span> کد رهگیری استایل بندی شده :  </span><span>{transaction_id_css}</span>
						<br>
						<span> وضعیت پرداخت (ساده) :  </span><span>{payment_status}</span>
						<br>
						<span> وضعیت پرداخت استایل بندی شده :  </span><span>{payment_status_css}</span>
						<br>
						<span> اطلاعات پرداخت استایل بندی شده (مجموعه نام درگاه و وضعیت و کد رهگیری ) :  </span><span>{payment_pack}</span>
						<br>
                    </div>
                </div></div></div></div>
				 <div id="normal-sortables" class="meta-box-sortables ui-sortable">
				<div id="meta_gfdpspxpay_feed_form" class="postbox ">
				<h3 class="hndle">تنظیمات مربوط به سیستم شاپرک</h3><div class="inside">
				<div class="margin_vertical_10">		
                <p><?php _e("در صورتی که مبلغ کل (با اعمال کپن تخفیف و ... ) بین 0 تا 100 تومان باشد ، با توجه به محدودهای شاپرک که قیمت پرداختی نمیتواند زیر 100 تومان باشید یکی از گزینه های زیر را انتخاب نمایید . ", "gravityformszarinpal"); ?></p>
				<input type="radio" name="gf_zarinpal_shaparak" id="gf_zarinpal_shaparak_raygan" value="raygan" <?php echo rgar($config['meta'], 'shaparak') == "raygan" ? "checked='checked'" : "" ?>/>
                <label class="inline" for="gf_zarinpal_shaparak_raygan"><?php _e("تراکنش رایگان شود", "gravityformszarinpal"); ?></label>
            &nbsp;&nbsp;&nbsp;
			<input type="radio" name="gf_zarinpal_shaparak" id="gf_zarinpal_shaparak_sadt" value="sadt" <?php echo rgar($config['meta'], 'shaparak') != "raygan" ? "checked='checked'" : "" ?>/>
                <label class="inline" for="gf_zarinpal_shaparak_sadt"><?php _e("مبلغ تراکنش 100 تومان شود", "gravityformszarinpal"); ?></label>
                <p>تذکر : برای تفکیک تراکنش های مبلغ دار از تراکنش های رایگان ، کد رهگیری تراکنش های رایگان 15 رقمی تعریف شده اند .</p>
                </div>
				</div></div></div>
                <?php do_action("gform_zarinpal_add_option_group", $config, $form); ?>
				<div id="normal-sortables" class="meta-box-sortables ui-sortable">
				<div id="meta_gfdpspxpay_feed_form" class="postbox ">
				<h3 class="hndle">منطق شرطی درگاه</h3><div class="inside">
				<div id="gf_zarinpal_conditional_option">
                        <table cellspacing="0" cellpadding="0">
                            <tr>
                                <td>
                                    <input type="checkbox" id="gf_zarinpal_conditional_enabled" name="gf_zarinpal_conditional_enabled" value="1" onclick="if(this.checked){jQuery('#gf_zarinpal_conditional_container').fadeIn('fast');} else{ jQuery('#gf_zarinpal_conditional_container').fadeOut('fast'); }" <?php echo rgar($config['meta'], 'zarinpal_conditional_enabled') ? "checked='checked'" : ""?>/>
                                    <label for="gf_zarinpal_conditional_enable"><?php _e("این درگاه را فقط وقتی فعال کن که شرط زیر برقرار باشد . ", "gravityformszarinpal"); ?></label><br/>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div id="gf_zarinpal_conditional_container" <?php echo !rgar($config['meta'], 'zarinpal_conditional_enabled') ? "style='display:none'" : ""?>>
										
                                        <div id="gf_zarinpal_conditional_fields" style="display:none">
										<br>
                                            <label><?php _e("فعالسازی در صورتی که : ", "gravityformszarinpal") ?></label>
                                            <select id="gf_zarinpal_conditional_field_id" name="gf_zarinpal_conditional_field_id" class="optin_select" onchange='jQuery("#gf_zarinpal_conditional_value_container").html(GetFieldValues(jQuery(this).val(), "", 20));'>
                                            </select>
                                            <select id="gf_zarinpal_conditional_operator" name="gf_zarinpal_conditional_operator">
                                                <option value="is" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "is" ? "selected='selected'" : "" ?>><?php _e("هست", "gravityformszarinpal") ?></option>
                                                <option value="isnot" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "isnot" ? "selected='selected'" : "" ?>><?php _e("نیست", "gravityformszarinpal") ?></option>
                                                <option value=">" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == ">" ? "selected='selected'" : "" ?>><?php _e("بزرگ تر است از", "gravityformszarinpal") ?></option>
                                                <option value="<" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "<" ? "selected='selected'" : "" ?>><?php _e("کوچک تر است از", "gravityformszarinpal") ?></option>
                                                <option value="contains" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "contains" ? "selected='selected'" : "" ?>><?php _e("شامل میشود ", "gravityformszarinpal") ?></option>
                                                <option value="starts_with" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "starts_with" ? "selected='selected'" : "" ?>><?php _e("شروع میشود با", "gravityformszarinpal") ?></option>
                                                <option value="ends_with" <?php echo rgar($config['meta'], 'zarinpal_conditional_operator') == "ends_with" ? "selected='selected'" : "" ?>><?php _e("تمام میشود با", "gravityformszarinpal") ?></option>
                                            </select>
                                            <div id="gf_zarinpal_conditional_value_container" name="gf_zarinpal_conditional_value_container" style="display:inline;"></div>
                                        </div>
                                        <div id="gf_zarinpal_conditional_message" style="display:none">
                                            <?php _e("برای قرار دادن منطق شرطی ، باید فیلدهای فرم شما هم قابلیت منطق شرطی را داشته باشند . ", "gravityform") ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
						 <p>تذکر : در صورتی که از منطق شرطی استفاده میکنید ، تنظیمات شاپرک را حتما برروی گزینه رایگان شود قرار دهید .</p>
                    </div>
                </div> </div> </div>
				<div id="zarinpal_submit_container" class="margin_vertical_30">
                    <input type="submit" name="gf_zarinpal_submit" value="<?php echo empty($id) ? __("  ذخیره  ", "gravityformszarinpal") : __("بروز رسانی", "gravityformszarinpal"); ?>" class="button-primary button"/>
                    <input type="button" value="<?php _e("انصراف", "gravityformszarinpal"); ?>" class="button" onclick="javascript:document.location='admin.php?page=gf_zarinpal'" />
                </div>
            </div>
        </form>
        </div>
        <script type="text/javascript">
            function SelectType(type){
                jQuery("#zarinpal_field_group").slideUp();
                jQuery("#zarinpal_field_group input[type=\"text\"], #zarinpal_field_group select").val("");
                jQuery("#zarinpal_field_group input:checked").attr("checked", false);
                if(type){
                    jQuery("#zarinpal_form_container").slideDown();
                    jQuery("#gf_zarinpal_form").val("");
                }
                else{
                    jQuery("#zarinpal_form_container").slideUp();
                }
            }
            function SelectForm(type, formId, settingId){
                if(!formId){
                    jQuery("#zarinpal_field_group").slideUp();
                    return;
                }
                jQuery("#zarinpal_wait").show();
                jQuery("#zarinpal_field_group").slideUp();
                var mysack = new sack(ajaxurl);
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar( "action", "gf_select_zarinpal_form" );
                mysack.setVar( "gf_select_zarinpal_form", "<?php echo wp_create_nonce("gf_select_zarinpal_form") ?>" );
                mysack.setVar( "type", type);
                mysack.setVar( "form_id", formId);
                mysack.setVar( "setting_id", settingId);
                mysack.onError = function() {jQuery("#zarinpal_wait").hide(); alert('<?php _e("Ajax error while selecting a form", "gravityformszarinpal") ?>' )};
                mysack.runAJAX();
                return true;
            }
            function EndSelectForm(form_meta, customer_fields, customer_fields_email, customer_fields_mobile){
				form = form_meta;
                var type = jQuery("#gf_zarinpal_type").val();
                jQuery(".gf_zarinpal_invalid_form").hide();
                jQuery(".gf_zarinpal_invalid_form1").hide();
                jQuery(".gf_zarinpal_invalid_form2").hide();
                if( (type == "product" || type =="subscription") && GetFieldsByType(["product"]).length == 0){
                    jQuery("#gf_zarinpal_invalid_product_form").show();
                    jQuery("#zarinpal_wait").hide();
                    return;
                }
				else if( type =="subscription" && GetFieldsByType(["email"]).length == 0){
                    jQuery("#gf_zarinpal_invalid_product_form1").show();
                    jQuery("#zarinpal_wait").hide();
                    return;
                }
				if( type =="subscription"){
                 jQuery("#gf_zarinpal_invalid_product_form2").show();
                }
                jQuery(".zarinpal_field_container").hide();
                jQuery("#zarinpal_customer_fields").html(customer_fields);
                jQuery(".zarinpal_customer_fields_email").html(customer_fields_email);
                jQuery(".zarinpal_customer_fields_mobile").html(customer_fields_mobile);
				var post_fields = GetFieldsByType(["post_title", "post_content", "post_excerpt", "post_category", "post_custom_field", "post_image", "post_tag"]);
                if(post_fields.length > 0){
                    jQuery("#zarinpal_post_action").show();
                }
                else{
                    jQuery("#gf_zarinpal_delay_post").attr("checked", false);
                    jQuery("#zarinpal_post_action").hide();
                }
                if(type == "subscription" && post_fields.length > 0){
                    jQuery("#zarinpal_post_update_action").show();
                }
                else{
                    jQuery("#gf_zarinpal_update_post").attr("checked", false);
                    jQuery("#zarinpal_post_update_action").hide();
                }
				jQuery(document).trigger('zarinpalFormSelected', [form]);
                jQuery("#gf_zarinpal_conditional_enabled").attr('checked', false);
                SetZarinPalCondition("","");
                if(form["notifications"]){
				
                    jQuery("#gf_zarinpal_notifications").show();
                    jQuery("#zarinpal_delay_notification").hide();
                }
                else{
                    jQuery("#zarinpal_delay_notification").show();
                    jQuery("#gf_zarinpal_notifications").hide();
                }
                jQuery("#zarinpal_field_container_" + type).show();
                jQuery("#zarinpal_field_group").slideDown();
                jQuery("#zarinpal_wait").hide();
            }
            function GetFieldsByType(types){
                var fields = new Array();
                for(var i=0; i<form["fields"].length; i++){
                    if(IndexOf(types, form["fields"][i]["type"]) >= 0)
                        fields.push(form["fields"][i]);
                }
                return fields;
            }
            function IndexOf(ary, item){
                for(var i=0; i<ary.length; i++)
                    if(ary[i] == item)
                        return i;
                return -1;
            }
			<?php
            if(!empty($config["form_id"])){
                ?>
				form = <?php echo GFCommon::json_encode($form)?> ;
				jQuery(document).ready(function(){
                    var selectedField = "<?php echo str_replace('"', '\"', $config["meta"]["zarinpal_conditional_field_id"])?>";
                    var selectedValue = "<?php echo str_replace('"', '\"', $config["meta"]["zarinpal_conditional_value"])?>";
                    SetZarinPalCondition(selectedField, selectedValue);
                });
                <?php
            }
            ?>
            function SetZarinPalCondition(selectedField, selectedValue){
                jQuery("#gf_zarinpal_conditional_field_id").html(GetSelectableFields(selectedField, 20));
                var optinConditionField = jQuery("#gf_zarinpal_conditional_field_id").val();
                var checked = jQuery("#gf_zarinpal_conditional_enabled").attr('checked');
                if(optinConditionField){
                    jQuery("#gf_zarinpal_conditional_message").hide();
                    jQuery("#gf_zarinpal_conditional_fields").show();
                    jQuery("#gf_zarinpal_conditional_value_container").html(GetFieldValues(optinConditionField, selectedValue, 20));
                    jQuery("#gf_zarinpal_conditional_value").val(selectedValue);
                }
                else{
                    jQuery("#gf_zarinpal_conditional_message").show();
                    jQuery("#gf_zarinpal_conditional_fields").hide();
                }
                if(!checked) jQuery("#gf_zarinpal_conditional_container").hide();

            }
            function GetFieldValues(fieldId, selectedValue, labelMaxCharacters){
                if(!fieldId)
                    return "";
                var str = "";
                var field = GetFieldById(fieldId);
                if(!field)
                    return "";
                var isAnySelected = false;
                if(field["type"] == "post_category" && field["displayAllCategories"]){
					str += '<?php $dd = wp_dropdown_categories(array("class"=>"optin_select", "orderby"=> "name", "id"=> "gf_zarinpal_conditional_value", "name"=> "gf_zarinpal_conditional_value", "hierarchical"=>true, "hide_empty"=>0, "echo"=>false)); echo str_replace("\n","", str_replace("'","\\'",$dd)); ?>';
				}
				else if(field.choices){
					str += '<select id="gf_zarinpal_conditional_value" name="gf_zarinpal_conditional_value" class="optin_select">'
	                for(var i=0; i<field.choices.length; i++){
	                    var fieldValue = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
	                    var isSelected = fieldValue == selectedValue;
	                    var selected = isSelected ? "selected='selected'" : "";
	                    if(isSelected)
	                        isAnySelected = true;
	                    str += "<option value='" + fieldValue.replace(/'/g, "&#039;") + "' " + selected + ">" + TruncateMiddle(field.choices[i].text, labelMaxCharacters) + "</option>";
	                }
	                if(!isAnySelected && selectedValue){
	                    str += "<option value='" + selectedValue.replace(/'/g, "&#039;") + "' selected='selected'>" + TruncateMiddle(selectedValue, labelMaxCharacters) + "</option>";
	                }
	                str += "</select>";
				}
				else
				{
					selectedValue = selectedValue ? selectedValue.replace(/'/g, "&#039;") : "";
					str += "<input type='text' placeholder='<?php _e("یک مقدار وارد نمایید", "gravityformszarinpal"); ?>' id='gf_zarinpal_conditional_value' name='gf_zarinpal_conditional_value' value='" + selectedValue.replace(/'/g, "&#039;") + "'>";
				}
                return str;
            }
            function GetFieldById(fieldId){
                for(var i=0; i<form.fields.length; i++){
                    if(form.fields[i].id == fieldId)
                        return form.fields[i];
                }
                return null;
            }
            function TruncateMiddle(text, maxCharacters){
                if(!text)
                    return "";
                if(text.length <= maxCharacters)
                    return text;
                var middle = parseInt(maxCharacters / 2);
                return text.substr(0, middle) + "..." + text.substr(text.length - middle, middle);
            }
            function GetSelectableFields(selectedFieldId, labelMaxCharacters){
                var str = "";
                var inputType;
                for(var i=0; i<form.fields.length; i++){
                    fieldLabel = form.fields[i].adminLabel ? form.fields[i].adminLabel : form.fields[i].label;
                    inputType = form.fields[i].inputType ? form.fields[i].inputType : form.fields[i].type;
                    if (IsConditionalLogicField(form.fields[i])) {
                        var selected = form.fields[i].id == selectedFieldId ? "selected='selected'" : "";
                        str += "<option value='" + form.fields[i].id + "' " + selected + ">" + TruncateMiddle(fieldLabel, labelMaxCharacters) + "</option>";
                    }
                }
                return str;
            }
            function IsConditionalLogicField(field){
			    inputType = field.inputType ? field.inputType : field.type;
			    var supported_fields = ["checkbox", "radio", "select", "text", "website", "textarea", "email", "hidden", "number", "phone", "multiselect", "post_title",
			                            "post_tags", "post_custom_field", "post_content", "post_excerpt"];
			    var index = jQuery.inArray(inputType, supported_fields);
			    return index >= 0;
			}
        </script>
        <?php
    }
    public static function select_zarinpal_form_By_HANNANStd(){
        check_ajax_referer("gf_select_zarinpal_form", "gf_select_zarinpal_form");
        $type = $_POST["type"];
        $form_id =  intval($_POST["form_id"]);
        $setting_id =  intval($_POST["setting_id"]);
        $form = RGFormsModel::get_form_meta($form_id);
        $customer_fields = self::get_customer_information($form);
        $customer_fields_email = self::get_customer_information_email($form);
        $customer_fields_mobile = self::get_customer_information_mobile($form);
        die("EndSelectForm(" . GFCommon::json_encode($form) . ", '" . str_replace("'", "\'", $customer_fields) . "', '" . str_replace("'", "\'", $customer_fields_email) . "', '" . str_replace("'", "\'", $customer_fields_mobile) . "');");
    }
    public static function add_permissions(){
        global $wp_roles;
        $wp_roles->add_cap("administrator", "gravityforms_zarinpal");
        $wp_roles->add_cap("administrator", "gravityforms_zarinpal_uninstall");
    }
	public static function members_get_capabilities( $caps ) {
        return array_merge($caps, array("gravityforms_zarinpal", "gravityforms_zarinpal_uninstall"));
    }
    public static function get_active_config($form){
        $configs = GFZarinPalData::get_feed_by_form($form["id"], true);
        if(!$configs)
            return false;
        foreach($configs as $config){
            if(self::has_zarinpal_condition($form, $config))
                return $config;
        }
        return false;
    }

	public static function shaparak_ing_By_HANNANStd($form, $is_ajax){

	$on_off = true;
    $config = GFZarinPalData::get_feed_by_form($form["id"]);
    if(!$config)
        $on_off = false;
    $config = $config[0];
    if(!self::has_zarinpal_condition($form, $config))
         $on_off = false;
    
	if ($on_off == true )
	{
	$config = self::get_active_config($form);
	$currency = GFCommon::get_currency();
	if ($currency == 'IRR' || $currency == 'IRT') {
	?>
	<script type="text/javascript">
	gform.addFilter( 'gform_product_total', function(total, formId){
	<?php if ($currency == 'IRR') { ?>
	if((total < 1000) && (total > 0)) 
	<?php } 
	if ($currency == 'IRT') { ?>
	if((total < 100) && (total > 0)) 
	<?php } ?>
	{
	<?php if ($config["meta"]["shaparak"] == "sadt") { 
	if ($currency == 'IRT') {  ?>
	return 100;
	<?php } if ($currency == 'IRR') { ?>
	return 1000;
	<?php } } else { ?>
	return 0;
	<?php } ?>
	}  
	else {
	if (total < 0)
	return 0;
	return total;
	}
	}
	);
	</script>
	<?php
	}
	}
	else
		return false;
	}
	public static function send_to_zarinpal_By_HANNANStd($confirmation, $form, $entry, $ajax){
		if(RGForms::post("gform_submit") != $form["id"])
        {
            return $confirmation;
		}
        $config = self::get_active_config($form);
        if(!$config)
        {	
            self::log_debug("NOT sending to ZarinPal: No ZarinPal setup was located for form_id = {$form['id']}.");
            return $confirmation;
		}
		unset($entry["payment_status"]);
        unset($entry["payment_amount"]);
        unset($entry["payment_date"]);
        unset($entry["transaction_id"]);
        unset($entry["transaction_type"]);
		unset($entry["is_fulfilled"]);
        RGFormsModel::update_lead($entry);
		self:: delay_addons_By_HANNANStd($form);
		gform_update_meta($entry["id"], "zarinpal_feed_id", $config["id"]);
        gform_update_meta($entry["id"], "payment_gateway", self::get_gname());
		RGFormsModel::update_lead_property($entry["id"], "payment_method", "zarinpal");
        RGFormsModel::update_lead_property($entry["id"], "payment_status", 'Processing');
		RGFormsModel::update_lead_property($entry["id"], "is_fulfilled", 0);
		switch($config["meta"]["type"]){
            case "product" :   
			RGFormsModel::update_lead_property($entry["id"], "transaction_type", 1);	
            break;
			case "subscription" :
		    RGFormsModel::update_lead_property($entry["id"], "transaction_type", 2);	
            break;
        }	
	if (GFCommon::has_post_field($form["fields"])) {
	if($config["meta"]["update_post_action2"] != ""){
	self::log_debug("Creating post.");
	RGFormsModel::create_post($form, $entry);
	$post = get_post($entry["post_id"]);
			switch($config["meta"]["update_post_action2"]){
                    case "publish" :
					$post->post_status = 'publish';
					wp_update_post($post);
					break;
					case "draft" :
					$post->post_status = 'draft';
					wp_update_post($post);
					break;
					case "pending" :
					$post->post_status = 'pending';
					wp_update_post($post);
					break;
					case "private" :
					$post->post_status = 'private';
					wp_update_post($post);
					break;		
			}}}	
		if (rgpost('input_'. str_replace(".", "_",$config["meta"]["customer_fields"]["tozihat"])))
		$Desc1 = " | ".rgpost('input_'. str_replace(".", "_",$config["meta"]["customer_fields"]["tozihat"]));
		else $Desc1 = "";
		if ($config["meta"]["desc_pm"])
		$Desc2 = " | ".$config["meta"]["desc_pm"];
		else $Desc2 ="";
		$Description = $Desc1."".$Desc2;
		$query_string = "";
        $query_string = self::get_product_query_string($form, $entry);
		$query_strings = apply_filters("gform_zarinpal_query_{$form['id']}", apply_filters("gform_zarinpal_query", $query_string, $form, $entry), $form, $entry);
		if(!$query_strings || $query_strings==0) return array("redirect" =>"".self::return_url($form["id"], $entry["id"]).""); 
		else {
        
		
		
		$MerchantID = self::get_merchent();  //Required
	    $Amount = $query_string;
	    $currency = GFCommon::get_currency();  
		if ($currency == 'IRR'){$Amount = $Amount/10;}
		$CallbackURL = self::return_url($form["id"], $entry["id"]);

	$Description = "فرم " . $form["id"] . "|پیام " . $entry["id"] . "" . $Description . "";

    
	if (rgpost('input_'. str_replace(".", "_",$config["meta"]["customer_fields_email"]["tozihat"])))
	$Email = rgpost('input_'. str_replace(".", "_",$config["meta"]["customer_fields_email"]["tozihat"]));
	else 
	$Email = '-';
		
	if (rgpost('input_'. str_replace(".", "_",$config["meta"]["customer_fields_mobile"]["tozihat"])))
	$Mobile = rgpost('input_'. str_replace(".", "_",$config["meta"]["customer_fields_mobile"]["tozihat"]));
	else 
	$Mobile ='-';
	
	$server = self::get_server();
	if ($server == "German")
	{
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8')); 
	}
	else {
	$client = new SoapClient('https://ir.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8')); 
	}
	$result = $client->PaymentRequest(
						array(
								'MerchantID' 	=> $MerchantID,
								'Amount' 		=> $Amount,
								'Description' 	=> $Description,
								'Email' 		=> $Email,
								'Mobile' 		=> $Mobile,
								'CallbackURL' 	=> $CallbackURL
							)
	);
	if($result->Status == 100)
	{
	$payurl = 'https://www.zarinpal.com/pg/StartPay/'.$result->Authority;
	echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" . $payurl . "'; };</script>";
	} else {
		global $current_user;
		$user_id = 0;
		$user_name = "مهمان";
		if($current_user && $user_data = get_userdata($current_user->ID)){
        $user_id = $current_user->ID;
        $user_name = $user_data->display_name;}
		RGFormsModel::update_lead_property($entry["id"], "payment_status", 'Failed');
	    RGFormsModel::add_note($entry["id"], $user_id, $user_name, sprintf(__("وضعیت پرداخت : ناموفق - خطای تراکنش : %s - یعنی : %s", "gravityformszarinpal"), $result->Status, self::khata_nama($result->Status)));
		$form_string = "";
        $validation_message = "<div class='validation_error'>" . self:: khata_nama($result->Status) . "</div>";
        $form_string .= apply_filters("gform_validation_message_{$form["id"]}", apply_filters("gform_validation_message", $validation_message, $form), $form);
        return apply_filters("gform_get_form_filter_{$form["id"]}",apply_filters('gform_get_form_filter',$form_string, $form), $form);
	}
  }
  }
 public static function gf_create_user_By_HANNANStd($entry, $form, $fulfilled = false) {
		if(!class_exists("GFUser"))
    	return;
		GFUser::log_debug( "form #{$form['id']} - starting gf_create_user_By_HANNANStd()." );
        global $wpdb;
		if(rgar($entry, 'status') == 'spam') {
	        GFUser::log_debug( 'gf_create_user_By_HANNANStd(): aborting. Entry is marked as spam.' );
	        return;
        }
        $config = GFUser::get_active_config($form, $entry);
        $is_update_feed = rgars($config, 'meta/feed_type') == 'update';
		if(!$config || !$config['is_active']) {
	        GFUser::log_debug( 'gf_create_user_By_HANNANStd(): aborting. No feed or feed is inactive.' );
	        return;
        }
        $user_data = GFUser::get_user_data($entry, $form, $config, $is_update_feed);
        if(!$user_data) {
	        GFUser::log_debug( 'gf_create_user_By_HANNANStd(): aborting. user_login or user_email are empty.' );
	        return;
        }
        $user_activation = rgars($config, 'meta/user_activation');
		if(!$is_update_feed && $user_activation && !$fulfilled || (!$is_update_feed && $user_activation && $fulfilled) ) {
			require_once(GFUser::get_base_path() . '/includes/signups.php');
            GFUserSignups::prep_signups_functionality();
            $meta = array(
                'lead_id'    => $entry['id'],
                'user_login' => $user_data['user_login'],
                'email'      => $user_data['user_email'],
				'password'	 => GFUser::encrypt( $user_data['password'] ),
            );
			$meta = apply_filters( 'gform_user_registration_signup_meta',               $meta, $form, $entry, $config );
            $meta = apply_filters( "gform_user_registration_signup_meta_{$form['id']}", $meta, $form, $entry, $config );
			$ms_options = rgars($config, 'meta/multisite_options');
			if(is_multisite() && $ms_options['create_site'] && $site_data = GFUser::get_site_data($entry, $form, $config)) {
                wpmu_signup_blog($site_data['domain'], $site_data['path'], $site_data['title'], $user_data['user_login'], $user_data['user_email'], $meta);
            } else {
                $user_data['user_login'] = preg_replace( '/\s+/', '', sanitize_user( $user_data['user_login'], true ) );
                GFUser::log_debug("Calling wpmu_signup_user (sends email with activation link) with login: " . $user_data['user_login'] . " email: " . $user_data['user_email'] . " meta: " . print_r($meta, true));
                wpmu_signup_user($user_data['user_login'], $user_data['user_email'], $meta);
                GFUser::log_debug("Done with wpmu_signup_user");
            }
			$activation_key = $wpdb->get_var($wpdb->prepare("SELECT activation_key FROM $wpdb->signups WHERE user_login = %s ORDER BY registered DESC LIMIT 1", $user_data['user_login']));
			GFUserSignups::add_signup_meta($entry['id'], $activation_key);
			return;
        }
        if($is_update_feed) {
            GFUser::update_user($entry, $form, $config);
        } else {
        	if (!$user_activation){
        		GFUser::log_debug("in gf_create_user - calling create_user");
            	GFUser::create_user($entry, $form, $config);
			}
        }
    }
	private static function get_merchent(){
        $settings = get_option("gf_zarinpal_settings");
        $merchent = $settings["merchent"];
        return $merchent;
    }
    private static function get_server(){
        $settings = get_option("gf_zarinpal_settings");
        $server = $settings["server"];
        return $server;
    }
	private static function get_gname(){
        $settings = get_option("gf_zarinpal_settings");
		if($settings["gname"])
        $gname = $settings["gname"];
		else
		$gname = "زرین پال";
        return $gname;
    }
 private static function khata_nama($err_code){
 $message = " ";
 switch($err_code){
                                                case "-1" :
												$message = "اطلاعات ارسال شده ناقص است .";
                                                break;

                                                case "-2" :
												$message = "آی پی یا مرچنت زرین پال اشتباه است .";
                                                break;

												case "-3" :
												$message = "با توجه به محدودیت های شاپرک امکان پرداخت با رقم درخواست شده میسر نمیباشد .";
                                                break;
                                                
												case "-4" :
												$message = "سطح تایید پذیرنده پایین تر از سطح نقره ای میباشد .";
                                                break;
												
												case "-11" :
												$message = "درخواست مورد نظر یافت نشد .";
                                                break;
												
												case "-21" :
												$message = "هیچ نوع عملیات مالی برای این تراکنش یافت نشد .";
                                                break;
												
												case "-22" :
												$message = "تراکنش نا موفق میباشد .";
                                                break;
												
												case "-33" :
												$message = "رقم تراکنش با رقم وارد شده مطابقت ندارد .";
                                                break;
												
												case "-40" :
												$message = "اجازه دسترسی به متد مورد نظر وجود ندارد .";
                                                break;
												
												case "-54" :
												$message = "درخواست مورد نظر آرشیو شده است .";
                                                break;
												
												case "100" :
												$message = "اتصال با زرین پال به خوبی برقرار شد و همه چیز صحیح است .";
                                                break;
				
												case "101" :
												$message = "تراکنش با موفقیت به پایان رسیده بود و تاییدیه آن نیز انجام شده بود .";
                                                break;			
}
 return $message;
}
    public static function has_zarinpal_condition($form, $config) {
        $config = $config["meta"];
        $operator = isset($config["zarinpal_conditional_operator"]) ? $config["zarinpal_conditional_operator"] : "";
        $field = RGFormsModel::get_field($form, $config["zarinpal_conditional_field_id"]);
        if(empty($field) || !$config["zarinpal_conditional_enabled"])
        return true;
        $is_visible = !RGFormsModel::is_field_hidden($form, $field, array());
        $field_value = RGFormsModel::get_field_value($field, array());
        $is_value_match = RGFormsModel::is_value_match($field_value, $config["zarinpal_conditional_value"], $operator);
        $go_to_zarinpal = $is_value_match && $is_visible;
        return  $go_to_zarinpal;
    }
    public static function get_config($form_id){
		$config = GFZarinPalData::get_feed_by_form($form_id);
		if(!$config)
            return false;
        return $config[0]; 
    }
    public static function get_config_by_entry($entry) {
		$feed_id = gform_get_meta($entry["id"], "zarinpal_feed_id");
        $feed = GFZarinPalData::get_feed($feed_id);
		return !empty($feed) ? $feed : false;
    }
	private static function return_url($form_id, $lead_id) {
	$pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';
	if ( $_SERVER['SERVER_PORT'] != '80' ) {
	$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . utf8_encode($_SERVER['REQUEST_URI']);
	} else {
	$pageURL .= $_SERVER['SERVER_NAME'] . utf8_encode($_SERVER['REQUEST_URI']);
	}
	$ids_query = "id={$form_id}|{$lead_id}";
	return add_query_arg("Return", $ids_query, $pageURL);
    }
    public static function zarinpal_verify() {
	if(!self::is_gravityforms_supported())
            return;
    if($str = RGForms::get("Return")){
	$verify = $str;
    parse_str($str, $query);
	list($form_id, $lead_id) = explode("|", $query["id"]);
    $form = RGFormsModel::get_form_meta($form_id);
    $lead = RGFormsModel::get_lead($lead_id);
	$entry = RGFormsModel::get_lead($lead_id);
	$currency = GFCommon::get_currency();
	$config = self::get_config_by_entry($entry);
    if( $_GET['Return'] == $verify  && $entry["payment_method"]== 'zarinpal' ){
    $payment_date = gmdate('Y-m-d H:i:s');
	$query_string = "";
        switch($config["meta"]["type"]){
            case "product" :   
                $transaction_type = 1;
            break;
            case "subscription" :
			    $transaction_type = 2;
            break;
        }
    if(!class_exists("GFFormDisplay"))
    require_once(GFCommon::get_base_path() . "/form_display.php");
    $confirmation = GFFormDisplay::handle_confirmation( $form, $lead, false );
	$query_string = self::get_product_query_string($form, $entry);
	if(!$query_string || $query_string==0) {
	$stat = 'completed';
    $transaction_id = rand(100000000000000,999999999999999);
	self::set_payment_status($config, $form, $form_id, $entry, $stat, $transaction_type, $transaction_id, 0 , 0);	
	}else {
	$MerchantID = self::get_merchent();
	$Amount = $query_string;
	if ($currency == 'IRR')
	$Amount = $query_string/10;
	$Authority = $_GET['Authority'];
	if($_GET['Status'] == 'OK'){
	$server = self::get_server();
	if ($server == "German")
	{
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8')); 
	}
	else {
	$client = new SoapClient('https://ir.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8')); 
	}
	$result = $client->PaymentVerification(
						  	array(
									'MerchantID'	 => $MerchantID,
									'Authority' 	 => $Authority,
									'Amount'	 => $Amount
								)
	);
	if($result->Status == 100){
		$stat = 'completed';
		$transaction_id = $result->RefID;
		$fault = 0;
		}
		else {
		$stat = 'failed';
		$transaction_id = 0;
	    $fault = $result->Status;
		}
	} 
	else {
	$stat = 'cancelled';
	$transaction_id = 0;
	$fault = 0;
	}	
	self::set_payment_status($config, $form, $form_id, $entry, $stat, $transaction_type, $transaction_id, $query_string, $fault);	
	}
    }
	}
    }
    public static function set_payment_status($config, $form, $form_id, $entry, $status, $transaction_type, $transaction_id, $amount, $fault){
	if (!$entry['transaction_id']){
	$wp_session = WP_Session::get_instance();
	@session_start();
	$wp_session['refid_zarinpal'] = $form["id"].$entry["id"];
	$_SESSION["refids_zarinpal"] = $form["id"].$entry["id"];
	global $current_user;
			$user_id = 0;
			$user_name = "مهمان";
			if($current_user && $user_data = get_userdata($current_user->ID)){
            $user_id = $current_user->ID;
            $user_name = $user_data->display_name;}
				switch($status){
					case "completed" :								
								if ($transaction_type == 2){				 		
								$entry["payment_status"] = "Active";
                                $entry["payment_amount"] = $amount;
                                $entry["payment_date"] = gmdate("Y-m-d H:i:s");
                                $entry["transaction_id"] = $transaction_id;
                                $entry["transaction_type"] = 2;
								$entry["is_fulfilled"] = 1;
                                self::log_debug("Updating entry.");
                                RGFormsModel::update_lead($entry);
                                self::log_debug("Adding note.");
                                RGFormsModel::add_note($entry["id"], $user_id, $user_name, sprintf(__("وضعیت اشتراک : موفق - مبلغ پرداخت : %s - کد رهگیری (شماره سند) : %s", "gravityformszarinpal"), GFCommon::to_money($entry["payment_amount"], $entry["currency"]), $entry["transaction_id"]));
								RGFormsModel::add_note($entry["id"], $user_id, $user_name, sprintf(__("تغییرات اطلاعات فیلدها فقط در همین رسید اعمال خواهد شد و بر روی وضعیت کاربر تاثیری نخواهد داشت .", "gravityformszarinpal")));
								self::send_notification( "form_submission", $form, $entry );
								self::log_debug("Inserting transaction.");
                                    self::fulfill_order($entry, $transaction_id, $amount);
                                    self::log_debug("Order has been fulfilled");
								}
								if ($transaction_type == 1){				 		
								$entry["payment_status"] = "Paid";
                                $entry["payment_amount"] = $amount;
                                $entry["payment_date"] = gmdate("Y-m-d H:i:s");
                                $entry["transaction_id"] = $transaction_id;
                                $entry["transaction_type"] = 1;
								$entry["is_fulfilled"] = 1;
                                self::log_debug("Updating entry.");
                                RGFormsModel::update_lead($entry);
                                self::log_debug("Adding note.");
                                RGFormsModel::add_note($entry["id"], $user_id, $user_name, sprintf(__("وضعیت پرداخت : موفق - مبلغ پرداخت : %s - کد رهگیری (شماره سند) : %s", "gravityformszarinpal"), GFCommon::to_money($entry["payment_amount"], $entry["currency"]), $entry["transaction_id"]));
								self::send_notification( "form_submission", $form, $entry );
								self::log_debug("Inserting transaction.");  
                                    self::fulfill_order($entry, $transaction_id, $amount);
                                    self::log_debug("Order has been fulfilled");
								}
                    break;
					case "failed" :
                                $entry["payment_status"] = "Failed";
                                $entry["payment_amount"] = 0;
                                $entry["transaction_id"] = "-";
								$entry["is_fulfilled"] = 0;
                                self::log_debug("Updating entry.");
                                RGFormsModel::update_lead($entry);
                                self::log_debug("Adding note.");
                                RGFormsModel::add_note($entry["id"], $user_id, $user_name, sprintf(__("وضعیت پرداخت : ناموفق - خطای تراکنش : %s یعنی %s", "gravityformszarinpal"), $fault, self::khata_nama($fault)));
							    if(!$config["meta"]["delay_notifications"]){self::send_notification ( "form_submission", $form, $entry );}  
                    break;
					case "cancelled" :
                                $entry["payment_status"] = "Cancelled";
								$entry["payment_amount"] = 0;
								$entry["is_fulfilled"] = 0;
                                $entry["transaction_id"] = "-";
                                self::log_debug("Updating entry.");
                                RGFormsModel::update_lead($entry);
                                self::log_debug("Adding note.");
                                RGFormsModel::add_note($entry["id"], $user_id, $user_name, sprintf(__("وضعیت پرداخت : منصرف شده", "gravityformszarinpal")));
								if(!$config["meta"]["delay_notifications"]){self::send_notification ( "form_submission", $form, $entry );}
					break;	
					
    }
	}
    if(!class_exists("GFFormDisplay"))
    require_once(GFCommon::get_base_path() . "/form_display.php");	
    $confirmation = GFFormDisplay::handle_confirmation( $form, $entry, false );	
	if ( $status == "failed"){
	$tr = $entry["id"] ? ("<br/>شماره سفارش : ".$entry["id"]) : "";		
	$vaziat = "تراکنش ناموفق بود . خطای تراکنش : ". $fault ."<br>". self:: khata_nama($fault).$tr;
	}
	if ( $status == "cancelled"){ if($config["meta"]["cancel_pm"]){$vaziat = $config["meta"]["cancel_pm"];}else{$vaziat = 'تراکنش به دلیل انصراف کاربر ناتمام باقی ماند . ';} 
	$tr = $entry["id"] ? ("<br/>شماره سفارش : ".$entry["id"]) : "";		
	$vaziat = $vaziat.$tr;
	}
	if ( $status == "completed" || $entry["payment_status"] == "Active" || $entry["payment_status"] == "Paid") $vaziat = $confirmation;
	$wp_session = WP_Session::get_instance();
	@session_start();
	if ($wp_session['refid_zarinpal'])
	$sess = $wp_session['refid_zarinpal'];
	else if ($_SESSION["refids_zarinpal"])
	$sess = $_SESSION["refids_zarinpal"];
	if (isset($entry['transaction_id']) && !isset($sess) ) { 
	unset($vaziat);
	$vaziat = "این تراکنش قبلا به پایان رسیده بود و نتیجه آن نیز اعلام شده بود . بنا به دلایل امنیتی از بازگو کردن وضعیت آن به شما معذوریم .";	
	} 
	GFFormDisplay::$submission[$form_id] = array("is_confirmation" => true, "confirmation_message" => $vaziat, "form" => $form, "lead" => $entry);
	do_action("gform_post_payment_status", $config, $entry, $status,  $transaction_id, $amount);
	}
    public static function fulfill_order(&$entry, $transaction_id, $amount){
        $config = self::get_config_by_entry($entry);
        if(!$config){
            self::log_error("Order can't be fulfilled because feed wasn't found for form: {$entry["form_id"]}");
            return;
        }
        $form = RGFormsModel::get_form_meta($entry["form_id"]);
		if (GFCommon::has_post_field($form["fields"])) {
            if($config["meta"]["update_post_action2"] == ""){
			self::log_debug("Creating post.");
            RGFormsModel::create_post($form, $entry);
			}
			$post = get_post($entry["post_id"]);
			if ($config["meta"]["update_post_action1"] !="" ) {
			switch($config["meta"]["update_post_action1"]){
                    case "publish" :
					$post->post_status = 'publish';
					break;
					case "draft" :
					$post->post_status = 'draft';
					break;
					case "pending" :
					$post->post_status = 'pending';
					break;
					case "private" :
					$post->post_status = 'private';
					break;				
			}	
			}else {
			$post->post_status = rgar($form, "postStatus");
			}
			wp_update_post($post);
			}
		if($config["meta"]["type"] == "subscription" )	
		if(class_exists("GFUser")) self::gf_create_user_By_HANNANStd($entry, $form);	
		if(class_exists("GFTwilio")) 
		{	
		if (function_exists('export')) GFTwilio::export($entry, $form);
		}
		if(class_exists("GFHANNANSMS")) GFHANNANSMS::sendsms_By_HANNANStd($entry, $form);
		if(!class_exists("GFFormDisplay"))
		require_once(GFCommon::get_base_path() . "/form_display.php");
		$confirmation = GFFormDisplay::handle_confirmation( $form, $entry, false );		
        self::log_debug("Before gform_zarinpal_fulfillment.");
	    if(is_array($confirmation) && isset($confirmation["redirect"])){header("Location: {$confirmation["redirect"]}");}
        do_action("gform_zarinpal_fulfillment", $entry, $config, $transaction_id, $amount);
    }
private static function get_product_query_string($form, $entry){
$config = self::get_active_config($form);
$currency = GFCommon::get_currency();
        $products = GFCommon::get_product_fields($form, $entry, true);
        $product_index = 1;
        $total = 0;
        $discount = 0;
        foreach($products["products"] as $product){
            $option_fields = "";
            $price = GFCommon::to_number($product["price"]);
            if(is_array(rgar($product,"options"))){
                $option_index = 1;
                foreach($product["options"] as $option){
                    $field_label = urlencode($option["field_label"]);
                    $option_name = urlencode($option["option_name"]);
                    $option_fields .= "&on{$option_index}_{$product_index}={$field_label}&os{$option_index}_{$product_index}={$option_name}";
                    $price += GFCommon::to_number($option["price"]);
                    $option_index++;
                }
            }
            $name = urlencode($product["name"]);
            if($price > 0)
            {
                $total += $price * $product['quantity'];
                $product_index++;
            }
            else{
                $discount += abs($price) * $product['quantity'];
            }
        }
        if(!empty($products["shipping"]["price"])) {
        $total += floatval($products["shipping"]["price"]);
		}
		if($discount > 0){
		    if($discount < $total) {
		    $total = $total-$discount;
			}
			else {
			$total = 0;
			}
		}
		else {
		$total = $total;
		}		
if ($currency == 'IRR' || $currency == 'IRT') {
if ($currency == 'IRR' && $total<1000 && $total>0){if ($config["meta"]["shaparak"] == "sadt") $total = 1000; else $total = 0;}
if ($currency == 'IRT' && $total<100 && $total>0){if ($config["meta"]["shaparak"] == "sadt") $total = 100; else $total = 0;}
}
return $total;
    }   
	public static function uninstall(){
        if(!GFZarinPal::has_access("gravityforms_zarinpal_uninstall"))
        die(__("شما مجوز کافی برای این کار را ندارید . سطح دسترسی شما پایین تر از حد مجاز است . ", "gravityformszarinpal"));
        GFZarinPalData::drop_tables();        
		delete_option("gf_zarinpal_settings");
        delete_option("gf_zarinpal_configured");
        delete_option("gf_zarinpal_version");
        $plugin = "gravityformszarinpal/zarinpal.php";
        deactivate_plugins($plugin);
        update_option('recently_activated', array($plugin => time()) + (array)get_option('recently_activated'));
    }
    private static function is_gravityforms_installed(){
        return class_exists("RGForms");
    }
    private static function is_gravityforms_supported(){
        if(class_exists("GFCommon")){
            $is_correct_version = version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=");
            return $is_correct_version;
        }
        else{
            return false;
        }
    }
    protected static function has_access($required_permission){
        $has_members_plugin = function_exists('members_get_capabilities');
        $has_access = $has_members_plugin ? current_user_can($required_permission) : current_user_can("level_7");
        if($has_access)
            return $has_members_plugin ? $required_permission : "level_7";
        else
            return false;
    }
   private static function get_customer_information($form, $config=null){
		$form_fields = self::get_form_fields($form);
		$str = "";
        $customer_fields = self::get_customer_fields();
        foreach($customer_fields as $field){
            $selected_field = $config ? $config["meta"]["customer_fields"][$field["name"]] : "";
            $str .= "" . $field["label"]  . "" . self::get_mapped_field_list($field["name"], $selected_field, $form_fields) . "";
        }
        $str .= "";
        return $str;
    }
			//email
	private static function get_customer_information_email($form, $config=null){
		$form_fields = self::get_form_fields($form);
		$str = "";
        $customer_fields_email = self::get_customer_fields();
        foreach($customer_fields_email as $field){
            $selected_field = $config ? $config["meta"]["customer_fields_email"][$field["name"]] : "";
            $str .= "<label style='margin-left:104px;' for='zarinpal_customer_field_email_tozihat'>ایمیل</label>" . self::get_mapped_field_list_email($field["name"], $selected_field, $form_fields) . "";
        }
        $str .= "";
        return $str;
    }
	private static function get_mapped_field_list_email($variable_name, $selected_field, $fields){
        $field_name = "zarinpal_customer_field_email_" . $variable_name;
        $str = "<select name='$field_name' id='$field_name'><option value=''></option>";
        foreach($fields as $field){
            $field_id = $field[0];
            $field_label = esc_html(GFCommon::truncate_middle($field[1], 40));
            $selected = $field_id == $selected_field ? "selected='selected'" : "";
            $str .= "<option value='" . $field_id . "' ". $selected . ">" . $field_label . "</option>";
        }
        $str .= "</select>";
        return $str;
    }
	//
			//mobile
	private static function get_customer_information_mobile($form, $config=null){
		$form_fields = self::get_form_fields($form);
		$str = "";
        $customer_fields_mobile = self::get_customer_fields();
        foreach($customer_fields_mobile as $field){
            $selected_field = $config ? $config["meta"]["customer_fields_mobile"][$field["name"]] : "";
            $str .= "<label style='margin-left:77px;' for='zarinpal_customer_field_mobile_tozihat'>تلفن همراه</label>" . self::get_mapped_field_list_mobile($field["name"], $selected_field, $form_fields) . "";
        }
        $str .= "";
        return $str;
    }
	private static function get_mapped_field_list_mobile($variable_name, $selected_field, $fields){
        $field_name = "zarinpal_customer_field_mobile_" . $variable_name;
        $str = "<select name='$field_name' id='$field_name'><option value=''></option>";
        foreach($fields as $field){
            $field_id = $field[0];
            $field_label = esc_html(GFCommon::truncate_middle($field[1], 40));
            $selected = $field_id == $selected_field ? "selected='selected'" : "";
            $str .= "<option value='" . $field_id . "' ". $selected . ">" . $field_label . "</option>";
        }
        $str .= "</select>";
        return $str;
    }
	//
	
	private static function get_customer_fields(){
        return array(array("name" => "tozihat" , "label" => "<label for='zarinpal_customer_field_tozihat' style='margin-left:17px;'>انتخاب محتوای یک فیلد</label>"));
    }
	private static function get_mapped_field_list($variable_name, $selected_field, $fields){
        $field_name = "zarinpal_customer_field_" . $variable_name;
        $str = "<select name='$field_name' id='$field_name'><option value=''></option>";
        foreach($fields as $field){
            $field_id = $field[0];
            $field_label = esc_html(GFCommon::truncate_middle($field[1], 40));
            $selected = $field_id == $selected_field ? "selected='selected'" : "";
            $str .= "<option value='" . $field_id . "' ". $selected . ">" . $field_label . "</option>";
        }
        $str .= "</select>";
        return $str;
    }
	private static function get_form_fields($form){
        $fields = array();
        if(is_array($form["fields"])){
            foreach($form["fields"] as $field){
                if(isset($field["inputs"]) && is_array($field["inputs"])){
                    foreach($field["inputs"] as $input)
                        $fields[] =  array($input["id"], GFCommon::get_label($field, $input["id"]));
                }
                else if(!rgar($field, 'displayOnly')){
                    $fields[] =  array($field["id"], GFCommon::get_label($field));
                }
            }
        }
        return $fields;
    }
	private static function is_zarinpal_page(){
        $current_page = trim(strtolower(RGForms::get("page")));
        return in_array($current_page, array("gf_zarinpal"));
    }
	protected static function get_base_url(){
        return plugins_url(null, __FILE__);
    }
	protected static function get_base_path(){
        $folder = basename(dirname(__FILE__));
        return WP_PLUGIN_DIR . "/" . $folder;
    }
    public static function admin_edit_payment_status_details_by_HANNANStd($form_id, $lead)
    {	
		$payment_gateway = rgar($lead, "payment_method");
		if ($payment_gateway == "zarinpal") {
		?>
		<hr/>
		<strong style="font-family:byekan">اطلاعات تراکنش :</strong><br/><br/>
		<?php
		$zarinpal_feed_id = gform_get_meta($lead["id"], "zarinpal_feed_id");
		$feed_config = GFZarinPalData::get_feed($zarinpal_feed_id);
		$transaction_type = rgar($lead, "transaction_type");
		$payment_status = rgar($lead, "payment_status");
		
		$payment_amount = rgar($lead, "payment_amount");
		if (empty($payment_amount))
		{$form = RGFormsModel::get_form_meta($form_id);
		$payment_amount = GFCommon::get_order_total($form,$lead);}
	  	$transaction_id = rgar($lead, "transaction_id");
		$payment_date = rgar($lead, "payment_date");
		if (empty($payment_date))
		{$payment_date = rgar($lead, "date_created");}
		$date = new DateTime($payment_date);
		$tzb = get_option('gmt_offset'); 
		$tzn = abs($tzb) * 3600;
		$tzh = intval(gmdate("H", $tzn));
		$tzm = intval(gmdate("i", $tzn));
		if ( intval($tzb) < 0) {
		$date->sub(new DateInterval('P0DT'.$tzh.'H'.$tzm.'M'));
		}
		else {
		$date->add(new DateInterval('P0DT'.$tzh.'H'.$tzm.'M'));}
		$payment_date = $date->format('Y-m-d H:i:s');
		$payment_date = GF_jdate('Y-m-d H:i:s',strtotime($payment_date),'',date_default_timezone_get(),'en'); 
		
		if ($payment_status =='Paid') $payment_status_persian = 'موفق';	
		if ($payment_status =='Active') $payment_status_persian = 'موفق';	
		if ($payment_status =='Cancelled') $payment_status_persian = 'منصرف شده';	
		if ($payment_status =='Failed') $payment_status_persian = 'ناموفق';			
		if ($payment_status =='Processing') $payment_status_persian = 'معلق';
		
		if (!strtolower(rgpost("save")) || RGForms::post("screen_mode") != "edit" ) {
		echo 'وضعیت پرداخت : '.$payment_status_persian.'<br/><br/>';	
		echo 'تاریخ پرداخت : '.$payment_date.'<br/><br/>';
		echo 'مبلغ پرداختی : '.$payment_amount.'<br/><br/>';
		echo 'کد رهگیری : '.$transaction_id.'<br/><br/>';
		echo 'درگاه پرداخت : زرین پال';
		}
		else {
		$payment_string = '';
		$payment_string .= '<select id="payment_status" name="payment_status">';
		$payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status_persian . '</option>';
		if($transaction_type==1){
		if($payment_status != "Paid")
		$payment_string .= '<option value="Paid">موفق</option>';
		}
		if($transaction_type==2){
		if($payment_status != "Active")
		$payment_string .= '<option value="Active">موفق</option>';
		}
		if (!$transaction_type)
		{
		if($payment_status != "Paid")
		$payment_string .= '<option value="Paid">موفق</option>';
		if($payment_status != "Active")
		$payment_string .= '<option value="Active">موفق</option>';
		}
		if($payment_status != "Failed")
		$payment_string .= '<option value="Failed">ناموفق</option>';
		if($payment_status != "Cancelled")
		$payment_string .= '<option value="Cancelled">منصرف شده</option>';
		if($payment_status != "Processing")
		$payment_string .= '<option value="Processing">معلق</option>';
		$payment_string .= '</select>';
		echo 'وضعیت پرداخت :'.$payment_string.'<br/><br/>';		
		?>
		<div id="edit_payment_status_details" style="display:block">
			<table>
				<tr>
					<td>تاریخ پرداخت : </td>
					<td><input type="text" id="payment_date" name="payment_date" value="<?php echo $payment_date?>"></td>
				</tr>
				<tr>
					<td>مبلغ پرداختی : </td>
					<td><input type="text" id="payment_amount" name="payment_amount" value="<?php echo $payment_amount?>"></td>
				</tr>
				<tr>
					<td>کد رهگیری : </td>
					<td><input type="text" id="zarinpal_transaction_id" name="zarinpal_transaction_id" value="<?php echo $transaction_id?>"></td>
				</tr>

			</table><br/>
		</div>
		<?php
		echo 'درگاه پرداخت : زرین پال (غیر قابل ویرایش)';
	}
	

echo '<br/><hr/>';
	}
	}
	
	public static function admin_update_payment_by_HANNANStd($form, $lead_id){	
		$lead = RGFormsModel::get_lead($lead_id);
	    check_admin_referer('gforms_save_entry', 'gforms_save_entry');
		$payment_gateway = $lead["payment_method"];
		$form_action = strtolower(rgpost("save"));
		if ($payment_gateway <> "zarinpal")
			return;
		$payment_status = rgpost("payment_status");
		if (empty($payment_status)){
		$payment_status = $lead["payment_status"];}
		$payment_amount = rgpost("payment_amount");
		$payment_transaction = rgpost("zarinpal_transaction_id");
		$payment_date = rgpost("payment_date");
		$payment_date_Checker = $payment_date;
		list($date,$time) = explode(" ",$payment_date);
		list($Y,$m,$d) = explode("-",$date);
		list($H,$i,$s) = explode (":",$time);
		$miladi = GF_jalali_to_gregorian($Y,$m,$d);
		$date = new DateTime("$miladi[0]-$miladi[1]-$miladi[2] $H:$i:$s");
		$payment_date = $date->format('Y-m-d H:i:s');
		if (empty($payment_date_Checker)) {
		if (!empty($lead["payment_date"])){
		$payment_date = $lead["payment_date"];}
		else {$payment_date = $lead["date_created"];}
		}
		else { 
		$payment_date = date("Y-m-d H:i:s", strtotime($payment_date));
		$date = new DateTime($payment_date);
		$tzb = get_option('gmt_offset'); 
		$tzn = abs($tzb) * 3600;
		$tzh = intval(gmdate("H", $tzn));
		$tzm = intval(gmdate("i", $tzn));
		if ( intval($tzb) < 0) {
		$date->add(new DateInterval('P0DT'.$tzh.'H'.$tzm.'M'));
		}else {
		$date->sub(new DateInterval('P0DT'.$tzh.'H'.$tzm.'M'));}
		$payment_date = $date->format('Y-m-d H:i:s');
		}
		global $current_user;
		$user_id = 0;
        $user_name = "مهمان";
        if($current_user && $user_data = get_userdata($current_user->ID)){
            $user_id = $current_user->ID;
            $user_name = $user_data->display_name;
        }
		$lead["payment_status"] = $payment_status;
		$lead["payment_amount"] = $payment_amount;	
		$lead["payment_date"] =   $payment_date;
		$lead["transaction_id"] = $payment_transaction;
		RGFormsModel::update_lead($lead);
		switch ($lead["payment_status"]){
		case "Active" : $statn= 'موفق'; break;
		case "Paid" : $statn= 'موفق'; break;
		case "Cancelled" : $statn= 'منصرف شده'; break;
		case "Failed" : $statn= 'ناموفق'; break;
		case "Processing" : $statn= 'معلق'; break;
		}
		RGFormsModel::add_note($lead["id"], $user_id, $user_name, sprintf(__("اطلاعات تراکنش به صورت دستی ویرایش شد . وضعیت : %s - مبلغ : %s - کد رهگیری : %s - تاریخ : %s", "gravityformszarinpal"), $statn, GFCommon::to_money($lead["payment_amount"], $lead["currency"]), $payment_transaction, $lead["payment_date"]));	 
		if($payment_status == 'Paid' || $payment_status == 'Active'){
		RGFormsModel::update_lead_property($lead["id"], "is_fulfilled", 1);
		}
		else {
		RGFormsModel::update_lead_property($lead["id"], "is_fulfilled", 0);
		}   
	}	
 
function set_logging_supported($plugins)
{
$plugins[self::$slug] = "ZarinPal Payments Standard";
return $plugins;
}
private static function log_error($message){
if(class_exists("GFLogging"))
{
GFLogging::include_logger();
GFLogging::log_message(self::$slug, $message, KLogger::ERROR);
}
}
private static function log_debug($message){
if(class_exists("GFLogging"))
{
GFLogging::include_logger();
GFLogging::log_message(self::$slug, $message, KLogger::DEBUG);
}
}
}
class GFZarinPalData{
    public static function update_table(){
        global $wpdb;
        $table_name = self::get_zarinpal_table_name();
        if ( ! empty($wpdb->charset) )
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if ( ! empty($wpdb->collate) )
            $charset_collate .= " COLLATE $wpdb->collate";
        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
        $sql = "CREATE TABLE $table_name (
              id mediumint(8) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              is_active tinyint(1) not null default 1,
              meta longtext,
              PRIMARY KEY  (id),
              KEY form_id (form_id)
            )$charset_collate;";
        dbDelta($sql);
    }
    public static function get_zarinpal_table_name(){
        global $wpdb;
        return $wpdb->prefix . "rg_zarinpal";
    }
    public static function drop_tables(){
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_zarinpal_table_name());
    }
	public static function get_available_forms($active_form = ''){
        $forms = RGFormsModel::get_forms();
        $available_forms = array();
        foreach($forms as $form) {
            $available_forms[] = $form;
        }
        return $available_forms;
    }
	public static function get_feed($id){
        global $wpdb;
        $table_name = self::get_zarinpal_table_name();
        $sql = $wpdb->prepare("SELECT id, form_id, is_active, meta FROM $table_name WHERE id=%d", $id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        if(empty($results))
            return array();
        $result = $results[0];
        $result["meta"] = maybe_unserialize($result["meta"]);
        return $result;
    }
    public static function get_feeds(){
        global $wpdb;
        $table_name = self::get_zarinpal_table_name();
        $form_table_name = RGFormsModel::get_form_table_name();
        $sql = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
                FROM $table_name s
                INNER JOIN $form_table_name f ON s.form_id = f.id";
        $results = $wpdb->get_results($sql, ARRAY_A);
        $count = sizeof($results);
        for($i=0; $i<$count; $i++){
            $results[$i]["meta"] = maybe_unserialize($results[$i]["meta"]);
        }
        return $results;
    }
	public static function get_feed_by_form($form_id, $only_active = false){
        global $wpdb;
        $table_name = self::get_zarinpal_table_name();
        $active_clause = $only_active ? " AND is_active=1" : "";
        $sql = $wpdb->prepare("SELECT id, form_id, is_active, meta FROM $table_name WHERE form_id=%d $active_clause", $form_id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        if(empty($results))
            return array();
        $count = sizeof($results);
        for($i=0; $i<$count; $i++){
            $results[$i]["meta"] = maybe_unserialize($results[$i]["meta"]);
        }
        return apply_filters("gform_zarinpal_get_feeds_{$form_id}", apply_filters('gform_zarinpal_get_feeds', $results, $form_id), $form_id);
    }
	public static function update_feed($id, $form_id, $is_active, $setting){
        global $wpdb;
        $table_name = self::get_zarinpal_table_name();
        $setting = maybe_serialize($setting);
        if($id == 0){
            $wpdb->insert($table_name, array("form_id" => $form_id, "is_active"=> $is_active, "meta" => $setting), array("%d", "%d", "%s"));
            $id = $wpdb->get_var("SELECT LAST_INSERT_ID()");
        }
        else{
            $wpdb->update($table_name, array("form_id" => $form_id, "is_active"=> $is_active, "meta" => $setting), array("id" => $id), array("%d", "%d", "%s"), array("%d"));
        }
        return $id;
    }
    public static function delete_feed($id){
        global $wpdb;
        $table_name = self::get_zarinpal_table_name();
        $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id=%s", $id));
    }
	public static function get_transaction_totals($form_id){
        global $wpdb;
        $lead_table_name = RGFormsModel::get_lead_table_name();
        $sql = $wpdb->prepare(" SELECT l.status, sum(l.payment_amount) revenue, count(l.id) transactions
                                 FROM {$lead_table_name} l
                                 WHERE l.form_id={$form_id} AND l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                 GROUP BY l.status", $form_id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        $totals = array();
        if(is_array($results)){
            foreach($results as $result){
                $totals[$result["status"]] = array("revenue" => empty($result["revenue"]) ? 0 : $result["revenue"] , "transactions" => empty($result["transactions"]) ? 0 : $result["transactions"]);
            }
        }
        return $totals;
    }
	public static function get_transaction_totals_zarin($form_id){
        global $wpdb;
        $lead_table_name = RGFormsModel::get_lead_table_name();
        $sql = $wpdb->prepare(" SELECT l.status, sum(l.payment_amount) revenue, count(l.id) transactions
                                 FROM {$lead_table_name} l
                                 WHERE l.status='active' AND l.is_fulfilled=1 AND l.payment_method='zarinpal'
                                 GROUP BY l.status", $form_id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        $totals = array();
        if(is_array($results)){
            foreach($results as $result){
                $totals[$result["status"]] = array("revenue" => empty($result["revenue"]) ? 0 : $result["revenue"] , "transactions" => empty($result["transactions"]) ? 0 : $result["transactions"]);
            }
        }
        return $totals;
    }
	public static function get_transaction_totals_gateways($form_id){
        global $wpdb;
        $lead_table_name = RGFormsModel::get_lead_table_name();
        $sql = $wpdb->prepare(" SELECT l.status, sum(l.payment_amount) revenue, count(l.id) transactions
                                 FROM {$lead_table_name} l
                                 WHERE l.form_id={$form_id} AND l.status='active' AND l.is_fulfilled=1
                                 GROUP BY l.status", $form_id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        $totals = array();
        if(is_array($results)){
            foreach($results as $result){
                $totals[$result["status"]] = array("revenue" => empty($result["revenue"]) ? 0 : $result["revenue"] , "transactions" => empty($result["transactions"]) ? 0 : $result["transactions"]);
            }
        }
        return $totals;
    }
	public static function get_transaction_totals_site($form_id){
        global $wpdb;
        $lead_table_name = RGFormsModel::get_lead_table_name();
        $sql = $wpdb->prepare(" SELECT l.status, sum(l.payment_amount) revenue, count(l.id) transactions
                                 FROM {$lead_table_name} l
                                 WHERE l.status='active' AND l.is_fulfilled=1
                                 GROUP BY l.status", $form_id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        $totals = array();
        if(is_array($results)){
            foreach($results as $result){
                $totals[$result["status"]] = array("revenue" => empty($result["revenue"]) ? 0 : $result["revenue"] , "transactions" => empty($result["transactions"]) ? 0 : $result["transactions"]);
            }
        }
        return $totals;
    }
}
if(!function_exists("rgget")){
function rgget($name, $array=null){
if(!isset($array))
$array = $_GET;
if(isset($array[$name]))
return $array[$name];
return "";
}
}
if(!function_exists("rgpost")){
function rgpost($name, $do_stripslashes=true){
if(isset($_POST[$name]))
return $do_stripslashes ? stripslashes_deep($_POST[$name]) : $_POST[$name];
return "";
}
}
if(!function_exists("rgar")){
function rgar($array, $name){
if(isset($array[$name]))
return $array[$name];
return '';
}
}
if(!function_exists("rgars")){
function rgars($array, $name){
$names = explode("/", $name);
$val = $array;
foreach($names as $current_name){
$val = rgar($val, $current_name);
}
return $val;
}
}
if(!function_exists("rgempty")){
function rgempty($name, $array = null){
if(!$array)
$array = $_POST;
$val = rgget($name, $array);
return empty($val);
}
}
if(!function_exists("rgblank")){
function rgblank($text){
return empty($text) && strval($text) != "0";
}
}
?>