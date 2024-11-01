<?php
/*
Plugin Name: Soundst FeedWire
Plugin URI: http://www.soundst.com/
Description: The Sound Strategies FeedWire plugin fetches content from multiple feeds and allows the administrator to select which headlines appear on the “Wire”.  The wire can be presented as either a widget or by using the short-code [soundstfeedwire] on a page.  
Version: 1.3.4
Author: Sound Strategies, Inc
Author URI: http://www.soundst.com/
*/

define("SSFW_UNIQUE_KEY", '3031032202211924');
define("SSFW_BASE_DIRECTORY",'/tmp/');

class SSFW_Semaphore {

	/* var stream */
	private $fp;

	/* var string */
	private $filename;

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 ·       Attempt to get an exclusive lock on this semaphore
	 ·
	 * @param string $baseDirectory
	 * @param string $uniqueKey
	 * @param int $maxLockMinutes
	 *
	 * @return bool
	 */
	public function getSemaphore($baseDirectory, $uniqueKey, $maxLockMinutes=5) {
		$this->filename = $baseDirectory . 'semlock-' . $uniqueKey . '.sem';
		$this->releaseExpiredSemaphore($maxLockMinutes);
		$this->fp = fopen($this->filename, 'w');
		return (flock($this->fp, LOCK_EX | LOCK_NB));
	}

	/**
	 ·       Release expired semaphore
	 *
	 * @param int $maxValidLockMinutes
	 */
	private function releaseExpiredSemaphore($maxValidLockMinutes) {
		if (file_exists($this->filename)) {
			$fileDate = filemtime($this->filename);
			if (time() - $fileDate > ($maxValidLockMinutes  * 60)) {
				unlink($this->filename);
			}
		}
	}
	
	/**
	 * Release semaphore
	 */
	public function releaseSemaphore() {
		flock($this->fp, LOCK_UN);
		fclose($this->fp);
		unlink($this->filename);
	}
}

$fw_sem = new SSFW_Semaphore();


if(class_exists('fw_p')){
	register_activation_hook(__FILE__, array( 'fw_p', 'activate' ));
	register_deactivation_hook(__FILE__, array('fw_p', 'deactivate'));
	
	$fw_p = new fw_p();
}

class fw_p{
	public function __construct(){
		
		if(is_admin()){
			add_action('admin_menu', array($this, 'add_fw_page'),1);
			add_action('admin_init', array($this, 'page_init'));
			require_once(dirname(__FILE__).'/fw_type.php');
			require_once(dirname(__FILE__).'/fw-bulk-actions.php');
			new fw_custom_bulk();
		}
	}

	public static function activate(){
		
	}
	public static function deactivate(){
		
	}
	
	public function add_fw_page()
	{
		wp_register_style( 'fw_stylesheet', plugins_url('fw-styles.css', __FILE__) );
		if (function_exists('add_menu_page'))
		{
				add_menu_page( 'Soundst FeedWire - Settings', 'FeedWire', 'manage_options', 'FWPluginPage', false, false, '63.3');
		}
		if (function_exists('add_submenu_page')){
			add_submenu_page( 'FWPluginPage', 'Soundst FeedWire - Settings', 'Settings', 'manage_options', 'FWPluginPage', array($this, 'FWPluginPageOptions'));
			
		}
	}

	public function FWPluginPageOptions()
	{?>
	<div class = "wrap">
		<h2>Soundst FeedWire</h2>
		<form method="post" action="options.php">
		<p>The Sound Strategies FeedWire plugin fetches content from multiple feeds and allows the administrator to select which headlines appear on the “Wire”.  The wire can be presented as either a widget or by using the short-code [soundstfeedwire] on a page.</p>
		
	    <?php
        	// Prints out all hidden setting fields
			settings_fields('fw_option_group');	
			do_settings_sections('fw-setting-admin');
		?>
	        <?php submit_button(); ?>
	        <?php settings_errors( 'settings-error' );?>
	    </form>
	    
	    <h3>Short-Code Instructions</h3>
		<p>
			The wire can be displayed on any page by entering the following short-code:<br/>
			[soundstfeedwire nn,source,hide-filter,hide-source,hide-date,hide-time]
		</p>
		<p>The “nn” parameter controls how many headlines appear and is required.</p>
		<p>The “source” parameter controls is used to limit the wire to a single source or all sources (use “All”) and is required.</p>
		<p>The four “hide” parameters are optional.</p>
		<p>
			Sample (showing 20 posts for all sources): [soundstfeedwire 20,All]<br/>
			Sample (showing 20 posts, but no source): [soundstfeedwire 20,All,hide-source]<br/>
			Sample (showing 20 posts from the “USA Today” feed, but no source or date): [soundstfeedwire 20,USA Today,hide-source,hide-date]
		</p>
	    
	    </div>
	    <?php 
	}
	
