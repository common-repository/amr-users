<?php

require_once ('ameta-includes.php');
require_once ('ameta-admin-import-export-list.php');

include ('ameta-admin-cache-status.php');
include ('ameta-admin-testyourdb.php');
include ('ameta-admin-cache-logs.php');
include ('ameta-admin-nice-names.php');
include ('ameta-admin-meta-keys.php');
include ('ameta-admin-cache-settings.php');
include ('ameta-admin-general.php');
include ('ameta-admin-configure.php');
 
function amr_wplist_sortable($columns) {
	$colstoadd = ausers_get_option ('amr-users-show-in-wplist');
	$orig_mk = ausers_get_option('amr-users-original-keys') ;
	$wpfields = amr_get_usermasterfields();
	if (empty($colstoadd)) return $columns;
  	foreach ($colstoadd as $field => $show) {
		if ($show) {
			if (in_array($field, $wpfields)	) {  
				$columns[$field] = $field;
			}	
			elseif (in_array($field, $orig_mk)) {
				if (function_exists('amru_custom_orderby')) {  // from plus
					$columns[$field] = $field;
				}
			}
		}
	}
	return $columns;
}
 
function amr_q_orderby( $query ) {  // but only in the main user list page or real query
	if( ! is_admin() )
		return;

	$wpfields = amr_get_usermasterfields();
	$orderby = $query->get( 'orderby');  // wp will have sanitised?
	if ($orderby == 'user_registration_date')  // for compatibility - no longer need! delete in a version or 2
		$orderby = 'user_registered';
	if (!(in_array($orderby, $wpfields ))) { // assume its a meta field
		$query->set('meta_key',$orderby);
		$query->set('orderby','meta_value');
	}
}
 	
function amr_add_user_columns ($columns) {
	$colstoadd = ausers_get_option ('amr-users-show-in-wplist');
	$nicenames = ausers_get_option ('amr-users-nicenames');
	if (empty($colstoadd)) return $columns;  //201402 avoid notices/warnings if no columns added
	foreach ($colstoadd as $field => $show) {
		if ($show) {
			if (!empty($nicenames[$field]))
				$columns[$field] =  $nicenames[$field];
			else 
				$columns[$field] = $field;
		}
	}
	return $columns;
}
 	
function amr_show_user_columns($value, $column_name, $user_id) {
	global $wpdb;
	$colstoadd = ausers_get_option ('amr-users-show-in-wplist');
	
	if (!empty($colstoadd[$column_name])) {
		$user_info = get_userdata($user_id);
		if (empty($value)) 
			$value = $user_info->$column_name;
		$func = 'ausers_format_'.ltrim($column_name,$wpdb->prefix); 
		if (function_exists($func)) {
			$text =  (call_user_func($func, $value, $user_info));
			return $text;
		}	
		else {
			$text = amr_wp_list_format_cell ($column_name, $value, $user_info);
			return $text;
			}
	}		
	else 
	return $value;
}
 
function amr_users_do_tabs_fields ($config, $current_tab) {
	global $amr_current_list; 
	// check for tabs  
	 // display the icon and page title  
	$page	= sanitize_text_field($_GET['page']);  
	//$ulist 	= empty($_REQUEST['list']) ? '' : (int) ($_REQUEST['list']);
	$url = remove_query_arg('config');
	$url = add_query_arg(array( 
		'page' => $page,
		'list' => $amr_current_list));
		
	echo '<div class="clear"> </div>';	
	// wrap each in anchor html tags  
	$links = array();  
	foreach( $config as $ctab => $name ) {  
		// set anchor class  
		$class      = ($ctab == $current_tab ? 'nav-tab nav-tab-active' : 'nav-tab');  
		$links[]    = '<a class="'.$class.'" href="'
		.wp_nonce_url(add_query_arg(array( 'config' => $ctab),$url),'amr-meta').'">'.$name.'</a>';  
	}  
	echo PHP_EOL.'<div class="widefat"><p class="nav-tab-wrapper">'.implode(' ', $links)
		.PHP_EOL.'</p>'.PHP_EOL.'</div>';  //20200625 address swap of glue string
	echo '<div class="clear"> </div>';		 
}
 
