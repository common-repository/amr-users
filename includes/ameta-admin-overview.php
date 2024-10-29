<?php
function amr_delete_list ($old) { //20201015
global $amain, $aopt;
//delete an existing list 
	$optname = 'amr-users-main';
	$option = ausers_get_option($optname);
	foreach ($option as $setting => $lists) {
		if (is_array($lists)) { 	
			if (isset($option[$setting][$old])) {
				//echo '<br />unset '.$setting.' for ' .$old;
				unset($option[$setting][$old]);
			}
		}
	}
	$result = ausers_update_option($optname, $option);
	
	$optname = 'amr-users';
	$option = ausers_get_option($optname); 
	unset($option['list'][$old]);
	ausers_update_option($optname, $option);

	$optname = 'amr-users-filtering';
	$option = ausers_get_option($optname);
	if (isset($option[$old])) {
		unset($option[$old]);
		ausers_update_option($optname, $option);
	}	
	
	$optname = 'amr-users-custom-headings';
	$option = ausers_get_option($optname);
	if (isset($option[$old])) {
		unset($option[$old]);
		ausers_update_option($optname, $option);
	}
	
	$optname = 'amr-users-menu-order';
	$option = ausers_get_option($optname);
	if (is_array($option)) {
		$key = array_search($old, $option);
		unset($option[$key]);
		ausers_update_option($optname, $option);
	}
	
	$amain = ausers_get_option('amr-users-main');
	$aopt = ausers_get_option('amr-users');
	$cache = new adb_cache();
	$deleted = $cache->clear_cache ($old);
}

function amr_copy_list ($old, $copy) { //20201015
//copy an existing list to a new list IF new list number does not exist already
	global $amain, $aopt;
	
	if (!empty($amain['names'][$copy])) {
		echo 'List '.$copy.' already exists as'. $amain['names'][$copy]; 
		return;
	}
	else echo '<br />Copy list '.$old.' to '.$copy;
	//$amain['no-lists'] = count($amain['names']);
	//ausers_update_option ('amr-users-main', $amain);	
	$option = $amain;
	foreach ($option as $setting => $lists) { //echo '<br />'.$setting;
		if (is_array($lists) and !($setting == 'menuorder' )) { 	// menuorder not keyed by listid 
			if (isset($option[$setting][$old])) {
				$option[$setting][$copy] = $option[$setting][$old];
			}
			//ksort($option[$setting]); 
		}
	}
	
	//now update the name to show it is a copy
	$option['names'][$copy] .=  __(' - copy','amr-users');

	$result = ausers_update_option('amr-users-main', $option);
	
	$optname = 'amr-users';
	$option = ausers_get_option($optname); 
	$option['list'][$copy] = $option['list'][$old];
	ausers_update_option($optname, $option);

	$optname = 'amr-users-filtering';
	$option = ausers_get_option($optname);
	if (isset($option[$old])) {
		$option[$copy] = $option[$old];
		ausers_update_option($optname, $option);
	}	
	
	$optname = 'amr-users-custom-headings';
	$option = ausers_get_option($optname);
	if (isset($option[$old])) {
		$option[$copy] = $option[$old];
		ausers_update_option($optname, $option);
	}
	$optname = 'amr-users-menu-order';
	$option = ausers_get_option($optname);
	$option[] = $copy; // add to the end of the menu
	ausers_update_option($optname, $option);
	
}
	