    public function page_init()
	{
	/*
	 * Settings Plugin Page
	 */		
		register_setting('fw_option_group', 'array_key', array($this, 'check_ALL'));
		
	//*
	//* Feeds Section
        add_settings_section(
	    	'feeds_section',
	    	'Feeds',
	    	array($this, 'feeds_section_info'),
	    	'fw-setting-admin'
		);	
		
        //feeds list
		add_settings_field(
	    	'feeds_list', 
	    	'Feeds List', 
	    	array($this, 'create_an_fl_field'), 
	    	'fw-setting-admin',
	    	'feeds_section'			
		);
		
		//feeds statistics
		add_settings_field(
		'feeds_statistics',
		'Feeds Statistics',
		array($this, 'create_an_fs_field'),
		'fw-setting-admin',
		'feeds_section'
		);
		
		//no-follow and no-index
		add_settings_field(
			'no_follow',
			'Use no-index and no-follow for all links (recommended)',
			array($this, 'create_an_nf_field'),
			'fw-setting-admin',
			'feeds_section'
		);
		
		//default source value for manually entered headlines
		add_settings_field(
			'def_source',
			'Default source value for manually entered headlines:',
			array($this, 'create_an_dsv_field'),
			'fw-setting-admin',
			'feeds_section'
		);
		
		//date format
		add_settings_field(
			'date_format',
			'Date Format:',
			array($this, 'create_an_datef_field'),
			'fw-setting-admin',
			'feeds_section'
		);		
		
		//feed processing schedule frequency
		add_settings_field(
			'shedule_freq',
			'Feed processing schedule:',
			array($this, 'create_an_sfreq_field'),
			'fw-setting-admin',
			'feeds_section'
		);
		
		//days to keep wire
		add_settings_field(
			'feep_wire',
			'Days to keep wire:',
			array($this, 'create_an_keepwire_field'),
			'fw-setting-admin',
			'feeds_section'
		);
		
		
	//*
	//* Page Content Styles Section
		add_settings_section(
			'pcf_section',
			'Page Content Styles',
			false,
			'fw-setting-admin'
		);

		//headline font size
		add_settings_field(
			'phf_size',
			'Headline font size:',
			array($this, 'create_an_phfs_field'),
			'fw-setting-admin',
			'pcf_section'
		);
		
		//source font size
		add_settings_field(
			'psf_size',
			'Source font size:',
			array($this, 'create_an_psfs_field'),
			'fw-setting-admin',
			'pcf_section'
		);
		
		//data/time font size
		add_settings_field(
			'pdtf_size',
			'Data/time font size:',
			array($this, 'create_an_pdtfs_field'),
			'fw-setting-admin',
			'pcf_section'
		);
		
		//padding between title and source
		add_settings_field(
			'p_paddingbts',
			'Padding between title and source:',
			array($this, 'create_an_ppaddingbts_field'),
			'fw-setting-admin',
			'pcf_section'
		);
		
		//padding between entries
		add_settings_field(
			'p_paddingbe',
			'Padding between entries:',
			array($this, 'create_an_ppaddingbe_field'),
			'fw-setting-admin',
			'pcf_section'
		);
		
		//line height
		add_settings_field(
			'line_height',
			'Spacing for title wrapping:',
			array($this, 'create_an_line_height_field'),
			'fw-setting-admin',
			'pcf_section'
		);
		
		//maximum entries per page
		add_settings_field(
			'max_entries',
			'Maximum entries per page:',
			array($this, 'create_an_max_entries_field'),
			'fw-setting-admin',
			'pcf_section'
		);
		
	//*
	//* Widget Content Styles Section
		add_settings_section(
			'wcf_section',
			'Widget Content Styles',
			false,
			'fw-setting-admin'
		);
		
		//headline font size
		add_settings_field(
			'whf_size',
			'Headline font size:',
			array($this, 'create_an_whfs_field'),
			'fw-setting-admin',
			'wcf_section'
		);
		
		//source font size
		add_settings_field(
			'wsf_size',
			'Source font size:',
			array($this, 'create_an_wsfs_field'),
			'fw-setting-admin',
			'wcf_section'
		);
		
		//data/time font size
		add_settings_field(
			'wdtf_size',
			'Data/time font size:',
			array($this, 'create_an_wdtfs_field'),
			'fw-setting-admin',
			'wcf_section'
		);
		
		//padding between title and source
		add_settings_field(
			'w_paddingbts',
			'Padding between title and source:',
			array($this, 'create_an_wpaddingbts_field'),
			'fw-setting-admin',
			'wcf_section'
		);
				
		//padding between entries
		add_settings_field(
			'w_paddingbe',
			'Padding between entries:',
			array($this, 'create_an_wpaddingbe_field'),
			'fw-setting-admin',
			'wcf_section'
		);
		

		//line height entries
		add_settings_field(
			'w_line_height',
			'Spacing for title wrapping:',
			array($this, 'create_an_wlineheight_field'),
			'fw-setting-admin',
			'wcf_section'
		);
		
		wp_enqueue_style( 'fw_stylesheet' );
		
		//horizontal padding
		add_settings_field(
			'w_hrzpadding',
			'Left and right padding:',
			array($this, 'create_an_whrzpadding_field'),
			'fw-setting-admin',
			'wcf_section'
		);
		
		//vertical padding
		add_settings_field(
			'w_vrtpadding',
			'Top and bottom padding:',
			array($this, 'create_an_wvrtpadding_field'),
			'fw-setting-admin',
			'wcf_section'
		);
		
	//*
	//* Extra Section
		add_settings_section(
			'exf_section',
			'Extra Section',
			false,
			'fw-setting-admin'
		);
		
		//enable logging
		add_settings_field(
			'enbl_lgg',
			'Enable Logging:',
			array($this, 'create_an_enbl_field'),
			'fw-setting-admin',
			'exf_section'
		);
		
		wp_enqueue_style( 'fw_stylesheet' );
	}

	
    public function check_ALL($input)
	{
	//*
	//*Feeds Section
		
		//feeds list
		$message = null;
		$type = null;
		$line_number = 0;
		$new_flist = explode("\n",$input['feeds_list']);
		foreach ($new_flist as $one_feed){
			$line_number++;
			if(($one_feed)!='')
			if(substr_count($one_feed, ',') < '3'){
				$type = 'error';
				$message .= 'Line ';
				$message .= $line_number;
				$message .= ' does not have exactly four elements (source, feed URL, and review)<br/>';
			}
			else{
				$sub_el = explode(',',$one_feed);
				if(($sub_el[2] != "N")and($sub_el[2] != "Y")){
					$type = 'error';
					$message .= 'The value for the “default display” (third) element on line ';
					$message .= $line_number;
					$message .= ' must be Y or N<br/>';
				}
				elseif((trim($sub_el[3]) != "N")and((trim($sub_el[3])) != "Y")){
					$type = 'error';
					$message .= 'The value for the “new window” (fourth) element on line ';
					$message .= $line_number;
					$message .= ' must be Y or N<br/>';
				}
			}
		}
			
        if(is_string($input['feeds_list']))
		{
	    	$mid['feeds_list'] = $input['feeds_list'];			
	    	if(get_option('fw_feeds_list') === FALSE)
			{
				add_option('fw_feeds_list', $mid['feeds_list']);
				if($type == null){
					$type = 'updated';
					$message = 'Successfully saved';
				}
	    	}
	    	else
			{
				update_option('fw_feeds_list', $mid['feeds_list']);
				if($type == null){
					$type = 'updated';
					$message = 'Successfully updated';
				}
	    	}
	    	if(get_option('fw_feeds_list')){
	    		$push = explode("\n",get_option('fw_feeds_list'));
	    		foreach ($push as $wire_array){
	    			if($push != ''){
	    				$key = explode(",",$wire_array);
	    				$last_updated[esc_attr($key[0])] = time();
	    				$update_status[esc_attr($key[0])]['status'] = '';
	    				$update_status[esc_attr($key[0])]['descr'] = '';
	    			}
	    		}
	    	}
	    	if(get_option('fw_last_updated_list') === FALSE){
	    		add_option('fw_last_updated_list',$last_updated);
	    	}
	    	else{
	    		$last_updated = get_option('fw_last_updated_list');
	    		 
	    		$push = explode("\n",get_option('fw_feeds_list'));
	    		foreach ($push as $wire_array){
	    			if($push != ''){
	    				$key = explode(",",$wire_array);
	    				
	    				if(!isset($last_updated[esc_attr($key[0])])){
	    					$last_updated[esc_attr($key[0])] = time();
	    				}
	    				
	    				$last_updated_new[esc_attr($key[0])] = $last_updated[esc_attr($key[0])];
	    			}
	    		}
	    		update_option('fw_last_updated_list',$last_updated_new);
	    	}
	    	
	    	if(get_option('fw_update_status_list') === FALSE){
	    		add_option('fw_update_status_list',$update_status);
	    	}
	    	else{
	    		$update_status = get_option('fw_update_status_list');
	    	
	    		$push = explode("\n",get_option('fw_feeds_list'));
	    		foreach ($push as $wire_array){
	    			if($push != ''){
	    				$key = explode(",",$wire_array);
	    				 
	    				if(!isset($update_status[esc_attr($key[0])])){
	    					$update_status[esc_attr($key[0])]['status'] = "";
	    					$update_status[esc_attr($key[0])]['descr'] = "";
	    				}
	    				 
	    				$update_status_new[esc_attr($key[0])]['status'] = $update_status[esc_attr($key[0])]['status'];
	    				$update_status_new[esc_attr($key[0])]['descr'] = $update_status[esc_attr($key[0])]['descr'];
	    			}
	    		}
	    		update_option('fw_update_status_list',$update_status_new);
	    	}
		}
		else
		{
	    	$mid['feeds_list'] = '';
		}
		
		//no-follow and no-index
		$mid['no_follow'] = $input['no_follow'];
		if(get_option('fw_no_follow') === FALSE)
		{
			add_option('fw_no_follow', $mid['no_follow']);
		}
		else
		{
			update_option('fw_no_follow', $mid['no_follow']);
		}
		
		//default source value for manually entered headlines
		$mid['def_source'] = $input['def_source'];
		if(get_option('fw_def_source') === FALSE)
		{
			add_option('fw_def_source', $mid['def_source']);
		}
		else
		{
			update_option('fw_def_source', $mid['def_source']);
		}
		
		//date format
		$mid['date_format'] = $input['date_format'];
		if(get_option('fw_date_format') === FALSE)
		{
			add_option('fw_date_format', $mid['date_format']);
		}
		else
		{
			update_option('fw_date_format', $mid['date_format']);
		}
		
		//feed processing schedule frequency
		$mid['shedule_freq'] = $input['shedule_freq'];
		if(get_option('fw_shedule_freq') === FALSE)
		{
			add_option('fw_shedule_freq', $mid['shedule_freq']);
		}
		else
		{
			update_option('fw_shedule_freq', $mid['shedule_freq']);
		}
		
		//days to keep wire
		$mid['keep_wire'] = $input['keep_wire'];
		if(get_option('fw_keep_wire') === FALSE)
		{
			add_option('fw_keep_wire', $mid['keep_wire']);
		}
		else
		{
			update_option('fw_keep_wire', $mid['keep_wire']);
		}
		
	//*
	//* Page Content Styles Section
		
		//headline font size
		if(is_numeric($input['phf_size']))
		{
			$mid['phf_size'] = $input['phf_size'];
			if(get_option('fw_phf_size') === FALSE)
			{
				add_option('fw_phf_size', $mid['phf_size']);
			}
			else
			{
				update_option('fw_phf_size', $mid['phf_size']);
			}
		}
		else
		{
			$mid['phf_size'] = '';
		}
		
		//source font size
		if(is_numeric($input['psf_size']))
		{
			$mid['psf_size'] = $input['psf_size'];
			if(get_option('fw_psf_size') === FALSE)
			{
				add_option('fw_psf_size', $mid['psf_size']);
			}
			else
			{
				update_option('fw_psf_size', $mid['psf_size']);
			}
		}
		else
		{
			$mid['psf_size'] = '';
		}
		
		//data/time font size
		if(is_numeric($input['pdtf_size']))
		{
			$mid['pdtf_size'] = $input['pdtf_size'];
			if(get_option('fw_pdtf_size') === FALSE)
			{
				add_option('fw_pdtf_size', $mid['pdtf_size']);
			}
			else
			{
				update_option('fw_pdtf_size', $mid['pdtf_size']);
			}
		}
		else
		{
			$mid['pdtf_size'] = '';
		}
		
		//padding between title and source
		if(is_numeric($input['p_paddingbts']))
		{
			$mid['p_paddingbts'] = $input['p_paddingbts'];
			if(get_option('fw_p_paddingbts') === FALSE)
			{
				add_option('fw_p_paddingbts', $mid['p_paddingbts']);
			}
			else
			{
				update_option('fw_p_paddingbts', $mid['p_paddingbts']);
			}
		}
		else
		{
			$mid['p_paddingbts'] = '';
		}
		
		//padding between entries
		if(is_numeric($input['p_paddingbe']))
		{
			$mid['p_paddingbe'] = $input['p_paddingbe'];
			if(get_option('fw_p_paddingbe') === FALSE)
			{
				add_option('fw_p_paddingbe', $mid['p_paddingbe']);
			}
			else
			{
				update_option('fw_p_paddingbe', $mid['p_paddingbe']);
			}
		}
		else
		{
			$mid['p_paddingbe'] = '';
		}
		
		//maximum entries per page
		if(is_numeric($input['max_entries']))
		{
			$mid['max_entries'] = $input['max_entries'];
			if(get_option('fw_max_entries') === FALSE)
			{
				add_option('fw_max_entries', $mid['max_entries']);
			}
			else
			{
				update_option('fw_max_entries', $mid['max_entries']);
			}
		}
		else
		{
			$mid['max_entries'] = '';
		}
		
		//line height
		if(is_numeric($input['line_height']))
		{
			$mid['line_height'] = $input['line_height'];
			if(get_option('fw_line_height') === FALSE)
			{
				add_option('fw_line_height', $mid['line_height']);
			}
			else
			{
				update_option('fw_line_height', $mid['line_height']);
			}
		}
		else
		{
			$mid['line_height'] = '';
		}
		
		
	//*
	// *Widget Content Styles Section
		
		//headline font size
		if(is_numeric($input['whf_size']))
		{
			$mid['whf_size'] = $input['whf_size'];
			if(get_option('fw_whf_size') === FALSE)
			{
				add_option('fw_whf_size', $mid['whf_size']);
			}
			else
			{
				update_option('fw_whf_size', $mid['whf_size']);
			}
		}
		else
		{
			$mid['whf_size'] = '';
		}
		
		//source font size
		if(is_numeric($input['wsf_size']))
		{
			$mid['wsf_size'] = $input['wsf_size'];
			if(get_option('fw_wsf_size') === FALSE)
			{
				add_option('fw_wsf_size', $mid['wsf_size']);
			}
			else
			{
				update_option('fw_wsf_size', $mid['wsf_size']);
			}
		}
		else
		{
			$mid['wsf_size'] = '';
		}
		
		//data/time font size
		if(is_numeric($input['wdtf_size']))
		{
			$mid['wdtf_size'] = $input['wdtf_size'];
			if(get_option('fw_wdtf_size') === FALSE)
			{
				add_option('fw_wdtf_size', $mid['wdtf_size']);
			}
			else
			{
				update_option('fw_wdtf_size', $mid['wdtf_size']);
			}
		}
		else
		{
			$mid['wdtf_size'] = '';
		}
		
		//padding between title and source
		if(is_numeric($input['w_paddingbts']))
		{
			$mid['w_paddingbts'] = $input['w_paddingbts'];
			if(get_option('fw_w_paddingbts') === FALSE)
			{
				add_option('fw_w_paddingbts', $mid['w_paddingbts']);
			}
			else
			{
				update_option('fw_w_paddingbts', $mid['w_paddingbts']);
			}
		}
		else
		{
			$mid['w_paddingbts'] = '';
		}
		
		//padding between entries
		if(is_numeric($input['w_paddingbe']))
		{
			$mid['w_paddingbe'] = $input['w_paddingbe'];
			if(get_option('fw_w_paddingbe') === FALSE)
			{
				add_option('fw_w_paddingbe', $mid['w_paddingbe']);
			}
			else
			{
				update_option('fw_w_paddingbe', $mid['w_paddingbe']);
			}
		}
		else
		{
			$mid['w_paddingbe'] = '';
		}
		
		//line height
		if(is_numeric($input['w_line_height']))
		{
			$mid['w_line_height'] = $input['w_line_height'];
			if(get_option('fw_w_line_height') === FALSE)
			{
				add_option('fw_w_line_height', $mid['w_line_height']);
			}
			else
			{
				update_option('fw_w_line_height', $mid['w_line_height']);
			}
		}
		else
		{
			$mid['w_line_height'] = '';
		}
		
		//horizontal padding
		if(is_numeric($input['w_hrzpadding']))
		{
			$mid['w_hrzpadding'] = $input['w_hrzpadding'];
			if(get_option('fw_w_hrzpadding') === FALSE)
			{
				add_option('fw_w_hrzpadding', $mid['w_hrzpadding']);
			}
			else
			{
				update_option('fw_w_hrzpadding', $mid['w_hrzpadding']);
			}
		}
		else
		{
			$mid['w_hrzpadding'] = '';
		}
		
		//vertical padding
		if(is_numeric($input['w_vrtpadding']))
		{
			$mid['w_vrtpadding'] = $input['w_vrtpadding'];
			if(get_option('fw_w_vrtpadding') === FALSE)
			{
				add_option('fw_w_vrtpadding', $mid['w_vrtpadding']);
			}
			else
			{
				update_option('fw_w_vrtpadding', $mid['w_vrtpadding']);
			}
		}
		else
		{
			$mid['w_vrtpadding'] = '';
		}
		
	//*
	//* Extra Section
		
		//enable logging
		$mid['enbl_lgg'] = $input['enbl_lgg'];
		if(get_option('fw_enbl_lgg') === FALSE)
		{
			add_option('fw_enbl_lgg', $mid['enbl_lgg']);
		}
		else
		{
			update_option('fw_enbl_lgg', $mid['enbl_lgg']);
		}		
		
		add_settings_error(
			'settings-error',
			'settings_updated',
			$message,
			$type
		);
		
		return $mid;
	}
	
//*
//* Feeds Section
    public function feeds_section_info()
	{
		?>
		Each feed must appear on a separate line and have the following comma separated elements:
		<p>
			Source – Name of the feed that will be displayed and used by visitors for filtering<br/>
			Feed URL – The actual feed URL starting with http://<br/>
			Default Display – Enter the value “N” if the headlines from the feed must be approved by the administrator before they display.  Enter “Y” if the headlines go directly to the wire.<br/>
			New Window – Enter the value “Y” to open the link in a new window (recommended).  Enter “N” to use the same window.
		</p>
		<p>Sample: Sound Strategies,http://soundst.com/feed/,Y,Y</p>
		<?php 
    }
    