function amr_meta_menu() { /* parent, page title, menu title, access level, file, function */
	/* Note have to have different files, else wordpress runs all the functions together */
	global 
		$amain,
		$amr_pluginpage,
		$ausersadminurl,
		$ausersadminusersurl;

	if (is_network_admin() ) {
		$ausersadminurl = network_admin_url('admin.php');
		$ausersadminusersurl = network_admin_url('users.php');
		}
	else {
		$ausersadminurl = admin_url('admin.php');
		$ausersadminusersurl = admin_url('users.php');
	}
	if (empty($amain)) 
		$amain = ausers_get_option('amr-users-main');
	
	/* add the options page at admin level of access */
	$menu_title = $page_title = __('User Lists', 'amr-users');

	$parent_slug =  'amr-users';
	$function = 	'amrmeta_about_page'; //'amr_meta_general';
	$menu_slug = 	'amr-users';	
	$capability = 	'manage_options';

	$settings_page = add_query_arg($ausersadminurl,'page','amr-users');
	
	$amr_pluginpage['users'] = add_menu_page($page_title, $menu_title , $capability, $menu_slug, $function);
	add_action('load-'.$amr_pluginpage['users'], 'amru_on_load_page');
	add_action('admin_init-'.$amr_pluginpage['users'], 'amr_load_scripts' );

	$parent_slug = $menu_slug;
	$amr_pluginpage['general'] = add_submenu_page($parent_slug, 
			__('User List Settings','amr-users'), __('General & about','amr-users'), 'manage_options',
			$menu_slug, $function);	
			
/*	$amr_pluginpage['about'] = add_submenu_page($parent_slug, 
			__('About','amr-users'), __('About','amr-users'), 'manage_options',
			$menu_slug, $function);	
			
	$amr_pluginpage['general'] = add_submenu_page($parent_slug, 
			__('User List Settings','amr-users'), __('General Settings','amr-users'), 'manage_options',
			'ameta-admin-general.php', 'amr_meta_general_page');
*/

		
	$amr_pluginpage['exkeys'] = add_submenu_page($parent_slug, 		
				__('User List Settings','amr-users'),
				__('Excluded Meta Keys', 'amr-users'),
				'manage_options',
			'ameta-admin-meta-keys.php', 'amr_meta_keys_page');	
			
	if (current_user_can('manage_user_lists')) 
		$capability = 'manage_user_lists';
	else 
		$capability = 'manage_options'; 
		
	$amr_pluginpage['fields'] = add_submenu_page($parent_slug, 		
				__('User List Settings','amr-users'),
				__('Fields &amp; Nice Names', 'amr-users'),
				'manage_options',
			'ameta-admin-nice-names.php', 'amr_meta_nice_names_page');	
	//add_action('admin_init-'.$amr_pluginpage['fields'], 'amr_load_fields_scripts' ); 
	add_action('admin_enqueue_scripts', 'amr_load_fields_scripts'); //20211129
	
	$amr_pluginpage['overview'] = add_submenu_page($parent_slug, 		
				__('User List Settings','amr-users'),
				__('Overview &amp; tools', 'amr-users'),
				$capability,
			'ameta-admin-overview.php', 'amr_meta_overview_page');		
					

	$amr_pluginpage['configure'] = add_submenu_page($parent_slug, 
			__('Configure a list','amr-users'), __('Configure a list','amr-users'), $capability,
			'ameta-admin-configure.php', 'amrmeta_configure_page');		
			
			
	add_action( 'admin_head-'.$amr_pluginpage['configure'], 'ameta_admin_style' );	
			
	$amr_pluginpage['cache'] = add_submenu_page($parent_slug, 
			__('Cache Settings','amr-users'), __('Cacheing','amr-users'), $capability,
			'ameta-admin-cache-settings.php', 'amrmeta_cache_settings_page');	

	add_action( 'admin_head-'.$amr_pluginpage['configure'], 'ameta_admin_style' );
	
	$amr_pluginpage['add-ons'] = add_submenu_page($parent_slug, 
			__('Add ons','amr-users'), __('Add ons','amr-users'), 'manage_options',
			'user-add-ons', 'amru_add_ons_page');	
	
	
	 
	if (empty($amain)) $amain = ausers_get_option('amr-users-main');  /*  Need to get this early so we can do menus */
			
	
	if (current_user_can('list_users') or current_user_can('manage_users'))  {

		if (isset ($amain['names'])) { /* add a separate menu item for each list */	
			foreach ($amain['names'] as $i => $name) {
				
				if (isset ($amain['names'][$i]) and (!function_exists ('amrmeta_organise_lists') ) or !empty($amain['show_in_menu'][$i]) ) {
					
					if (!empty($amain['is_public'][$i])) {
						$capability = 'read';
					}
					else {
						$capability = 'list_users';
					}
					
					$shortname = (empty ($amain['shortnames'][$i]) ? $amain['names'][$i] : $amain['shortnames'][$i]) ;
					$page = add_submenu_page(
					'users.php', // parent slug
					__('User lists', 'amr-users'), // title
					$shortname, //menu title
					$capability, // capability
					'ameta-list.php?ulist='.$i, //menu slug - must be ? why ??, priv problem if &
					'amr_list_user_meta'); // function
				}
			}
	}
	else {
	}
		
		// the default - list all lists IF some are not shown n menu, but for admin only

		$page = add_submenu_page(  // admininstrator only
			'users.php', // parent slug
			__('User lists', 'amr-users'), // title
			__('User lists', 'amr-users'), //menu title
			$capability, // capability
			'userlists', //menu slug - must be ? why ??, priv problem if &
			'amr_list_of_lists'
			); // function
		}	
		
		
		
	
	}
	