function amr_handle_copy_delete () {	
	global $amain;
	if (!(current_user_can('manage_options') or current_user_can('manage_user_lists'))) { //20210314
		_e('Inadequate access','amr-users');
		return;
	}
	
	if (isset($_GET['copylist'])) {  	
		$source = (int) $_REQUEST['copylist'];
		if (!isset($amain['names'][$source])) {
			echo 'Error copying list '.$source; 
			return false;
		}
		$next = 1;  // get the current max index
		foreach($amain['names'] as $listid =>$name) { 
			$next = max($next,$listid);
			//echo '<br />Next = '.$next.' vs '.$listid;
		}
		$next = $next +1;
		//echo '<br />Next = '.$next;
		//
		amr_copy_list ($source, $next);
		$amain = ausers_get_option ('amr-users-main');		
		//debug_this($amain['names'], 'names after copy ');														   
	}
	elseif (isset($_GET['deletelist'])) { // we not deleteing all but so long as main is gone, when they click update on other settings,the other bits will disappear
		$old = (int) $_REQUEST['deletelist'];
		amr_delete_list ($old);
		amr_users_message(__('List deleted.','amr-users').' '.$old);
	}
}	

function amr_handle_add_new () {
	global $amain;
	

	if (isset($_REQUEST['addnew'])) {  		
		if ((count ($amain['names'])) < 1)
			$amain['names'][1] = __('New list','amr-users');
		else 
			$amain['names'][] = __('New list','amr-users');
		$amain['no-lists'] = count ($amain['names']);
		ausers_update_option ('amr-users-main', $amain);
		$listno = array_key_last($amain['names']) ;
		//echo '<p>Setting up defaults</p>';
		//20210415 ameta_new_list_defaults($listno);
		//debug_this($amain, ' after new');
	}	
}

function amrmeta_validate_overview()	{ 
	global $amain;
	global $aopt;

	if (isset($_POST['name'])) {
		$return = amrmeta_validate_names();
		if ( is_wp_error($return) )	echo $return->get_error_message();
	}

	$amain['version'] = AUSERS_VERSION;
	
	if (isset($_POST)) {	
		ausers_update_option ('amr-users-main', $amain);
	}
	amr_users_message(__('Options Updated', 'amr-users'));			
	return;
}