    public function create_an_fl_field()
	{
		if(get_option('fw_feeds_list') === FALSE)
		{
			add_option('fw_feeds_list', 'Sound Strategies,http://soundst.com/feed/,Y,Y');
		}
    	?><div style="width:600px;"><textarea id="fl_field" name="array_key[feeds_list]"><?php echo get_option('fw_feeds_list');?></textarea><br/>
    	<?php /*<a id="check" class="button button-primary" href="javascript:void(0);">Check</a>*/ ?>
    	<?php /* <a id="fw_update_feeds" class="button button-primary" href="javascript:void(0);">Manage Feeds</a> */?>
    	</div><?php 
	}
	public function create_an_fs_field()
	{
    	$push = explode("\n",get_option('fw_feeds_list'));
    	$last_update = get_option('fw_last_updated_list');
    	$update_status = get_option('fw_update_status_list');
    	$i = 0;
    	?>
    	<div class="fw_update_area">
    	<div class="update_box">
    		<span class="t_name">Feed Name</span>
    		<div class="update_status t_name">Update Status</div>
    		<div class="last_update t_name">Last update time</div>
    		
    	</div>
    	<?php 
    	foreach($push as $one_feed)
		if($one_feed !== ''){
			$name = explode(',',$one_feed);
			$i++;
    		?>
    			<div id="update_box-<?php echo $i;?>" class="update_box">
    				<span><?php echo $name[0];?></span>
    				<div class="update_status">
    				<?php 
    				if($update_status[esc_attr($name[0])]['status'] != ""){
    					echo $update_status[esc_attr($name[0])]['status'];
    				}
    				?>
    				</div>
    				<div class="last_update">
    				<?php 
    				if($last_update[esc_attr($name[0])] != ""){
						echo get_date_from_gmt( date( 'Y-m-d H:i:s', $last_update[esc_attr($name[0])] ), 'd M Y (h:i:s)' );
    				}
    				else echo 'not updated yet';
    				?>
    				</div>
    				
    				<a id="update_but-<?php echo $i;?>" class="button button-primary update_but" href="javascript:void(0);">Process</a>
    				<div class="was_updated" <?php if($update_status[esc_attr($name[0])]['descr'] != ""){echo "style='display:block;'";}?>>
    					Last Status: 
    					<span class="description <?php if(strstr($update_status[esc_attr($name[0])]['status'],"warning")){echo 'warning';} elseif(strstr($update_status[esc_attr($name[0])]['status'],"failure")) echo 'failure';?>">
    						<?php
    						if($update_status[esc_attr($name[0])]['descr'] != ""){
								echo $update_status[esc_attr($name[0])]['descr'];
							} 
    						?>
    					</span>
    				</div>
    			</div>
    		<?php 
    	}
    	?>
    	</div>
    	<script type="text/javascript">
			jQuery('.update_but').click(function(){

				var id = jQuery(this);

				id.parent('.update_box').find('.update_status').text('Processing').wrapInner('<span class="processing"></span>');
				jQuery.ajax({
					type: 'POST',
					url: ajaxurl,
					data: {"action": "update_feed", "checkdatype":jQuery(this).attr("id") },
					success: function(data, textStatus, xhr){
						data = JSON.parse(xhr.responseText);
						if((data.status == "success")||(data.status == "warning")){
							id.parent('.update_box').find('.last_update').text(data.last_update);
						}
						id.parent('.update_box').find('.was_updated').find('.description').html(data.message);
						if(data.status == "success"){
							id.parent('.update_box').find('.update_status').text(data.status).wrapInner('<span class="success"></span>');
							id.parent('.update_box').find('.was_updated').find('.description').addClass('success').removeClass('failure').removeClass('warning');
							id.parent('.update_box').find('.was_updated').slideDown("slow").delay(5000).slideUp("slow");
						} else if(data.status == "failure"){
							id.parent('.update_box').find('.update_status').text(data.status).wrapInner('<span class="failure"></span>');
							id.parent('.update_box').find('.was_updated').find('.description').addClass('failure').removeClass('warning');
							id.parent('.update_box').find('.was_updated').slideDown("slow");
						} else {
							id.parent('.update_box').find('.update_status').text(data.status).wrapInner('<span class="warning"></span>');
							id.parent('.update_box').find('.was_updated').find('.description').addClass('warning').removeClass('failure');
							id.parent('.update_box').find('.was_updated').slideDown("slow");
						}						
					}
			});
			return false;
			});
			<?php /*jQuery('#fw_update_feeds').click(function(){
				jQuery('.fw_update_area').slideToggle('slow');
			});
			 jQuery('#check').click(function(){
				console.log("<?php echo get_option('fw_last_updated_list',true);?>");
			}); */ ?>
		</script>
    	<?php 
    	//POINT!
    }
    public function create_an_nf_field()
    {
    	?>
    		<input type="radio" id="nf_field" name="array_key[no_follow]" value=""<?php checked('',get_option('fw_no_follow'));?> /> Yes
    		<input type="radio" id="nf_field1" name="array_key[no_follow]" value="1"<?php checked('1',get_option('fw_no_follow'));?> /> No
    	<?php
	}
	public function create_an_dsv_field(){
		?>
			<input type="text" id="dsv_field" name="array_key[def_source]" value="<?php echo get_option('fw_def_source');?>"/> (can be blank)
		<?php 
	}
	