function amr_list_of_lists() { //20210311
global $amain,$amr_current_list;

	if (isset($_GET['list'])) { 
		$amr_current_list = (int) $_GET['list'];
		amr_view_user_lists($amr_current_list);
	}
	else { 
		amr_meta_admin_headings (); // does the nonce check etc	
		?>
	<h2><?php _e('All User Lists','amr-users'); ?></h2>	
	<?php	echo '<p><a style="margin-left: 6em;" href="'.admin_url('admin.php?page=ameta-admin-overview.php').'">'.__('Organise, Sort, Rename and Renumber lists', 'amr-users').'<a></p>' ;?>
	<table class="widefat striped" >
	<?php
		foreach ($amain['names'] as $i => $name) {?>
			<tr><td><a href="<?php  
						echo (admin_url('users.php?page=userlists&list='.$i)); ?>"><?php echo $i.'. '.$name; 
						//echo au_view_link($name, $i, $name); 
						?></a>

			</td></tr><?php
		}
		?>
	</tr>
	</table>
	<?php 	

	echo ausers_form_end();
	}
}	

function amr_view_user_lists($ulist) { 
	global $amain, $amr_current_list;	
		$amr_current_list=$ulist;
		$name_of_list = $amain['names'][$amr_current_list];
		echo PHP_EOL.'<div class="wrap">'.PHP_EOL.'<h2 class="nav-tab-wrapper">';
		echo PHP_EOL.au_view_link($name_of_list,$amr_current_list,$name_of_list,'nav-tab nav-tab-active');
		//echo PHP_EOL.au_settings_link(__('Settings','amr-users'), $amr_current_list, $name_of_list, 'nav-tab').' &nbsp; '; 
		//echo PHP_EOL.au_configure_link(__('Configure','amr-users'), $amr_current_list, $name_of_list, 'nav-tab').
		echo '</h2>';	
		
		//*** later consider making 'tabs' consistent, need to upgrade: amr_users_do_tabs();

		$atts = array('list_number' => $amr_current_list);
		
		amrmeta_choose_lists('userlists');

		amr_list_user_meta($atts);
		echo PHP_EOL.'</div><!-- end of wrap -->';		
}

function au_settings_link($text,$i,$name) {
global $ausersadminurl;	
	if (!current_user_can('manage_options') and !current_user_can('manage_user_lists')) return PHP_EOL.'<!-- no settings as cannot manage_options -->';			
	$url = (add_query_arg(array(
		'page' 	=> 'list-overview',
		'tab'	=> 'settings') ,
		$ausersadminurl	)); 
		
	$t = PHP_EOL.'<a class="nav-tab" href="'.wp_nonce_url($url,'amr-meta').'#list'.$i
		.'" title="'.esc_attr($name).' - '.__('Settings', 'amr-users').'" >'
		.esc_attr($text)
		.'</a>';
	return ($t);
}

function au_lists_link($text,$i,$name, $page, $tab='') { 
global $ausersadminurl, $amr_current_list;	

	$url = admin_url('users.php?page=userlists'); //20210312
	if (!empty($tab)) 
		$url = (add_query_arg(array(
			'tab' => $tab
			),
			$url));
	$url = (add_query_arg(array(
			'page' => $page,
			'list' => $i),
			$url	));	
			//20210323 remove unncessary add query of config 
	$t = '<a href="'.wp_nonce_url($url,'amr-meta')
		.'" title="'.esc_attr($name).'" >'
		.esc_attr($text)
		.'</a>';
	return ($t);
}
 
function amr_meta_admin_headings () {
global $aopt, $amains, $amr_current_list;
	
	amr_check_for_upgrades();  // so we only do if an upgrade and will only do if admin
	ameta_options();
	
	echo ausers_form_start(); //hmmm /- this is the list start

	if (isset ($_POST['action']) and  ($_POST['action'] == "save")) { 
		check_admin_referer('amr-meta','amr-meta');
	}
}
 	
function amrmeta_validate_text($texttype)	{ /*  text  field*/
	global $amain;

	if (!empty($_POST[$texttype]))  {
		$amain[$texttype] = wp_kses($_POST[$texttype], ameta_allowed_html());	
	}
	else $amain[$texttype] =  '';
	return true;
}
 