function amrmeta_validate_list_settings()	{ 
	global $amain;
	global $aopt;

	if (isset($_POST['checkedpublic'])) { /* admin has seen the message and navigated to the settings screen and saved */
		$amain['checkedpublic'] = true;
	}

	foreach ($amain['names'] as $i=>$n) { // clear booleans in case not set
		//if ((!isset($_GET['list'])) or ($_GET['list'] == $i)) { // in case we are only doing 1 list - insingle view - but maybe we no longer need this
			$amain['show_search'][$i] = false;
			$amain['show_perpage'][$i] = false;
			$amain['show_pagination'][$i] = false;
			$amain['show_headings'][$i] = false;
			$amain['show_csv'][$i] = false;
			$amain['show_xls'][$i] = false;
			$amain['show_refresh'][$i] = false;
			$amain['is_public'][$i] = false;
			$amain['customnav'][$i] = false;
			$amain['sortable'][$i] = false;
			$amain['show_in_menu'][$i] = false;
			$amain['show_totals'][$i] = false;
		//}
	}
		
	if (isset($_POST['list_avatar_size'])) {	
		if (is_array($_POST['list_avatar_size']))  {
			foreach ($_POST['list_avatar_size'] as $i=>$value) 
				$amain['list_avatar_size'][$i] = ( int) $value;
		}
	}
	
	if (isset($_POST['rows_per_page'])) {
		$amain['rows_per_page'] = array();
		if (is_array($_POST['rows_per_page']))  {
			foreach ($_POST['rows_per_page'] as $i=>$value) 
				$amain['rows_per_page'][$i] = ( int) $value;
		}
	}
	
	if (isset($_POST['html_type'])) {	
		if (is_array($_POST['html_type']))  {
			foreach ($_POST['html_type'] as $i=>$value) {
				if (in_array( $value, array('table','simple'))) {
					$amain['html_type'][$i] =  $value;					
				}	
			}
		}
	}

	if (isset($_POST['filter_html_type'])) {	
		if (is_array($_POST['filter_html_type']))  {
			foreach ($_POST['filter_html_type'] as $i=>$value) {
				if (in_array( $value, array('intableheader','above','none'))) {
					$amain['filter_html_type'][$i] =  $value;					
				}	
			}
		}
	}
//	
		$addon_settings = apply_filters('amr-users-addon-settings', array()); //20150820
		if (!empty($addon_settings)) { //20150820
			foreach ($addon_settings as $setting) {	
				foreach ($amain['names'] as $i=>$n) {
					$amain[$setting['name']][$i] = false;
				}	
			}
		}
	
	if (isset($_POST['sortable'])) {	
		if (is_array($_POST['sortable']))  {
			foreach ($_POST['sortable'] as $i=>$y) 
				$amain['sortable'][$i] = true;
		}
	}
	if (isset($_POST['is_public'])) {	
		if (is_array($_POST['is_public']))  {
			foreach ($_POST['is_public'] as $i=>$y) 
				$amain['is_public'][$i] = true;
		}

	}
	if (isset($_POST['show_in_menu'])) {	
		if (is_array($_POST['show_in_menu']))  { 
			foreach ($_POST['show_in_menu'] as $i=>$y) 
				$amain['show_in_menu'][$i] = true;
		}
	}
	
	if (!empty($addon_settings)) { //20150820
		foreach ($addon_settings as $setting) {		
			if (isset($_POST[$setting['name']])) { // else all unticked			
				foreach ($_POST[$setting['name']] as $i=>$y)
					$amain[$setting['name']][$i] =true;
			}
			else $amain[$setting['name']] = array();
		}
	}		
	if (isset($_POST['show_search'])) {	
		if (is_array($_POST['show_search']))  {
			foreach ($_POST['show_search'] as $i=>$y) 
				$amain['show_search'][$i] = true;
		}
	}
	if (isset($_POST['customnav'])) {	
		if (is_array($_POST['customnav']))  {
			foreach ($_POST['customnav'] as $i=>$y) 
				$amain['customnav'][$i] = true;
		}
	}
	if (isset($_POST['show_perpage'])) {	
		if (is_array($_REQUEST['show_perpage']))  {
			foreach ($_REQUEST['show_perpage'] as $i=>$y) 
				$amain['show_perpage'][$i] = true;
		}
	}
	if (isset($_POST['show_pagination'])) {	
		if (is_array($_REQUEST['show_pagination']))  {
			foreach ($_REQUEST['show_pagination'] as $i=>$y) 
				$amain['show_pagination'][$i] = true;
		}
	}
	if (isset($_POST['show_headings'])) {	
		if (is_array($_REQUEST['show_headings']))  {
			foreach ($_REQUEST['show_headings'] as $i=>$y) 
				$amain['show_headings'][$i] = true;
		}
	}
	if (isset($_POST['show_csv'])) {	
		if (is_array($_REQUEST['show_csv']))  {
			foreach ($_REQUEST['show_csv'] as $i=>$y) 
				$amain['show_csv'][$i] = true;
		}
	}
	if (isset($_POST['show_xls'])) {	
		if (is_array($_REQUEST['show_xls']))  { 
			foreach ($_REQUEST['show_xls'] as $i=>$y) 
				$amain['show_xls'][$i] = true;
		}
	}
	if (isset($_POST['show_refresh'])) {	
		if (is_array($_REQUEST['show_refresh']))  {
			foreach ($_REQUEST['show_refresh'] as $i=>$y) 
				$amain['show_refresh'][$i] = true;
		}
	}
	if (isset($_POST['show_totals'])) {	  //20210317 add validation of show totals
		if (is_array($_REQUEST['show_totals']))  {
			foreach ($_REQUEST['show_totals'] as $i=>$y) 
				$amain['show_totals'][$i] = true;
		}
	}		
	$amain['version'] = AUSERS_VERSION;
	
	if (isset($_POST)) {	
		ausers_update_option ('amr-users-main', $amain);
	}
	
	amr_users_message(__('Options Updated', 'amr-users'));	
	return;
}