	//date format
	public function create_an_datef_field(){
		?>
			<input type="radio" id="date_format" name="array_key[date_format]" value="1"<?php checked('1',get_option('fw_date_format'));?> /> yyyy/mm/dd
    		<input type="radio" id="date format1" name="array_key[date_format]" value=""<?php checked('',get_option('fw_date_format'));?> /> mm/dd/yyyy
		<?php 
	}
	
	//feed processing schedule frequency
	public function create_an_sfreq_field(){
		?>
			<input type="text" id="sfreq_field" name="array_key[shedule_freq]" value="<?php echo get_option('fw_shedule_freq');?>"/> (minutes)
		<?php 
	}
	
	//days to keep wire
	public function create_an_keepwire_field(){
		if(get_option('fw_keep_wire') === FALSE)
		{
			add_option('fw_keep_wire', 10);
		}
		?>
			<input type="text" id="keepwire_field" name="array_key[keep_wire]" value="<?php echo get_option('fw_keep_wire');?>"/> (leave blank to keep indefinitely)
		<?php 
	}
	
	
//*
//* Page Content Styles Section
	public function create_an_phfs_field(){
		if(get_option('fw_phf_size') === FALSE)
		{
			add_option('fw_phf_size', '10');
		}
		?>
			<input type="text" id="phfs_field" name="array_key[phf_size]" value="<?php echo get_option('fw_phf_size');?>"/>
		<?php 
	}
	public function create_an_psfs_field(){
		if(get_option('fw_psf_size') === FALSE)
		{
			add_option('fw_psf_size', '8');
		}
		?>
			<input type="text" id="psfs_field" name="array_key[psf_size]" value="<?php echo get_option('fw_psf_size');?>"/>
		<?php 
	}
	public function create_an_pdtfs_field(){
		if(get_option('fw_pdtf_size') === FALSE)
		{
			add_option('fw_pdtf_size', '8');
		}
		?>
			<input type="text" id="pdtfs_field" name="array_key[pdtf_size]" value="<?php echo get_option('fw_pdtf_size');?>"/>
		<?php 
	}
	public function create_an_ppaddingbts_field(){
		if(get_option('fw_p_paddingbts') === FALSE)
		{
			add_option('fw_p_paddingbts', '0');
		}
		?>
			<input type="text" id="p_paddingbts_field" name="array_key[p_paddingbts]" value="<?php echo get_option('fw_p_paddingbts');?>"/>
		<?php 
	}
	public function create_an_ppaddingbe_field(){
		if(get_option('fw_p_paddingbe') === FALSE)
		{
			add_option('fw_p_paddingbe', '12');
		}
		?>
			<input type="text" id="p_paddingbe_field" name="array_key[p_paddingbe]" value="<?php echo get_option('fw_p_paddingbe');?>"/>
		<?php 
	}
	public function create_an_line_height_field(){
		if(get_option('fw_line_height') === FALSE)
		{
			add_option('fw_line_height', '8');
		}
		?>
			<input type="text" id="line_height_field" name="array_key[line_height]" value="<?php echo get_option('fw_line_height');?>"/>
		<?php 
	}
	public function create_an_max_entries_field(){
		if(get_option('fw_max_entries') === FALSE)
		{
			add_option('fw_max_entries', '100');
		}
		?>
			<input type="text" id="max_entries_field" name="array_key[max_entries]" value="<?php echo get_option('fw_max_entries');?>"/>
		<?php 
	}

//*
// *Widget Content Styles Section
	public function create_an_whfs_field(){
		if(get_option('fw_whf_size') === FALSE)
		{
			add_option('fw_whf_size', '10');
		}
		?>
			<input type="text" id="whfs_field" name="array_key[whf_size]" value="<?php echo get_option('fw_whf_size');?>"/>
		<?php 
	}

	public function create_an_wsfs_field(){
		if(get_option('fw_wsf_size') === FALSE)
		{
			add_option('fw_wsf_size', '8');
		}
		?>
			<input type="text" id="wsfs_field" name="array_key[wsf_size]" value="<?php echo get_option('fw_wsf_size');?>"/>
		<?php 
	}
	
	public function create_an_wdtfs_field(){
		if(get_option('fw_wdtf_size') === FALSE)
		{
			add_option('fw_wdtf_size', '8');
		}
		?>
			<input type="text" id="wdtfs_field" name="array_key[wdtf_size]" value="<?php echo get_option('fw_wdtf_size');?>"/>
		<?php 
	}
	public function create_an_wpaddingbts_field(){
		if(get_option('fw_w_paddingbts') === FALSE)
		{
			add_option('fw_w_paddingbts', '0');
		}
		?>
			<input type="text" id="w_paddingbts_field" name="array_key[w_paddingbts]" value="<?php echo get_option('fw_w_paddingbts');?>"/>
		<?php 
	}
	public function create_an_wlineheight_field(){
		if(get_option('fw_w_line_height') === FALSE)
		{
			add_option('fw_w_line_height', '8');
		}
		?>
			<input type="text" id="w_line_height_field" name="array_key[w_line_height]" value="<?php echo get_option('fw_w_line_height');?>"/>
		<?php 
	}
	public function create_an_wpaddingbe_field(){
		if(get_option('fw_w_paddingbe') === FALSE)
		{
			add_option('fw_w_paddingbe', '12');
		}
		?>
			<input type="text" id="w_paddingbe_field" name="array_key[w_paddingbe]" value="<?php echo get_option('fw_w_paddingbe');?>"/>
		<?php 
	}
	public function create_an_whrzpadding_field(){
		if(get_option('fw_w_hrzpadding') === FALSE)
		{
			add_option('fw_w_hrzpadding', '10');
		}
		?>
			<input type="text" id="w_hrzpadding_field" name="array_key[w_hrzpadding]" value="<?php echo get_option('fw_w_hrzpadding');?>"/>
		<?php 
	}
	public function create_an_wvrtpadding_field(){
		if(get_option('fw_w_vrtpadding') === FALSE)
		{
			add_option('fw_w_vrtpadding', '10');
		}
		?>
			<input type="text" id="w_vrtpadding_field" name="array_key[w_vrtpadding]" value="<?php echo get_option('fw_w_vrtpadding');?>"/>
		<?php 
	}
//*
//* Extra Section
	public function create_an_enbl_field(){
		?>
			<input type="radio" id="enbl_field" name="array_key[enbl_lgg]" value="1"<?php checked('1',get_option('fw_enbl_lgg'));?> /> Yes
    		<input type="radio" id="enbl_field1" name="array_key[enbl_lgg]" value=""<?php checked('',get_option('fw_enbl_lgg'));?> /> No
    		<br /><span>The Update processes will be written to the "feed_wire.log" file.</span>
		<?php 
	}
		
}

/*function add_fw_script(){
	wp_enqueue_script( 'jquery.fw.pagination', plugins_url('soundst-feedwire/jquery.fw.pagination.js'),5);
}
add_action('wp_head', 'add_fw_script');*/

/*
 * Soundst FeedWire Shortcode
 */