function ameta_allowed_html () {
//	return ('<p><br /><hr /><h2><h3><<h4><h5><h6><strong><em>');
	return (array(
		'br' => array(),
		'em' => array(),
		'span' => array(),
		'h1' => array(),
		'h2' => array(),
		'h3' => array(),
		'h4' => array(),
		'h5' => array(),
		'h6' => array(),
		'strong' => array(),
		'p' => array(),
		'abbr' => array(
		'title' => array ()),
		'img' => array('src'=>array(), 'alt'=>array() ),
		'acronym' => array(
			'title' => array ()),
		'b' => array(),
		'blockquote' => array(
			'cite' => array ()),
		'cite' => array (),
		'code' => array(),
		'del' => array(
			'datetime' => array ()),
		'em' => array (), 'i' => array (),
		'q' => array(
			'cite' => array ()),
		'strike' => array(),
		'div' => array()

		)); 
	}
 
function amr_load_scripts () {
	wp_enqueue_script('jquery');		 
}	

function amr_load_fields_scripts() {  //20211129 not needed
	//wp_enqueue_script( 'amr-check-fieldtype',  plugins_url('/js/amr-check-fieldtype.js',__FILE__ ), array( 'jquery' ), '1.0', true );		
}
	
function amrmeta_validate_names()	{ /*  the names of lists */
	global $amain;

	if (is_array($_POST['name']))  {
		foreach ($_POST['name'] as $i => $n) {		/* for each list */	
			// sanitize_text_field does more than wp_kses($n); //20220327
			$amain['names'][$i] = sanitize_text_field($n);	
		}
		return (true);
	}
	else { 
		$cache = new adb_cache();
		$cache_error = $cache->get_error('nonamesarray');
		amr_flag_error ($cache_error);
		return (false);
	}	
}	
	