function amr_setting_checkbox($setting_name, $i, $status, $disabled='') { 
		$html = checked($status,true,false);   
		$html = '<input type="checkbox" id="'.$setting_name
			.$i.'" name="'.$setting_name.'['. $i .']" value="1" '.$html.$disabled.'/.>';
		//if (!empty($status)) echo 'checked="Checked"'; 
		return $html;
}

function amr_echo_setting_html($setting_name, $i, $disabled='') { 
	global $amain;
		if (empty($amain[$setting_name][$i])) 
			$status = false;
		else 
			$status = $amain[$setting_name][$i];
		
		echo PHP_EOL.'<td>'.amr_setting_checkbox($setting_name, $i, $status, $disabled).'</td>';
		//<input type="checkbox" id="'.$setting_name 			.$i.'" name="'.$setting_name.'['. $i .']" value="1" ';
		//if (!empty($status)) echo 'checked="Checked"'; 
		//echo '/></td>';
}

function amr_echo_setting_heading_html($title, $text, $class='') { // 201608
		echo PHP_EOL.'<th '.$class.'>'.$title;
		echo ' '.$text.'</th>';
}

function amr_echo_submit_add_new() {
		echo PHP_EOL.'<p style="clear:both;" class="submit">
		<input type="hidden" name="action" value="save" />
		<input class="button-primary" type="submit" name="update" value="'. __('Update', 'amr-users') .'" />
		 &nbsp;  &nbsp;  &nbsp;  &nbsp; 
		 <input class="button-primary" type="submit" name="addnew" value="'. __('Add new', 'amr-users') .'" />'.PHP_EOL.'</p>';
}

function amr_meta_overview_form () { 
	global $amain, $aopt;
	
	amr_echo_submit_add_new();
	
	echo PHP_EOL.'<table style="width: auto;" class="widefat" >';
	foreach (array('thead','tfoot') as $section) {
		echo PHP_EOL.'<'.$section.'><tr>';
		amr_echo_setting_heading_html(	__('Name of List', 'amr-users'), '', 'style="padding-left:10px;"');
		amr_echo_setting_heading_html(	__('Shortcode', 'amr-users'), '', 'style="width: 100px; padding-left:10px;"');
		amr_echo_setting_heading_html(	__('Actions', 'amr-users'), '', 'style="padding-left:10px;"');	
		echo PHP_EOL.'</tr></'.$section.'>'.PHP_EOL;
	}
	
	foreach ($amain['names'] as $i => $name) {
		$n = esc_attr($amain['names'][$i]);  //20220327
		echo PHP_EOL.'<tr>';
		echo PHP_EOL.'<td>';  
		echo PHP_EOL.'<input type="text" size="30" id="name'.$i.'" name="name['. $i.']"  value="'.$n.'" />';  //20220327
		echo '</td>';
		echo '<td >';
		
		if (!is_network_admin()) {
			echo au_add_userlist_page('[userlist list='.$i.']', $i,$n);
		}
		else 
			echo '[userlist&nbsp;list='.$i.']';	
		echo '</td>';

		echo PHP_EOL.'<td>';
		echo PHP_EOL.au_lists_link(__('View','amr-users'),$i,$n,'userlists').' '  //20210312
			.PHP_EOL.au_configure_link(__('Configure','amr-users'),$i,$n).' '
			.PHP_EOL.au_copy_link(__('Copy','amr-users'),$i,$n).' '
			.PHP_EOL.au_delete_link(__('Delete','amr-users'),$i,$n).' ';
		echo '</td></tr>';	
	}
	echo PHP_EOL.'</table>'.PHP_EOL;	

	echo ausers_form_end();
}