function soundstfeedwire_func( $atts ) {
	$n_atts = implode(' ',$atts);	
	
	$source = '';
	
	if($n_atts){
		$new_options = explode(',',$n_atts);
		if($new_options[0]){
			$nn = $new_options[0];
		}
		if($new_options[1]){
			$source = $new_options[1];
		}
		foreach($new_options as $one){
			if($one == 'hide-source'){
				$hide_source = 1;
			}
			if($one == 'hide-date'){
				$hide_date = 1;
			}
			if($one == 'hide-time'){
				$hide_time = 1;
			}
			if($one == 'hide-filter'){
				$hide_filter = 1;
			}
		}
	}
	
	
	$selected = '';
	
	//css for page content by shortcode
	$output = '<style type="text/css">';
	
	$output .= '.fw_inside .entry{line-height:';
	$output .= get_option('fw_line_height');
	$output .= 'px;}';
	
	$output .= '.fw_headline{font-size:';
	$output .= get_option('fw_phf_size');
	$output .= 'px;';
	$output .= 'line-height:';
	$output .= get_option('fw_phf_size');
	$output .= 'px;}';
	
	$output .= '.fw_dtime{';
	$output .= 'padding:';
	$output .= get_option('fw_p_paddingbts');
	$output .= 'px 0;}';
	$output .= '.entry{';
	$output .= 'padding-bottom:';
	$output .= get_option('fw_p_paddingbe');
	$output .= 'px;';
	$output .= '}';
	
	$output .= '#fw_filter{margin: 0 10px 5px 0;height: 22px;}';
	
	$output .= '#fw_entries_field{margin: 0 0 5px 5px;}';
	
	$output .= '.fw_e_name{margin: 0 0 5px 10px;}';
	
	$output .= '.fw_source{font-size:';
	$output .= get_option('fw_psf_size');
	$output .= 'px;';
	$output .= 'line-height:';
	$output .= get_option('fw_psf_size');
	$output .= 'px;}';
	
	$output .= '.fw_date{font-size:';
	$output .= get_option('fw_pdtf_size');
	$output .= 'px;}';
	
	$output .= '.fw_attachment_img{max-width:200px;}';
	
	$output .= '.pagination{width:300px;}'; 
	
	$output .= '</style>';
	
	//content by shortcode
	$output .= '<div class = "fw_wrap"><a name="fw_top"></a>';
	if($hide_filter == ""){
		$output .= '<form method="get" action="">';
		$output .= '<select id="fw_filter" name="fw_filter"><option>Select only one source</option>';
		$output .= '<option value="All"';
		if($source == "All"){
			$output .= ' selected';
		}
		$output .= '>All</option>';

		$push = explode("\n",get_option('fw_feeds_list'));
		foreach ($push as $wire_array){
			$push_one = explode(',',$wire_array);

			if($push_one[0]!=''){
				$output .= '<option value="';
				$output .= htmlspecialchars(esc_attr($push_one[0]));
				$output .= '"';

				if( $source == esc_attr($push_one[0])){
					$output .= ' selected';
				}

				$output .= '>';
				$output .= esc_attr($push_one[0]);
				$output .= '</option>';
			}
		}

		$output .= '</select>';
		$output .= '<input id="getb" type="submit" value="go" />';

		$entries = $nn;
		
		$output .= '<span class="fw_e_name">#Entries per page:</span>';
		$output .= '<input type="text" id="fw_entries_field" size="4" name="fw_entries_field" value="';
		$output .= $entries;
		$output .= '"/>';
		
		$output .= '</form>';
		
	}
		
	$output .= '<script type="text/javascript">';
	$output .= 'if(typeof jQuery == \'undefined\'){';
	$output .= 'document.write(\'<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></\'+\'script>\');';
	$output .= '}';
	$output .= '</script>';
	
	

	
	$output .= '<div class="fw_inside">';
	
	if($source === ''){$source = 'All';}
	
	if($source == 'All'){
		$items = get_posts(array('post_type' => 'fw_headlines','numberposts' => -1));
		$count = count($items);
		$wires = get_posts(array('post_type' => 'fw_headlines','numberposts' => $entries));
	}
	else{
		$items = get_posts(array('post_type' => 'fw_headlines','numberposts' => -1,'meta_key' => '_fw_source', 'meta_value' => $source));
		$count = count($items);
		$wires = get_posts(array('post_type' => 'fw_headlines','numberposts' => $entries,'meta_key' => '_fw_source', 'meta_value' => $source));
	}
	
	foreach ($wires as $wire){
		setup_postdata($wire);
	
		if(((get_post_meta($wire->ID,'_fw_source',true) == $source)||($source == "All"))&& (get_post_meta($wire->ID,'_fw_display',true) == '')){
	
			$output .= '<div class="entry">';
			$output .= '<a class="fw_headline" title="';
			$output .= get_the_title($wire->ID);
			$output .= '"href="';
			$output .= get_post_meta($wire->ID,'_fw_url',true);
			$output .= '" ';
			if(get_option('fw_no_follow') == ''){
				$output .= 'rel="noindex,nofollow"';
			}
			if(get_post_meta($wire->ID, '_fw_newwindow', true) == ''){
				$output .= 'target = "_blank"';
			}
			$output .= '>';
			$output .= get_the_title($wire->ID);
			$output .= '</a><div class="fw_dtime">';
			if (esc_attr($hide_source) == ''){
				$output .= '<span class="fw_source" style="padding-right: 7px">';
				$output .= get_post_meta($wire->ID,'_fw_source',true);
				$output .= '</span>';
			}
			$output .= '<span class="fw_date">';
			if (esc_attr($hide_date) == ''){
				if (get_option('fw_date_format') == '1')
					$output .= get_the_time('Y/m/d',$wire->ID);
				elseif (get_option('fw_date_format') == '')
				$output .= get_the_time('m/d/Y',$wire->ID);
			}
			if (esc_attr($hide_time) == ''){
				$output .= get_the_time(' g:ia',$wire->ID);
			}
			$output .= '</span></div>';
			$output .= '</div>';
		}
	}
	
	$output .= '</div>';	
	
	$output .= '<div class="pagination">';
	$output .= '<a class="alignleft" href="#fw_top" id="prev" class="prevnext" style="display:none;">&laquo; Newer</a>';
	$output .= '<a class="alignright" href="#fw_top" id="next" class="prevnext" style="display:none;">Older &raquo;</a>';
	$output .= '<span id="counter" class="'.($count - $entries).'"></span>';
	$output .= '<span id="super-counter" class="0" style="display:none;"></span>';
	$output .= '</div>';
	
	$max_entries = get_option('fw_max_entries');
	
	$output .= '<script type="text/javascript">';
	$output .= 'if('.$count.' > '.$entries.') jQuery("#next").slideDown();';
	$output .= 'jQuery(document).ready(function(){';
		
	$output .= 'var counter = jQuery("#super-counter").attr("class");';
	
	$output .= 'jQuery("#next").click(function(){';
	$output .= 'var fentries = jQuery("#fw_entries_field").val();';
	$output .= 'if(fentries > '.$max_entries.') {fentries = '.$max_entries.';}';
	$output .= 'jQuery("#fw_entries_field").val(fentries);';
	$output .= 'var ffilter = jQuery("#fw_filter").val();';
	$output .= 'jQuery("#prev").slideDown();';
	$output .= 'var id = jQuery(this);';
	$output .= 'counter = jQuery("#super-counter").attr("class");';
	$output .= 'counter++;';
	$output .= 'jQuery("#super-counter").attr("class",counter);';
	$output .= 'jQuery.ajax({';
	$output .= 'type: \'POST\',';
	$output .= 'url: "'.admin_url('admin-ajax.php').'",';
	$output .= 'data: {"action": "shortcode_pagination",';
	$output .= '"fw_filter": ffilter,';
	$output .= '"fw_entries": fentries,';
	$output .= '"fw_counter": counter,';
	$output .= '},';
	$output .= 'success: function(data){';
	$output .= 'var items_counter = jQuery("#counter").attr("class");';
	$output .= 'items_counter -= fentries;';
	$output .= 'jQuery("#counter").attr("class",items_counter);';
	$output .= 'jQuery(".fw_inside").fadeToggle(function(){jQuery(".fw_inside").html(data);jQuery(".fw_inside").fadeToggle();});';
	$output .= 'if(items_counter <= 0) jQuery("#next").slideUp();';
	$output .= '}';
	$output .= '});';
	//$output .= 'return false;';
	$output .= '});';
	
	$output .= 'jQuery("#prev").click(function(){';
	$output .= 'var fentries = jQuery("#fw_entries_field").val();';
	$output .= 'if(fentries > '.$max_entries.') {fentries = '.$max_entries.';}';
	$output .= 'jQuery("#fw_entries_field").val(fentries);';
	$output .= 'var ffilter = jQuery("#fw_filter").val();';
	$output .= 'jQuery("#next").slideDown();';
	$output .= 'var id = jQuery(this);';
	$output .= 'counter = jQuery("#super-counter").attr("class");';
	$output .= 'counter--;';
	$output .= 'jQuery("#super-counter").attr("class",counter);';
	$output .= 'if (counter <= 0) jQuery("#prev").slideUp();';
	$output .= 'jQuery.ajax({';
	$output .= 'type: \'POST\',';
	$output .= 'url: "'.admin_url('admin-ajax.php').'",';
	$output .= 'data: {"action": "shortcode_pagination",';
	$output .= '"fw_filter": ffilter,';
	$output .= '"fw_entries": fentries,';
	$output .= '"fw_counter": counter,';
	$output .= '},';
	$output .= 'success: function(data){';
	$output .= 'var items_counter = jQuery("#counter").attr("class");';
	$output .= 'items_counter = parseInt(items_counter) + parseInt(fentries);';
	$output .= 'jQuery("#counter").attr("class",items_counter);';
	$output .= 'jQuery(".fw_inside").fadeToggle(function(){jQuery(".fw_inside").html(data);jQuery(".fw_inside").fadeToggle();});';
	$output .= '}';
	$output .= '});';
	//$output .= 'return false;';
	$output .= '});';
	$output .= '});';
	
	$output .= 'jQuery("#getb").click(function(){';
	$output .= 'counter = 0;';
	$output .= 'jQuery("#super-counter").attr("class",counter);';
	$output .= 'jQuery("#prev").slideUp();';
	$output .= 'var fentries = jQuery("#fw_entries_field").val();';
	$output .= 'if(fentries > '.$max_entries.') {fentries = '.$max_entries.';}';
	$output .= 'jQuery("#fw_entries_field").val(fentries);';
	$output .= 'var ffilter = jQuery("#fw_filter").val();';
	//$output .= 'console.log(ffilter);';
	$output .= 'jQuery.ajax({';
	$output .= 'type: \'POST\',';
	$output .= 'url: "'.admin_url('admin-ajax.php').'",';
	$output .= 'data:{"action": "shortcode_get_count",';
	$output .= '"fw_filter": ffilter,';
	$output .= '},success: function(data){';
	$output .= 'jQuery("#counter").attr("class",(data - fentries));';
	$output .= 'var items_counter = jQuery("#counter").attr("class");';
	$output .= 'if(items_counter <= 0) jQuery("#next").slideUp();';
	$output .= 'else jQuery("#next").slideDown();';
	$output .= '}';
	$output .= '});';
	$output .= 'jQuery.ajax({';
	$output .= 'type: \'POST\',';
	$output .= 'url: "'.admin_url('admin-ajax.php').'",';
	$output .= 'data:{"action": "shortcode_pagination",';
	$output .= '"fw_filter": ffilter,';
	$output .= '"fw_entries": fentries,';
	$output .= '"fw_counter": counter,';
	$output .= '},success: function(data){';
	$output .= 'jQuery(".fw_inside").fadeToggle(function(){jQuery(".fw_inside").html(data);jQuery(".fw_inside").fadeToggle();});';
	$output .= '}});';
	$output .= 'return false;';
	$output .= '});';
	
	$output .= '</script>';
	
	$output .= '</div>';
	return $output;
}
add_shortcode( 'soundstfeedwire', 'soundstfeedwire_func' );