function ausers_submit () {	
	return ('
	<p style="clear: both;" class="submit">
		<input class="button-primary" type="submit" name="update" value="'. __('Update', 'amr-users') .'" />
		 &nbsp;  &nbsp;  &nbsp;  &nbsp; 
		<input type="submit" name="reset" class="button"  value="'. __('Reset all options', 'amr-users') .'" />
		<input type="hidden" name="action" value="save" />
	</p>');
	}
		 
function alist_update () {	
	return ('
	<p class="clear submit">
		<input type="hidden" name="action" value="save" />
		<input class="button-primary" type="submit" name="update" value="'. __('Update', 'amr-users') .'" />
	</p>');
	}
 
function alist_rebuild () {	
	return ('<p style="clear: both;" class="submit">
			<input type="submit" class="button-primary" name="rebuildback" value="'.__('Rebuild cache in background', 'amr-users').'" />
			</p>');
	}
 
function alist_rebuildreal ($i=1) {	
	return (PHP_EOL.'<div class="clear"></div><!-- end class clear -->'.PHP_EOL.'<div><h3>'
		.'</h3>'.__('For large databases, rebuilding in realtime can take a long time. Consider running a background cache instead.','amr-users').'<p>'
		.__('If you choose realtime, keep the page open after clicking the button.','amr-users').'</p>'
		.'<div style="clear: both; padding: 20px;" class="submit">
			<input type="hidden" name="rebuildreal" value="'.$i.'" />
			<input type="submit" name="rebuild" value="'.__('Rebuild in realtime', 'amr-users').'" />
			<input type="submit" class="button-primary" name="rebuildback" value="'.__('Rebuild in background', 'amr-users').'" />
			</div><!-- end  -->'.PHP_EOL
			);
	}
 
function amr_rebuildwarning ( $list ) {
	
	$logcache = new adb_cache();

	if ($logcache->cache_in_progress($logcache->reportid($list,'user'))) {
		$text = sprintf(__('Cache of %s already in progress','amr-users'),$list);
		$logcache->log_cache_event($text);
		echo $text;
		return;
	}	
	else {
		$text = $logcache->cache_already_scheduled($list);  
		if (!empty($text)) {
			$new_text = __('Report ','amr-users').$list.': '.$text;
			$logcache->log_cache_event($new_text); 
			amr_users_message($new_text);	
			//return;	 - let it run anyway
		}
	}	
	echo alist_rebuildreal($list);	
	return;
	
	}
 
function amr_userlist_submenu ( $listindex ) {
	global $amain;
	//echo PHP_EOL.'<div class="clear"> ';
	//echo '<b>'.sprintf(__('Configure list %s: %s','amr-users'),$listindex,$amain['names'][$listindex]).
	echo ' &nbsp; '.
		au_manage_fields_link()
		.' | '.au_overview_link()
		.' | '.au_buildcache_view_link(__('Rebuild cache now','amr-users'),$listindex,$amain['names'][$listindex])
		.' | '.au_headings_link($listindex,$amain['names'][$listindex])
/*		.' | '.au_filter_link($listindex,$amain['names'][$listindex])
		.' | '.au_custom_nav_link($listindex,$amain['names'][$listindex])
		.' | '.au_grouping_link($listindex,$amain['names'][$listindex])
		.' | '.au_view_link(__('View','amr-users'), $listindex,$amain['names'][$listindex])
*/		.' | '.au_lists_link(__('View','amr-users'),$listindex,$amain['names'][$listindex],'userlists')
;
//		.'</b>';
//		.'</div>';
}
 
function au_overview_link() {
	global $ausersadminurl;
	$t = '<a href="'
	.wp_nonce_url(add_query_arg(
	array('page'=>'ameta-admin-overview.php',
		'tab'=>'settings'),''),'amr-meta')   //20210317   for list level settings best to go straight to settings tab
		.'" title="'.__('List level settings','amr-users').'" >'.__('List level settings','amr-users').'</a>';
	return ($t);
} 

function au_manage_fields_link() {
	global $ausersadminurl;
	$t = '<a href="'
	.wp_nonce_url(add_query_arg('page','ameta-admin-nice-names.php',''),'amr-meta').'" title="'.__('Manage field settings for all lists (exclude, or specify type)','amr-users').'" >'.__('Manage fields for all lists','amr-users').'</a>';
	return ($t);
}
 
function au_add_userlist_page($text, $i,$name) {
global $ausersadminurl;	
	$url = admin_url('post-new.php?post_type=page&post_title='.__('Members', 'amr-users').'&content=[userlist list='.$i.']');
	$t = PHP_EOL.'<a style="color:green;" href="'.wp_nonce_url($url,'amr-meta')
		.'" title="'.__('Add a new page with shortcode for this list', 'amr-users').'" >'
		.$text
		.'</a>';
	return ($t);
}
 
function au_configure_link($text, $i,$name) {
global $ausersadminurl;	
	//working with admin url - safe, no need for esc_url
	
	$url = (add_query_arg(array('ulist' => $i, 
			'page' =>'ameta-admin-configure.php'),
			$ausersadminurl	));
	
	
	$t = PHP_EOL.'<a style="color:#D54E21;" href="'.wp_nonce_url($url,'amr-meta')
		.'" title="'.sprintf(__('Configure List %u: %s', 'amr-users'),$i, esc_attr($name)).'" >'
		.$text
		.'</a>';
	return ($t);
}
 	
function au_delete_link ($text, $i,$name) {
	$url = remove_query_arg('copylist');  // only used in admin
	
	$t = PHP_EOL.'<a href="'
		.wp_nonce_url(add_query_arg( 
			array(
			'page'=>'ameta-admin-overview.php',
			'deletelist' =>$i),
			$url),'amr-meta')
		.'" title="'.sprintf(__('Delete List %u: %s', 'amr-users'),$i, esc_attr($name)).'" >'
		.$text
		.'</a>';
	return ($t);
	}
 	
function au_copy_link ($text, $i,$name) {
	$url = (remove_query_arg('deletelist')); // only used in admin
	$t = PHP_EOL.'<a href="'.wp_nonce_url(
		add_query_arg('copylist',$i,$url),'amr-meta')
		.'" title="'.sprintf(__('Copy list to new %u: %s', 'amr-users'),$i, esc_attr($name)).'" >'
		.$text
		.'</a>';
	return ($t);
	}	
 	
function au_view_link($text, $i, $title, $class='') { // only used in admin
	$t = PHP_EOL.'<a class="'.$class.'" style="text-decoration: none;" href="'
//		.wp_nonce_url(add_query_arg('ulist',$i,'users.php?page=ameta-list.php'),'amr-meta')
		.'users.php?page=ameta-list.php?ulist='.$i
	.'" title="'.esc_attr($title).'" >'
		.esc_attr($text)
		.'</a>';
	return ($t);
}
 	
function au_csv_link($text, $i, $title) { 
//amr_users_get_csv_link($ulist,$suffix)
//global $ausersadminurl;
	$url = remove_query_arg('reqxls');
	$t = PHP_EOL.'<a style="color:#D54E21;" href="'
	.wp_nonce_url(
	add_query_arg(array('page'=>'ameta-list.php?ulist='.$i,'reqcsv'=>$i), $url),'amr-meta') // only used in admin
	.'" title="'.esc_attr($title).'" >'
		.esc_attr($text)
		.'</a>';
	return ($t);
}
 
function amru_related() {
	echo PHP_EOL.'<p>'.
	__('Related plugins are continually being developed in response to requests. They are packaged separately so you only add what you need.','amr-users')
	.'<p>';
	echo '<ul>';
	echo '<li>';
	echo '<a href="https://wpusersplugin.com/related-plugins/amr-cron-manager/" >amr cron manager</a> - ';
	_e('Improve visibility and manage the cron schedules','amr-users');
	echo '</li>';
	echo '<li>';
	echo '<a href="https://wpusersplugin.com/related-plugins/amr-users-plus/" >amr users plus</a> - ';
	_e('Adds functionality such as complex filtering','amr-users');
	echo '</li>';
	echo '<li>';
	echo '<a href="https://wpusersplugin.com/related-plugins/amr-users-plus-s2/" >amr users plus s2</a> - ';
	_e('Adds subscribers in the separate subscribe2 table to the user lists','amr-users');
	echo '</li>';
	echo '<li>';
	echo '<a href="https://wpusersplugin.com/related-plugins/amr-users-plus-cimy/" >amr users plus cimy</a> - ';
	_e('Makes the separate "cimy extra fields" table look like normal user meta data','amr-users');
	echo '</li>';
	echo '<li>';
	echo '<a href="https://wpusersplugin.com/related-plugins/amr-users-plus-ym/" >amr users plus ym</a> - ';
	_e('Adds bulk ym updates and better formatting of ym fields.','amr-users');
	echo '</li>';
	echo '<li>';
	echo '<a href="https://wpusersplugin.com/related-plugins/amr-users-multisite/" >'.__('amr users multi site','amr-users').'</a> - ';
	_e('Makes amr users operate in the network pages across the sites.','amr-users');
	echo '</li>';

	echo '</ul>';
	echo '<a href="https://wpusersplugin.com/related-plugins" >'.
	__('... there may be more.','amr-users')
	.'</a>';
	
	}
 	
function a_currentclass($page){
	if ((isset($_GET['am_page'])) and ($_GET['am_page']===$page))
	return (' class="current" ');
	else return('');
}
 	
function amr_meta_support_links () {
	echo PHP_EOL.'<ul class="subsubsub" style="float:right;">';
	echo '<li><a target="_blank" href="https://wpusersplugin.com/support">';
	_e('Support','amr-users');
	echo '</a>|</li>
	<li><a target="_blank" href="http://wordpress.org/extend/plugins/amr-users/">';
	_e('Rate it','amr-users');
		echo '</a>|</li>
	<li>
	<a target="_blank" href="https://wpusersplugin.com/feed/">';
	_e('Rss feed','amr-users');
	echo '</a>|</li>
	<li><a target="_blank" href="https://www.paypal.com/sendmoney?email=anmari@anmari.com">';
	_e('Say thanks to anmari@anmari.com','amr-users');

	echo '</a></li></ul><br/>';
}
 	
function amr_meta_main_admin_header($title, $capability='manage_options') { //capbility canbe filtered for csv so far

	echo PHP_EOL.'<div id="icon-users" class="icon32"><br/></div>'.PHP_EOL;	
	
	echo PHP_EOL.'<h2>'.$title
	.'</h2>'
	.PHP_EOL;
	
	if (!( current_user_can('manage_options') or current_user_can('manage_user_lists') or current_user_can($capability) )) 
		wp_die(__('You do not have sufficient permissions to update list settings.','amr-users'));
	
	if ((!ameta_cache_enable()) or  (!ameta_cachelogging_enable())) 
			echo '<h2>Problem creating DB tables</h2>';
}
 
function amrmeta_mainhelp($contextual_help, $screen_id, $screen) {
global $amr_pluginpage;

	if ($screen_id == $amr_pluginpage) {
		$contextual_help = '<h3>'.__('Fields and Nice Names','amr-users').'</h3>'.amrmeta_nicenameshelp();	
		$contextual_help .= '<h3>'.__('Lists','amr-users').'</h3>'.amrmeta_overview_help();
		$contextual_help .= '<h3>'.__('List Settings','amr-users').'</h3>'.amrmeta_confighelp();

		return $contextual_help;
	}
	if ($screen_id == 'ameta-admin-configure.php') {
		$contextual_help .= '<h3>'.__('List Settings','amr-users').'</h3>'.amrmeta_confighelp();
		return $contextual_help;
	}
}
 
function amrmeta_overview_help() {
	
	$contextual_help = 
	'<h3>'.__('Lists','amr-users').'</h3>'
	.'<ol><li>'.__('Defaults lists are provided as examples only.  Please configure them to your requirements.', 'amr-users').'</li><li>'

	.__('Update any new list details and configure the list.', 'amr-users').'</li><li>'
	.__('Each new list is copied from the last configured list.  This may be useful if configuring a range of similar lists - add the lists one by one - slowly incrementing the number of lists.', 'amr-users').'</li>'
	.'<li>'
	.__('List settings from compatible systems can be imported', 'amr-users').'</li>'
	.'</ol>';

	return $contextual_help;
	}
 
function amr_rebuild_in_realtime_with_info ($list) {  // nlr ?
	if (amr_build_user_data_maybe_cache ($list)) {; 
		echo '<div class="update">'.sprintf(__('Cache rebuilt for %s ','amr-users'),$list).'</div>'; /* check that allowed */
		echo au_view_link(__('View Report','amr-users'), $list, __('View the recently cached report','amr-users'));
	}
	else echo '<div class="update">'.sprintf(__('Check cache log for completion of list %s ','amr-users'),$list).'</div>'; /* check that allowed */
}
 
function amru_on_load_page() {
	global $pluginpage;
		//ensure, that the needed javascripts been loaded to allow drag/drop, expand/collapse and hide/show of boxes
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');

		//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore

	}
 
function amr_remove_footer_admin () {
	echo '';
	}	
 
function au_grouping_link($i,$name='') {
global $ausersadminurl,$ausersadminusersurl;		
	if (!function_exists('amr_grouping_admin_form')) {
			return ('<a href="https://wpusersplugin.com/related-plugins/amr-users-plus-grouping/" '.
			'title="'
			.__('Activate or acquire amr-user-plus-grouping addon for listing users in a group by any field','amr-users').'" ' 
			.'>'
			.__('Edit grouping','amr-users').'</a>');
	}
	
 
	$url = $ausersadminurl.'?page=ameta-admin-configure.php';
	$url = esc_url(add_query_arg(array(
		'grouping'=>1,
		'ulist'=>$i), $url));
//		
	
	$t = '<a style="color:#D54E21; " href="'
//		.wp_nonce_url($url,'amr-meta')
		.$url
		.'" title="'.sprintf(__('Grouping %u: %s', 'amr-users'),$i, esc_attr($name)).'" >'
		.__('Edit grouping', 'amr-users')
		.'</a>';
	return ($t);
}
 
function au_custom_nav_link($i,$name='') {
global $ausersadminurl, $ausersadminusersurl;		
	if (!function_exists('amr_custom_navigation_admin_form')) {
			return ('<a style="color: #AAAAAA;" href="https://wpusersplugin.com/related-plugins/amr-users-plus/" '.
			'title="'.__('Activate or acquire amr-user-plus addon for custom (eg: alphabetical) navigation','amr-users').'" ' 
			.'>'
			.__('Edit navigation', 'amr-users').'</a>');
	}
	$url = add_query_arg(array('ulist'=>$i), 
		$ausersadminurl.'?page=ameta-admin-configure.php');	

	if (isset($_REQUEST['custom_navigation']) ) { 
					
		return ('<b><a style="color: #006600;" href="'.esc_url($url)
		.'">'.__('Exit navigation', 'amr-users').'</a></b>');
	}
	
	$url = esc_url(add_query_arg(array(
		'custom_navigation'=>1), $url));
//		
	
	$t = '<a style="color:#D54E21; " href="'
		.$url
		.'" title="'.sprintf(__('Custom navigation %u: %s', 'amr-users'),$i, esc_attr($name)).'" >'
		.__('Edit navigation', 'amr-users')
		.'</a>';
	return ($t);
}
 	
function au_headings_link( $i) {
global $ausersadminurl,$ausersadminusersurl;
	$url = $ausersadminusersurl.'?page=ameta-list.php?ulist='.$i; 
	// doesn't like add_query_arg for ulist somehow
	//$url = add_query_arg(array( 'ulist' => $i),$url); 
	
	$url = admin_url('users.php?page=userlists'); //20210312

	if (isset($_REQUEST['headings'])) {
		$url = wp_nonce_url($url,'amr-meta');
		return ('<a href="'.$url
		.'">'.__('Exit headings', 'amr-users').'</a>');
	}
		
	$url = add_query_arg(array( 'headings' => 1, 'list'=>$i),$url); 	
	$url = wp_nonce_url($url,'amr-meta');
	$t = '<a style="color:#D54E21;" href="'
		.$url
		.'" title="'.sprintf(__('Edit the column headings %u: %s', 'amr-users'),$i, '').'" >'
		.__('Edit headings', 'amr-users')
		.'</a>';
	return ($t);
}

function amr_users_dropdown_form ($choices, $current) {
	?><div class="alignleft actions"><select id="list-tab" name="ulist"><?php
	amr_users_dropdown ($choices, $current);
	?></select>
	<input name="select" id="getlist" class="button" value="<?php _e('Select','amr-users'); 
	?>" type="submit"></div>
<?php
	
}

function amr_users_do_tabs ($tabs, $current_tab) {
global $_wp_admin_css_colors;
	$user_id  = get_current_user_id();
	$current_color = get_user_option( 'admin_color', $user_id );
	if (!$current_color) 
		$color = 'lightgrey';
	else
		$color = $_wp_admin_css_colors[$current_color]->colors[2]; 

	// check for tabs  
	    // display the icon and page title  
    echo '<div id="icon-options-general" class="icon32"><br /></div>';  
	if ($tabs !='') {  
		// wrap each in anchor html tags  
		$links = array();  
		foreach( $tabs as $tab => $name ) {  
			// set anchor class  
			if ($tab == $current_tab ) {
				$class	=  'nav-tab nav-tab-active' ;
				$style	= 'background-color: '.$color.';'; 		
			}
			else {
				$class = 'nav-tab';
				$style = '';
			}
			$page       = sanitize_text_field($_GET['page']);  
			// the link  
			$links[]    = "<a class='$class' style='$style' href='?page=$page&tab=$tab'>".esc_attr($name)."</a>";  
		}  
	  
		echo PHP_EOL.'<h2 class="nav-tab-wrapper">';  
			foreach ( $links as $link ) {  
				echo $link;  
			}  
		echo '</h2>'.PHP_EOL;  
	} 
}

function amr_users_do_tabs_config ($tabs, $current_tab) {
	global $amr_current_list;
	// check for tabs  
	    // display the icon and page title  
			
	echo '<div class="clear"> </div>';	
	if ($tabs !='') {  
		
		// wrap each in anchor html tags  
		$links = array();  
		foreach( $tabs as $ctab => $name ) {  
			// set anchor class  
			$class      = ($ctab == $current_tab ? 'nav-tab nav-tab-active' : 'nav-tab');  
			$page       = sanitize_text_field($_GET['page']);  
	
			$links[]    = "<a class='$class' href='?page=$page&config=$ctab&ulist=$amr_current_list'>".esc_attr($name)."</a>";  
		}  
	  
		echo PHP_EOL.'<h2 class="nav-tab-wrapper">';  
			foreach ( $links as $link ) {  
				echo $link;  
			}  
		echo '</h2>'.PHP_EOL;  
	} 
}
 
function amrmeta_about_page() {
	global $aopt;
	global $amr_nicenames;
	global $pluginpage;
	global $amain;
		
	//amr_meta_main_admin_header('About amr user lists'.' (version:'.AUSERS_VERSION.')');
	$tabs['general'] = __('General','amr-users');
	$tabs['about'] = __('About','amr-users').' ('.AUSERS_VERSION.')';
	$tabs['userdb'] = __('Your user db', 'amr-users');
	$tabs['news'] = __('News', 'amr-users');
	
	if (empty ($_GET['tab']) or  ($_GET['tab'] == 'general') ){
			amr_users_do_tabs ($tabs,'general');
			amr_meta_general();
			return;
		}			
	elseif ($_GET['tab'] == 'userdb') {
			amr_users_do_tabs ($tabs,'userdb');
			amr_meta_test_your_db_page();
			return;
		}
	elseif ($_GET['tab'] == 'news') {
			amr_users_do_tabs ($tabs,'news');
				
			echo '<h2>'.__('News', 'amr-users').'</h2>';

			amr_users_feed('https://wpusersplugin.com/feed/', 3, __('amr wpusersplugin news', 'amr-users'));
			amr_users_feed('http://webdesign.anmari.com/feed/', 3, __('other anmari news', 'amr-users'));
			return;
		}
		
	amr_users_do_tabs ($tabs,'about');
	amr_meta_support_links ();
	amr_meta_admin_headings ($plugin_page=''); // does the nonce check etc
	
	echo '<p><h3>'.__('Shortcodes to add to pages:', 'amr-users').'</h3></p>'
	.'<p><span style="color:green;">&nbsp;  [userlist] &nbsp;&nbsp;or &nbsp;&nbsp;[userlist list=n]</span></p>';

	echo '<h3>'.__('Fields and Nice Names', 'amr-users').'</h3>'.amrmeta_nicenameshelp();
	echo amrmeta_overview_help();
	echo '<h3>'.__('List Settings','amr-users').'</h3>'.amrmeta_confighelp();

	echo ausers_form_end();

}
 
//styling options page
function ameta_admin_style() {

?>
<!-- Admin styles for amr-users settings screen - admin_print_styles trashed the admin menu-->
<style type="text/css" media="screen">

.widefat tbody td.check-column {
	padding: 0 0 0 6px;
	margin: 0 0 0 8px;
}

table th.show {
	width: 20px;
}

legend {
	  font-size: 1.1em;
	  font-weight: bold;
}  
label { 
	cursor: auto;
	display: block;
	float: left;
	width: 200px;
 }
.widefat li label {
	width: 500px;
}
form label.lists {
	display: block;  /* block float the labels to left column, set a width */
	clear: left;
	float: left;  
	text-align: right; 
	width:40%;
	margin-right:0.5em;
	padding-top:0.2em;
	padding-bottom:1em;
	padding-left:2em;
 }
.userlistfields th a { cursor: help;}

.if-js-closed .inside {
	display:none;
}
.subsubsub span.step {
	font-weight: bold;
	font-size: 1.5em;
	color: green;
}
.tooltip {
  cursor: help; text-decoration: none;
}

</style>
	
<?php
}