function amr_userlist_settings () { 
	global $amain, $aopt, $amr_current_list ;
	
	if (is_plugin_active('amr-users-plus/amr-users-plus.php')) {
		$greyedout = '';
		$plusstatus = '';			
	}	
	else {
		$greyedout = ' style="color: #AAAAAA;" ';
		$plusstatus = ' disabled="disabled"';
	}
	
	if (!(isset ($amain['checkedpublic']))) { // no public lists
		echo '<input type="hidden" name="checkedpublic" value="true"/>';
	}

	echo '<div style="overflow-x: scroll;">';
	echo PHP_EOL.'<table id="ameta-lists" class="widefat striped" ><tr>';	
	'<tr>'.PHP_EOL;

	amr_echo_setting_heading_html(	__('->', 'amr-users'), ''); 

	amr_echo_setting_heading_html(	__('Public Html Type', 'amr-users'), ''); 		
	amr_echo_setting_heading_html(	__('Public', 'amr-users',' <a class="tooltip" href="#" title="'
	.__('List may be viewed in public pages', 'amr-users').'">?</a>'),''); 		
		
	amr_echo_setting_heading_html(	__('In Menu', 'amr-users'), '<a class="tooltip" href="#" title="'
	.__('Show as separate admin menu option for this', 'amr-users').'">?</a>',$greyedout);
	amr_echo_setting_heading_html(	__('Alpha nav.', 'amr-users'), '<a class="tooltip"  href="#" title="'.
	__('Show custom navigation to find users. ', 'amr-users').__('Requires the amr-users-plus addon.', 'amr-users') .'">?</a>', $greyedout);
	amr_echo_setting_heading_html(	__('Filtering Location', 'amr-users'), '<a class="tooltip"  href="#" title="'
	.__('Show filtering. ', 'amr-users').__('Requires the amr-users-plus addon.', 'amr-users') .'">?</a>', $greyedout
	);
	amr_echo_setting_heading_html(	__('Avatar size', 'amr-users'), 
	'<a class="tooltip" title="gravatar size info" href="http://en.gravatar.com/site/implement/images/">?</a>');	

	amr_echo_setting_heading_html(	__('Rows per page', 'amr-users'), ''); 

	
	amr_echo_setting_heading_html(	__('Per page', 'amr-users'), '<a class="tooltip" href="#" title="'
	.__('If list is public, show per page option.', 'amr-users').'">?</a>');			
	
	amr_echo_setting_heading_html(	__('Search', 'amr-users'), ' <a class="tooltip" href="#" title="'.
	__('If list is public, show user search form.', 'amr-users').'">?</a>');
	amr_echo_setting_heading_html(	__('Head ings', 'amr-users'), '<a class="tooltip" href="#" title="'.
		__('If list is public, show column headings.', 'amr-users').'">?</a>');		
		
	amr_echo_setting_heading_html(	__('Xls link', 'amr-users'), '<a class="tooltip" href="#" title="'.
	__('If list is public, show a link to xls export file', 'amr-users').' '.__('Requires the amr-users-plus addon.', 'amr-users').'">?</a>', $greyedout);
	
	amr_echo_setting_heading_html(	__('Csv link', 'amr-users'), '<a class="tooltip" href="#" title="'.
		__('If list is public, show a link to csv export file', 'amr-users').'">?</a>');	

	
	amr_echo_setting_heading_html(	__('Sort', 'amr-users'), '<a class="tooltip" href="#" title="'.
	__('Offer sorting of the cached list by clicking on the columns.', 'amr-users').'">?</a>');	
	amr_echo_setting_heading_html(	__('Paging', 'amr-users'), '<a class="tooltip" href="#" title="'.
		__('If list is public, show pagination, else just show top n results.', 'amr-users').'">?</a>');	

	amr_echo_setting_heading_html(	__('Refresh', 'amr-users'), '<a class="tooltip" href="#" title="'.
		__('If list is public, show a link to refresh the cache', 'amr-users').'">?</a>');	
		
	amr_echo_setting_heading_html(	__('Totals', 'amr-users'), '<a class="tooltip" href="#" title="'.
		__('Show total records', 'amr-users').'">?</a>');	

	$addon_settings = apply_filters('amr-users-addon-settings', array()); //we don't appaer to have any?
	if (!empty($addon_settings)) { //20150820	
		foreach ($addon_settings as $setting) {			
			amr_echo_setting_heading_html($setting['text'], ' <a class="tooltip" href="#" title="'
			.$setting['helptext']. '">?</a>');			
			}
	}
//	
	echo PHP_EOL.'</tr>'.PHP_EOL.'<tr>'.PHP_EOL;
	
	foreach ($amain['names'] as $i => $name) {		
		
		echo PHP_EOL.'<th id="list'.$i.'" style="width: auto; min-width:3em;">';
		echo au_lists_link($i, $i,__('View','amr-users').' '.$name,'userlists'); //20210312
		echo PHP_EOL.'</th>';	

		echo PHP_EOL.'<td align="left">';
		if (empty($amain['html_type'][$i])) 
			$amain['html_type'][$i] = 'table';
		foreach (array('table','simple') as $type) {
			echo '<input type="radio" id="html_type'.$i.'" name="html_type['. $i .']" value="'.$type.'" ';
			if (($amain['html_type'][$i]) == $type) echo 'checked="Checked"'; 
			echo '/>';
			_e($type);
			echo '<br />';
		}
		echo PHP_EOL.'</td>';	
	
		amr_echo_setting_html('is_public',$i);
		amr_echo_setting_html('show_in_menu', $i, $plusstatus);
		amr_echo_setting_html('customnav', $i, $plusstatus);
	
		echo PHP_EOL.'<td align="left">';
		if (empty($amain['filter_html_type'][$i])) 
			$amain['filter_html_type'][$i] = 'none';	
		foreach (array(
				'above' 		=> __('above','amr-users'), 
				'none' 			=> __('-','amr-users'),
				'intableheader' => __('in table','amr-users')) as $val => $type) {
				echo '<input type="radio" id="filter_html_type'.$i.'" name="filter_html_type['. $i .']" value="'.$val.'" '
				.$plusstatus ;
				if (($amain['filter_html_type'][$i]) == $val) echo 'checked="Checked"'; 
				echo '/>';
				echo $type;
				echo '<br />';
			}
		echo '</td>';	
		
		if (empty($amain['avatar_size'])) $amain['avatar_size'] = 10;
		if (empty($amain['list_avatar_size'][$i])) 
			$amain['list_avatar_size'][$i] = $amain['avatar_size'];	
			echo PHP_EOL.'<td><input type="text" size="3" id="avatar_size'
			.$i.'" name="list_avatar_size['. $i.']"  value="'.$amain['list_avatar_size'][$i].'" /></td>';
			
		if (empty($amain['rows_per_page'])) { //20201022 if settings mucked up
			$amain['rows_per_page'] = array($i=>10 ); 
		}
		elseif (empty($amain['rows_per_page'][$i])) {
			if (!is_array($amain['rows_per_page'])) // 20200504 if we havent converted rows per page well or they reloaded old settings
				$amain['rows_per_page'] = array($i=>$amain['rows_per_page'] );
			else 
				$amain['rows_per_page'][$i] = '10';
		}
		echo PHP_EOL.'<td><input type="text" size="3" id="rows_per_page'
			.$i.'" name="rows_per_page['. $i.']"  value="'.$amain['rows_per_page'][$i].'" /></td>';	
			
		amr_echo_setting_html('show_perpage',$i);
		amr_echo_setting_html('show_search',$i);
		amr_echo_setting_html('show_headings',$i);
		amr_echo_setting_html('show_xls',$i, $plusstatus);  // **** should this be greyed out?
		amr_echo_setting_html('show_csv',$i);
		amr_echo_setting_html('sortable',$i);
		amr_echo_setting_html('show_pagination',$i);	
		amr_echo_setting_html('show_refresh',$i);	
		amr_echo_setting_html('show_totals',$i);
		// already have $addon_settings = apply_filters('amr-users-addon-settings', array()); //20150820
		if (!empty($addon_settings)) { //20150820
			
		foreach ($addon_settings as $setting) {
/*			if (!empty($amain[$setting['name']])  and 
					(!empty($amain[$setting['name']][$i]))) {
					$value = $amain[$setting['name']][$i];
					}
			else $value = false;	
	*/			
			amr_echo_setting_html($setting['name'], $i);
			}
		}
		echo PHP_EOL.'</tr>';
	}
	echo PHP_EOL.'</table></div>'.PHP_EOL;
	echo ausers_submit();
	echo ausers_form_end();
}