/*
 * Soundst Feedwire widget
*/
/**
 * Plugin Name: Soundst Feedwire
 * Description: Use this widget to display the wire.
 * Version: 1.0
 * Author: Sound Strategies Inc.
 * Author URI: http://www.soundst.com/
 */
add_action( 'widgets_init', 'soundst_feedwire_widget' );
function soundst_feedwire_widget() {
	register_widget( 'FW_Widget' );
}
class FW_Widget extends WP_Widget {

	function FW_Widget() {
		$widget_ops = array( 'classname' => 'ssfw', 'description' => 'Use this widget to display the wire');

		$control_ops = array( 'width' => 250, 'height' => 350, 'id_base' => 'fw-widget' );

		$this->WP_Widget( 'fw-widget', 'Soundst Feedwire', $widget_ops, $control_ops );
	}

	function widget( $args, $instance ) {
		extract( $args );

		//Our variables from the widget settings.
		$fw_title = apply_filters('widget_title', $instance['title'] );
		$fw_nn = $instance['fw_nn'];
		$fw_filter = $instance['fw_filter'];
		$fw_hs = $instance['fw_hs'];
		$fw_hd = $instance['fw_hd'];
		$fw_ht = $instance['fw_ht'];
		$fw_ahtml = $instance['fw_ahtml'];
		$fw_uhtml = $instance['fw_uhtml'];
		
		echo $before_widget;

		// Display the widget title
		if ( $fw_title )
			echo $before_title . $fw_title . $after_title;

			$fw_ahtml = str_replace("\r\n",'',$fw_ahtml);
			$fw_ahtml = str_replace("\n",'',$fw_ahtml);
			
			$fw_uhtml = str_replace("\r\n",'',$fw_uhtml);
			$fw_uhtml = str_replace("\n",'',$fw_uhtml);
		
		echo '<script type="text/javascript">jQuery(function($){';
		echo '$(".fw_widget").prepend("'.addcslashes($fw_uhtml, "<>\"").'<div id=\"fw_inside\"></div>'.addcslashes($fw_ahtml, "<>\"").'");';
		
		$wires = get_posts(array('post_type' => 'fw_headlines','numberposts' => -1));
		$is_end = $fw_nn;
		foreach ($wires as $wire){
			if($is_end == 0)
				break;
			setup_postdata($wire);
			if((($fw_filter == "")||(get_post_meta($wire->ID,'_fw_source',true) == $fw_filter)) && (get_post_meta($wire->ID,'_fw_display',true) == '')){
				$is_end--;
				
				if(get_option('fw_no_follow') == ''){
					$rel = 'noindex,nofollow';
				} else {
					$rel = '';
				}
				
				if(get_post_meta($wire->ID, '_fw_newwindow', true) == ''){
					$target = '_blank';
				}
				else $target = '_self';
				
				echo '$("<a>").attr("class", "fw_widget_headline fw_widget_headline'.($fw_nn-$is_end).'").attr("title", "'.get_the_title($wire->ID).'").attr("href","'.get_post_meta($wire->ID,'_fw_url',true).'").attr("target","'.$target.'").attr("rel","'.$rel.'").text("'.addcslashes(html_entity_decode(htmlspecialchars_decode(get_the_title($wire->ID),ENT_QUOTES),ENT_COMPAT, 'UTF-8'),"\"").'").appendTo("#fw_inside"); ';
				echo '$("<div>").attr("class","fw_widget_dtime fw_widget_dtime'.($fw_nn-$is_end).'").appendTo("#fw_inside");';
				if ($fw_hs == ''){
					echo '$("<span>").attr("class","fw_widget_source").text("'.get_post_meta($wire->ID,'_fw_source',true).'").appendTo(".fw_widget_dtime'.($fw_nn-$is_end).'");';
				}
				
				if ($fw_hd == ''){
					if (get_option('fw_date_format') == '1')
						$jstime = get_the_time('Y/m/d',$wire->ID);
					elseif (get_option('fw_date_format') == '')
					$jstime = get_the_time('m/d/Y',$wire->ID);
				}
				
				if ($fw_ht == ''){
					$jsadvtime = get_the_time(' g:ia',$wire->ID);
				} else $jsadvtime = '';
				echo '$("<span>").attr("class","fw_widget_date fw_widget_date'.($fw_nn-$is_end).'").text("'.$jstime.' '.$jsadvtime.'").appendTo(".fw_widget_dtime'.($fw_nn-$is_end).'");';
				
			}
		}
		
		echo '})(jQuery);</script>';
		
		echo '<style type=\'text/css\'>';
		
		echo '.fw_widget_headline{font-size:'.get_option('fw_whf_size').'px;line-height:'.get_option('fw_w_line_height').'px;}';
		echo '.fw_widget_dtime{padding-top:'.get_option('fw_w_paddingbts').'px;padding-bottom:'.get_option('fw_w_paddingbe').'px;}';
		echo '.fw_widget_source{font-size:'.get_option('fw_wsf_size').'px;line-height:'.get_option('fw_w_line_height').'px;padding-right:7px;}';
		echo '.fw_widget_date{font-size:'.get_option('fw_wdtf_size').'px;}';
		echo '.fw_widget{padding-bottom:'.get_option('fw_w_vrtpadding').'px;padding-top:'.get_option('fw_w_vrtpadding').'px;padding-left:'.get_option('fw_w_hrzpadding').'px;padding-right:'.get_option('fw_w_hrzpadding').'px;line-height:'.get_option('fw_w_line_height').'px;}';
		
		echo '</style>';
		
		echo '<div class="fw_widget"></div>';		
		
		echo $after_widget;
	}

	//Update the widget

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		//Strip tags from title and name to remove HTML
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['fw_nn'] = strip_tags( $new_instance['fw_nn'] );
		$instance['fw_filter'] = strip_tags( $new_instance['fw_filter'] );
		$instance['fw_hs'] = strip_tags( $new_instance['fw_hs'] );
		$instance['fw_hd'] = strip_tags( $new_instance['fw_hd'] );
		$instance['fw_ht'] = strip_tags( $new_instance['fw_ht'] );
		$instance['fw_ahtml'] = $new_instance['fw_ahtml'];
		$instance['fw_uhtml'] = $new_instance['fw_uhtml'];
		
		return $instance;
	}


	function form( $instance ) {

		//Set up default widget settings.
		$defaults = array( 
				'title' => 'RR', 
				'fw_nn' => 20, 
				'fw_filter' => '', 
				'fw_hs' => '', 
				'fw_hd' => '', 
				'fw_ht' => '' , 
				'fw_ahtml' => 'FeedWire powered by <a href="http://soundst.com" target="_blank">Sound Strategies</a>',
				'fw_uhtml' => ''
			);
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width: 200px;" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'fw_nn' ); ?>">Number of headlines to display:</label>
			<input id="<?php echo $this->get_field_id( 'fw_nn' ); ?>" name="<?php echo $this->get_field_name( 'fw_nn' ); ?>" value="<?php echo $instance['fw_nn']; ?>" style="width: 40px;" />
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'fw_filter' ); ?>">Source filter (blank for all):</label>
			<select id="<?php echo $this->get_field_id( 'fw_filter' ); ?>" name="<?php echo $this->get_field_name( 'fw_filter' ); ?>"><option></option>
        	<?php 
	        	$selected = '';
    	    	$push = explode("\n",get_option('fw_feeds_list'));
        		foreach ($push as $wire_array){
	        		$push_one = explode(',',$wire_array);
    	    	
	        		if($push_one[0]!=''){
						if( $instance['fw_filter'] == $push_one[0]){
							$selected = ' selected';
						}
						else {
							$selected = '';
						}?>
        				<option value="<?php echo $push_one[0];?>" <?php echo $selected;?>>
        					<?php echo $push_one[0];?>
        				</option>
        				<?php 
	        		}
    	    	}
        	?>	
        	</select>
        </p>
        
		<p>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'fw_hs' ); ?>" name="<?php echo $this->get_field_name( 'fw_hs' ); ?>" value="1" <?php if($instance['fw_hs'] == "1") echo ' checked';?>/>
			<label for="<?php echo $this->get_field_id( 'fw_hs' ); ?>">Hide source</label>
		</p>
		
		<p>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'fw_hd' ); ?>" name="<?php echo $this->get_field_name( 'fw_hd' ); ?>" value="1" <?php if($instance['fw_hd'] == "1") echo ' checked'; ?>/>
			<label for="<?php echo $this->get_field_id( 'fw_hd' ); ?>">Hide date</label>
		</p>
		
		<p>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'fw_ht' ); ?>" name="<?php echo $this->get_field_name( 'fw_ht' ); ?>" value="1" <?php if($instance['fw_ht'] == "1") echo ' checked'; ?>/>
			<label for="<?php echo $this->get_field_id( 'fw_ht' ); ?>">Hide time</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'fw_uhtml' ); ?>">Upper HTML (optional):</label>
			<textarea id="<?php echo $this->get_field_id( 'fw_uhtml' ); ?>" name="<?php echo $this->get_field_name( 'fw_uhtml' ); ?>" style="width: 100%; height: 100px; max-width: 100%; max-height: 200px"><?php echo $instance['fw_uhtml']; ?></textarea>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'fw_ahtml' ); ?>">Lower HTML (optional):</label>
			<textarea id="<?php echo $this->get_field_id( 'fw_ahtml' ); ?>" name="<?php echo $this->get_field_name( 'fw_ahtml' ); ?>" style="width: 100%; height: 100px; max-width: 100%; max-height: 200px"><?php echo $instance['fw_ahtml']; ?></textarea>
		</p>
	<?php
	}
}

add_action('fw_custom_update','do_this_hourly');
function fw_activation() {
	
	if ( !wp_next_scheduled( 'fw_custom_update' ) ) {
		wp_schedule_event( time(), 'custom', 'fw_custom_update');
	}
}
add_action('wp', 'fw_activation');
function do_this_hourly() {
	global $fw_sem;
	$sem = $fw_sem->getSemaphore(SSFW_BASE_DIRECTORY, SSFW_UNIQUE_KEY, 5);
	if($sem) {
		echo "Got lock <br>";
	} else {
		echo "Failed to get lock. Boohoo! <br />";
		die();
	}
	fw_make_all_posts('semaphore_shedule');
	$fw_sem->releaseSemaphore();
}

add_filter( 'cron_schedules', 'cron_add_custom' );
function cron_add_custom( $schedules ) {
	if((get_option('fw_shedule_freq') != "") && (is_numeric(get_option('fw_shedule_freq')))){
		$custom_time = get_option('fw_shedule_freq')*60;
	}
	else
		$custom_time = 3600;
	// Adds once weekly to the existing schedules.
	$schedules['custom'] = array(
			'interval' => $custom_time,
			'display' => __( 'Once Custom' )
	);
	return $schedules;
}

//XML parsing
function fw_parse($input){

	libxml_use_internal_errors(true);
	$xml_str = file_get_contents($input);
	$dom = simplexml_load_string($xml_str);
	if ($dom === false) {
		$output['warning'] = "XML is not valid";
		foreach(libxml_get_errors() as $error) {
			$output['warning'] .= "\tWarning $error->code:\n\t$error->message in line: $error->line\nTrying to replace entities.\n";
		}
		$xml_str = preg_replace('/&[^; ]{0,6}.?/e', "((substr('\\0',-1) == ';') ? '\\0' : '&amp;'.substr('\\0',1))", $xml_str);
		$dom = simplexml_load_string($xml_str);
		if ($dom === false) {
			$output['error'] = "Failed loading XML\n";
			foreach(libxml_get_errors() as $error) {
				$output['error'] .= "\tError $error->code:\n\t$error->message in line: $error->line";
			}
			return $output;
		}
		else $output['warning'] .= 'Success';
	}
	
	$counter = 0;
	if($dom->xpath('//item')){
		foreach ($dom->xpath('//item') as $item) {

			//title
			$output[$counter]['title'] = $item->title;
			
			//date
			$output[$counter]['date'] = $item->pubDate;
			$output[$counter]['date'] = strftime("%Y-%m-%d %H:%M:%S", strtotime($output[$counter]['date']));
			$output[$counter]['date'] = get_date_from_gmt($output[$counter]['date']);
		
			//url
			$output[$counter]['url'] = $item->link;

			$counter++;
		}
	} elseif($dom->entry){
		
		foreach ($dom->entry as $item){
			
			//title
			$output[$counter]['title'] = $item->title;
			
			//date
			$output[$counter]['date'] = $item->published;
			$output[$counter]['date'] = strftime("%Y-%m-%d %H:%M:%S", strtotime($output[$counter]['date']));
			$output[$counter]['date'] = get_date_from_gmt($output[$counter]['date']);
			
				
			//url
			$output[$counter]['url'] = $item->link["href"];
			$counter++;
		}
	}
	
	
	return $output;
}

// new headlines creating
function fw_make_all_posts($semaphore_shed){

	$log_file = dirname(__FILE__).'/feed_wire.log';
	
	$last_updated = get_option('fw_last_updated_list');
	$update_status = get_option('fw_update_status_list');
//	trigger_error('schedule:'.serialize($last_updated),E_USER_WARNING);
	$num = 0;
	if(get_option('fw_feeds_list')){
		$push = explode("\n",get_option('fw_feeds_list'));
		$temp_count = 0;
		foreach ($push as $wire_array){
			$temp_count++;
			$key = explode(',',$wire_array);
			if($last_updated[esc_attr($key[0])] === min(array_values($last_updated))){
				$num = $temp_count;
				$last_updated[esc_attr($key[0])] = time();
				$last_update_time = get_date_from_gmt(date('Y-m-d H:i:s',$last_updated[esc_attr($key[0])]));
				update_option('fw_last_updated_list',$last_updated);
//				trigger_error('scheduled run:'.$key[0],E_USER_WARNING);
				$check = fw_make_posts($num,'semaphore_shed');
								
				if($check){
					//throw the error
					if($check['error']){
						$time = get_date_from_gmt(date('Y-m-d H:i:s', time()));
						$errr = $check['error'];
						$log_msg = "[$time] $key[0] updating process. FAILURE: $errr";
						//write_log
						if(get_option('fw_enbl_lgg') == '1'){
							file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX);
						}
						$update_status[esc_attr($key[0])]['status'] = '<span class="failure">failure</span>';
						$update_status[esc_attr($key[0])]['descr'] = $errr;
						update_option('fw_update_status_list',$update_status);
					} elseif($check['warning']){
						//throw warning
						$warn = $check['warning'];
						$log_msg = "[$last_update_time] $key[0] updating process. WARNING: $warn\n";
						$log_msg = preg_replace("/\s\s+/", " ", $log_msg);
						//write log
						if(get_option('fw_enbl_lgg') == '1'){
							file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX);
						}
						$update_status[esc_attr($key[0])]['status'] = '<span class="warning">warning</span>';
						$update_status[esc_attr($key[0])]['descr'] = $warn;
						update_option('fw_update_status_list',$update_status);
					} else {
						$log_msg = "[$last_update_time] $key[0] updating process. SUCCESS\n";
						if(get_option('fw_enbl_lgg') == '1'){
							file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX);
						}
						$update_status[esc_attr($key[0])]['status'] = '<span class="success">success</span>';
						$update_status[esc_attr($key[0])]['descr'] = '';
						update_option('fw_update_status_list',$update_status);
						
					}
				} else {
					$time = get_date_from_gmt(date('Y-m-d H:i:s', time()));
					$log_msg = "[$time] $key[0] updating process. FAILURE: One updating process is working already.\n";
					if(get_option('fw_enbl_lgg') == '1'){
							file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX);
					}
					$update_status[esc_attr($key[0])]['status'] = '<span class="failure">failure</span>';
					$update_status[esc_attr($key[0])]['descr'] = 'Trying to run two concurrent updates';
					update_option('fw_update_status_list',$update_status);
				}
				
				break;
			}
		}
	}
}

// new headlines creating for one feed
function fw_make_posts($feed_index, $semaphore_shed){
	
	add_action('init', 'fw_parse');
	if(get_option('fw_feeds_list')){
		
		$push = explode("\n",get_option('fw_feeds_list'));
		$temp_count = 0;
		foreach ($push as $wire_array){
			$temp_count++;
			$push_one = explode(',',$wire_array);
			if(($push_one[1] !== '') && ($feed_index == $temp_count)){
				$input = fw_parse($push_one[1]);
				if ($input['error']){
					return $input;
				} if($input['warning']) {
					$warning = TRUE;
				}
				if (is_array($input)){
				foreach ($input as $one){
					$fw_post_exist = 0;
					
					/*$fw_nod = get_posts(array('post_type' => 'fw_headlines','numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_fw_source', 'meta_value' => $push_one[0]));
					foreach ($fw_nod as $fw_nod_post){
						setup_postdata($fw_nod_post);
						if(( $fw_nod_post->post_title == esc_attr($one[title]))||( get_post_meta($fw_nod_post->ID,'_fw_url',true) == esc_attr($one[url]))){
							$fw_post_exist = 1;
						}
						wp_reset_postdata();
					}
					wp_reset_query();*/
					
					$sqsource = esc_sql(esc_attr($push_one[0]));
					$squrl = esc_attr($one[url]);
					$sqtitle = esc_attr($one[title]);
					
					global $wpdb;
					$querystr = "SELECT wposts.post_name, wposts.post_title, wposts.ID
						FROM  $wpdb->posts AS wposts
						INNER JOIN $wpdb->postmeta wpostmeta ON wposts.ID = wpostmeta.post_id
						AND wpostmeta.meta_key =  '_fw_source'
						AND wpostmeta.meta_value =  '$sqsource'
						INNER JOIN  $wpdb->postmeta wpostmeta2 ON wposts.ID = wpostmeta2.post_id
						AND wpostmeta2.meta_key =  '_fw_url'
						AND wpostmeta2.meta_value =  '$squrl'
						AND wposts.post_date < NOW( ) 
	    			 	AND wposts.post_status = 'publish'
						ORDER BY wposts.post_title ASC";
					
					$results = $wpdb->get_results($querystr, OBJECT);
					if(count($results)>0)
						$fw_post_exist = 1;
					
					if(($fw_post_exist == 0) && (strtotime($one[date]) < (int)current_time('timestamp'))){
						if((time() - strtotime($one[date])) < (86400*get_option('fw_keep_wire')))
						{
							$fw_post = array(
								'post_title' => esc_attr($one[title]),
								'post_date' => $one[date],
								'post_type' => 'fw_headlines',
								'post_status' => 'publish',
								'post_date_gmt' => get_gmt_from_date($one[date])
							);
							/*$fw_post_id = wp_insert_post( $fw_post );*/
							global $wpdb;
							$wpdb->insert(
								$wpdb->posts,
								$fw_post
							);
							$fw_post_id = $wpdb->insert_id;
							add_post_meta($fw_post_id, '_fw_source', esc_attr($push_one[0]), true);
							add_post_meta($fw_post_id, '_fw_url', esc_attr($one[url]), true);
							if($push_one[2] == 'Y')
								add_post_meta($fw_post_id, '_fw_display', '',true);
							elseif($push_one[2] == 'N')
								add_post_meta($fw_post_id, '_fw_display', '1',true);
							if($push_one[3] == 'Y')
								add_post_meta($fw_post_id, '_new_window', '',true);
							elseif($push_one[2] == 'N')
								add_post_meta($fw_post_id, '_new_window', '1',true);
							add_post_meta($fw_post_id, '_when_added', time(),true);
							add_post_meta($fw_post_id, '_post_exist', $semaphore_shed,true);
						}
					}
				}/*end foreach*/
				}/*end if*/
				else{
					return FALSE;
				}
			}
		}
			
	}
	if($warning){
		return $input;
	}
	return TRUE;
}