function amr_meta_overview_page() { /* the main setting spage  - num of lists and names of lists */
	global $amain, $aopt, $amr_current_list;

	if (empty($amain)) $amain = ausers_get_option('amr-users-main');
	//$amr_current_list = amr_user_lists::which_list(); 
	
	if (isset($_GET['tab'])) 
		$tab = sanitize_text_field($_GET['tab']);
	else 
		$tab = 'overview';
	
	$tabs['overview']	= __('Overview', 'amr-users');
	$tabs['settings']	= __('Settings', 'amr-users');
//wait	$tabs['fields']		= __('Configure', 'amr-users');
	$tabs['tools'] 		= __('Tools', 'amr-users');
//	$tabs['orglists'] 	= __('Organise', 'amr-users');

	if ($tab == 'fields') 
		$heading = $tabs[$tab].' '.__('List: ').' '.$amr_current_list;
	else
		$heading =  $tabs[$tab];
	
	amr_meta_admin_headings ($plugin_page=''); // does the nonce check etc	
	amr_users_do_tabs ($tabs,$tab);
	echo PHP_EOL.'<div class="wrap"><!-- one wrap -->'.PHP_EOL;

	if ( isset( $_POST['import-list'] )) {
			amr_meta_handle_import();		
	}
	elseif ( isset( $_POST['export-list'] )  ) {
			amr_meta_handle_export();
		}	
	elseif (isset ($_POST['action']) and  ($_POST['action'] == "save")) { 
		if (!empty($_POST['reset'])) {
			amr_meta_reset();	
			return;  // dont show lists yet
		}
		else {
			switch ($tab) {
			case 'overview':
			case 'orglists':

				if (function_exists ('amrmeta_validate_organised_lists') ) {
					amrmeta_validate_organised_lists();
				}
				else {
					amrmeta_validate_overview();
				}
				break;
			case 'settings': 
				amrmeta_validate_list_settings();				
				break;			
			}			
		}	
	}
	
	amr_handle_copy_delete();
	
	amr_handle_add_new();

	switch ($tab) {
		case 'overview' : 
		case 'orglists':
			if (!function_exists ('amrmeta_organise_lists') ) {
				echo '<a href="https://wpusersplugin.com/downloads/amr-users-plus/" title="Link to add on plugin site">'.__('Upgrade to organise lists & control user menu lists').'</a>';
				amr_meta_overview_form ();	
			}
			else {
				echo '<h2>'.__('Organise, Sort, Rename and Renumber lists', 'amr-users').'</h2>';
				amrmeta_organise_lists();
			}
			break;
		case 'settings': 				
			amr_userlist_settings();
			break;	
		case 'tools':  // keep so can offer preset 'lists' that maybe useful				
			amr_user_list_tools();
			break;
		case 'fields':  		
			amrmeta_configure_page_nested();	
			break;		
	}
/*
?>	
<script language="text/javascript">
 jQuery(document).ready( function($) {
    $('table#userlistfields tbody').sortable({
	cursor: "move";
	update: function( event, ui ) {
    $(this).children().each(function(index) {
			$(this).find('td').last().html(index + 1)
		});
	} });
</script>
<?php */

}		