//daily post removing
add_action('fw_daily_event', 'do_this_daily');

function fw_del_activation() {
	if ( !wp_next_scheduled( 'fw_daily_event' ) ) {
		wp_schedule_event( time(), 'daily', 'fw_daily_event');
	}
}
add_action('wp', 'fw_del_activation');

function do_this_daily() {
	fw_delete_posts();
}

function fw_delete_posts(){
	$all = get_posts(array('post_type' => 'fw_headlines','numberposts' => -1, 'post_status' => 'any'));
	foreach ($all as $one){
		setup_postdata($one);
		if ( (get_option('fw_keep_wire') !== FALSE) || (get_option('fw_keep_wire') != '') )
			if((time() - get_the_time('U',$one->ID)) > (86400*get_option('fw_keep_wire')))
				wp_delete_post( $one->ID, true );
	}
}

function update_feed(){

	$update_status = get_option('fw_update_status_list');

	$num = substr($_POST['checkdatype'],11);

	if(get_option('fw_feeds_list')){
		$push = explode("\n",get_option('fw_feeds_list'));
		$temp_count = 0;
		foreach ($push as $wire_array){
			$temp_count++;
			$key = explode(',',$wire_array);
			if($temp_count === intval($num)){
				$name_feed = esc_attr($key[0]);
				break;
			}
		}
	}
	
	$log_file = dirname(__FILE__).'/feed_wire.log';
	global $fw_sem;
	$sem = $fw_sem->getSemaphore(SSFW_BASE_DIRECTORY, SSFW_UNIQUE_KEY, 5);
	if($sem) {
		//echo "The feed was updated";
	} else {
		$update_status[$name_feed]['status'] = '<span class="failure">failure</span>';
		$update_status[$name_feed]['descr'] = 'Trying to run two concurrent updates';
		update_option('fw_update_status_list',$update_status);
		$data = array("message"=>"Updating process working now!","status"=>"failure");
		echo json_encode($data);
		die();
	}
	
	
	$check = fw_make_posts($num,'semaphore');
	
	
	if($check){
		//throw the error
		if($check['error']){
			$time = get_date_from_gmt(date('Y-m-d H:i:s', time()));
			$errr = $check['error'];
			$log_msg = "[$time] $name_feed updating process. FAILURE: $errr";
			//write_log
			if(get_option('fw_enbl_lgg') == '1'){
				if(!file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX)){
					$append = "\n[Cannot create the log file. Please make sure that your plugin directory has access for writing files.]";
				}
			}
			$rep_msg = $check['error'].$append;
			
			$update_status[$name_feed]['status'] = '<span class="failure">failure</span>';
			$update_status[$name_feed]['descr'] = $errr;
			
			update_option('fw_update_status_list',$update_status);
			
			$data = array("message"=>$rep_msg,"status"=>"failure");
			echo json_encode($data);
			die();
		}
		
		$last_updated = get_option('fw_last_updated_list');

		if(get_option('fw_feeds_list')){
			$push = explode("\n",get_option('fw_feeds_list'));
			$temp_count = 0;
			foreach ($push as $wire_array){
				$temp_count++;
				$key = explode(',',$wire_array);
				if($temp_count === intval($num)){
					$last_update_time = $last_updated[esc_attr($key[0])] = time();
					$last_update_time = get_date_from_gmt( date( 'Y-m-d H:i:s', $last_updated[esc_attr($key[0])] ), 'd M Y (h:i:s)' );
					update_option('fw_last_updated_list',$last_updated);
					break;
				}
			}
		}
		if($check['warning']){
			
			//throw warning
			$warn = $check['warning'];
			$log_msg = "[$last_update_time] $name_feed updating process. WARNING: $warn\n";
			$log_msg = preg_replace("/\s\s+/", " ", $log_msg);
			//write log
			if(get_option('fw_enbl_lgg') == '1'){
				if(!file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX)){
					$append = "\n[Cannot create the log file. Please make sure that your plugin directory has access for writing files.]";
				}
			}
			$rep_msg = $check['warning'].$append;
			$rep_msg = nl2br($rep_msg);
			
			$update_status[$name_feed]['status'] = '<span class="warning">warning</span>';
			$update_status[$name_feed]['descr'] = nl2br($warn);
			update_option('fw_update_status_list',$update_status);
			
			
			$data = array("message"=>$rep_msg,"last_update"=>$last_update_time,"status"=>"warning");
		} else {
			
			$log_msg = "[$last_update_time] $name_feed updating process. SUCCESS\n";
			if(get_option('fw_enbl_lgg') == '1'){
				if(!file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX)){
					$append = "\n[Cannot create the log file. Please make sure that your plugin directory has access for writing files.]";
				}
			}
			$rep_msg = "The feed was updated".$append;
			
			$update_status[$name_feed]['status'] = '<span class="success">success</span>';
			$update_status[$name_feed]['descr'] = '';
			update_option('fw_update_status_list',$update_status);
			
			$data = array("message"=>$rep_msg,"last_update"=>$last_update_time,"status"=>"success");
		}
	} else {
		$time = get_date_from_gmt(date('Y-m-d H:i:s', time()));
		$log_msg = "[$time] $name_feed updating process. FAILURE: One updating process is working already.\n";
		if(get_option('fw_enbl_lgg') == '1'){
			if(!file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX)){
				$append = "\n[Cannot create the log file. Please make sure that your plugin directory has access for writing files.]";
			}
		}
		$rep_msg = "XML parsing failure".$append;
		
		$update_status[$name_feed]['status'] = '<span class="failure">failure</span>';
		$update_status[$name_feed]['descr'] = "Trying to run two concurrent updates";
		update_option('fw_update_status_list',$update_status);
		
		$data = array("message"=>$rep_msg,"status"=>"failure");
	}
	
	
	
	$fw_sem->releaseSemaphore();
	echo json_encode($data);
	die();
}
add_action( 'wp_ajax_update_feed', 'update_feed' );


function shortcode_pagination(){
	$output = '';
	if($_POST['fw_filter']){$source = $_POST['fw_filter'];}
		if($_POST['fw_filter'] == "Select only one source"){$source = 'All';}
			if($source == 'All')
				$wires = get_posts(array('post_type' => 'fw_headlines','numberposts' => $_POST['fw_entries'],'offset' => $_POST['fw_entries']*$_POST['fw_counter']));
			else
				$wires = get_posts(array('post_type' => 'fw_headlines','numberposts' => $_POST['fw_entries'],'offset' => $_POST['fw_entries']*$_POST['fw_counter'],'meta_key' => '_fw_source', 'meta_value' => $source));
			foreach ($wires as $wire){
				setup_postdata($wire);
		
				if(get_post_meta($wire->ID,'_fw_display',true) == ''){
					
					//$count--;
					
					$output .= '<div class="entry">';
					$output .= '<a class="fw_headline" title="';
					$output .= get_the_title($wire->ID);
					$output .= '"href="';
					$output .= get_post_meta($wire->ID,'_fw_url',true);
					$output .= '" ';
					if(get_option('fw_no_follow') == ''){
						$output .= 'rel="noindex,nofollow"';
					}
					if(get_post_meta($wire->ID, '_fw_newwindow', true) == ''){
						$output .= 'target = "_blank"';
					}
					$output .= '>';
					$output .= get_the_title($wire->ID);
					$output .= '</a><div class="fw_dtime">';
					if (esc_attr($hide_source) == ''){
						$output .= '<span class="fw_source" style="padding-right: 7px">';
						$output .= get_post_meta($wire->ID,'_fw_source',true);
						$output .= '</span>';
					}
					$output .= '<span class="fw_date">';
					if (esc_attr($hide_date) == ''){
						if (get_option('fw_date_format') == '1')
							$output .= get_the_time('Y/m/d',$wire->ID);
						elseif (get_option('fw_date_format') == '')
						$output .= get_the_time('m/d/Y',$wire->ID);
					}
					if (esc_attr($hide_time) == ''){
						$output .= get_the_time(' g:ia',$wire->ID);
					}
					$output .= '</span></div>';
					$output .= '</div>';
				}
			}
	echo $output;
	die();
}
add_action( 'wp_ajax_shortcode_pagination', 'shortcode_pagination' );
add_action( 'wp_ajax_nopriv_shortcode_pagination', 'shortcode_pagination' );

function get_count(){
	if(($_POST['fw_filter'] == 'All')||($_POST['fw_filter'] == 'Select only one source')) {
		$items = get_posts(array('post_type' => 'fw_headlines','numberposts' => -1));
		$count = count($items);
	} else {
		$items = get_posts(array('post_type' => 'fw_headlines','numberposts' => -1,'meta_key' => '_fw_source', 'meta_value' => $_POST['fw_filter']));
		$count = count($items);
	}
	echo $count;
	die();
}
add_action( 'wp_ajax_shortcode_get_count', 'get_count' );
add_action( 'wp_ajax_nopriv_shortcode_get_count', 'get_count' );
