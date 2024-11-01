<?php 
add_action('init', 'fw_headlines_type');
function fw_headlines_type(){
	$labels = array(
			'name' => 'Wire'
			,'singular_name' => 'Headline'
			,'add_new' => 'Add New'
			,'add_new_item' => 'Add New Headline'
			,'edit_item' => 'Edit Headline'
			,'new_item' => 'New Headline'
			,'view_item' => 'View Headline'
			,'search_items' => 'Search Headline'
			,'not_found' => ''
			,'not_found_in_trash' => ''
			,'parent_item_colon' => 'Parent'
			,'menu_name' => 'Wire'
	);
	$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'exclude_from_search'=>true,
			'show_ui' => true,
			'query_var' => true,
			'show_in_menu' => true,
			'show_in_menu' => 'FWPluginPage',
			'rewrite' => array('slug' => 'fw_headlines'),
			'capability_type' => 'post',
			'has_archive' => true,
			'menu_position' => null,
			'supports' => array('title')
	);
	register_post_type( 'fw_headlines', $args );
}

add_action( 'add_meta_boxes', 'add_fw_headlines_custom_box' );

function add_fw_headlines_custom_box() {
	$screens = array( 'fw_headlines');
	foreach ($screens as $screen) {
		add_meta_box(
		'fw_headlines_sectionid',
		__( 'Wire source and URL', 'fw_headlines_textdomain' ),
		'fw_headlines_box',
		$screen
		);
	}
}

function fw_headlines_box( $post ) {
	// Use nonce for verification
	wp_nonce_field( plugin_basename( __FILE__ ), 'fw_headlines_noncename' );

	// The actual fields for data entry
	// Use get_post_meta to retrieve an existing value from the database and use the value for the form
	if(get_post_meta( $post->ID, '_fw_source', true )){
		$value[fw_source] = get_post_meta( $post->ID, '_fw_source', true );
	}
	else{
		$value[fw_source] = get_option('fw_def_source');
	}
	$value[fw_url] = get_post_meta( $post->ID, '_fw_url', true );
	$value[fw_media] = get_post_meta( $post->ID, '_fw_media', true );
	$value[fw_mime] = get_post_meta( $post->ID, '_fw_mime', true );
	$value[fw_mtitle] = get_post_meta( $post->ID, '_fw_mtitle', true );
	$value[fw_display] = get_post_meta( $post->ID, '_fw_display', true );
	$value[fw_newwindow] = get_post_meta( $post->ID, '_fw_newwindow', true );
	
	//source ?>
	<div class="edit-space">
		<div class="edit-name">
			<label for="fw_source_field">Source</label>
		</div>
		<input type="text" id="fw_source_field" name="fw_source_field" value="<?=(esc_attr($value[fw_source]))?>"/>
		<div class="clear"></div>
	</div>
	<?php //url ?>
	<div class="edit-space">
		<div class="edit-name">
			<label for="fw_url_field">URL</label>
		</div>
		<input type="text" id="fw_url_field" name="fw_url_field" value="<?=(esc_attr($value[fw_url]))?>"/>
		<div class="clear"></div>
	</div>
	<?php //media?>
	<div class="edit-space">
		<div class="edit-name">
			<label for="fw_custom_media_upload">&nbsp;</label>
		</div>
		<label for="fw_custom_media_upload">or link to media</label>
		<a href="#" class="button fw_custom_media_upload" title="Add Media">Add Media</a>
		<input id="fw_media_field" type="text" name="fw_media_field" value="<?=(esc_attr($value[fw_media]))?>" style="display:none;"/>
		<input id="fw_mime_field" type="text" name="fw_mime_field" value="<?=(esc_attr($value[fw_mime]))?>" style="display:none;"/>
		<input id="fw_mtitle_field" type="text" name="fw_mtitle_field" value="<?=(esc_attr($value[fw_mtitle]))?>" style="display:none;"/>
		<div class="clear"></div>
	</div>
<?php  
wp_enqueue_media();
?>
<script type="text/javascript">
  jQuery('.fw_custom_media_upload').click(function(e) {
	    e.preventDefault();
	    
	    var custom_uploader = wp.media({
	        title: 'Media Library',
	        button: {
	            text: 'Select Media',
	        },
	        multiple: false  // Set this to true to allow multiple files to be selected
	    })
	    .on('select', function() {
	        var attachment = custom_uploader.state().get('selection').first().toJSON();
	        jQuery('#fw_media_field').val(1);
	        jQuery('#fw_url_field').val(attachment.url);
	        jQuery('#fw_mime_field').val(attachment.mime);
	        console.log(attachment.mime);
	        jQuery('#fw_mtitle_field').val(attachment.title);
	    })
	    .open();
	});
</script>
	<?php //display ?>
	<div class="edit-space">
		<div class="edit-name">
			<label for="fw_display_field">Display</label>
		</div>
		<input type="radio" id="fw_display_field" name="fw_display_field" value="" <?php if($value[fw_display] == "") echo 'checked';?>/>Yes
		<input type="radio" id="fw_display_field1" name="fw_display_field" value="1" <?php if($value[fw_display] == "1") echo 'checked';?>/>No
		<div class="clear"></div>
	</div>
	<?php //new window ?>
	<div class="edit-space">
		<div class="edit-name">
			<label for="fw_newwindow_field">New Window</label>
		</div>
		<input type="radio" id="fw_newwindow_field" name="fw_newwindow_field" value="" <?php if($value[fw_newwindow] == "") echo 'checked';?>/>Yes
		<input type="radio" id="fw_newwindow_field1" name="fw_newwindow_field" value="1" <?php if($value[fw_newwindow] == "1") echo 'checked';?>/>No
		<div class="clear"></div>
	</div>
<?php 
}

add_action( 'save_post', 'page_custom_save_postdata' );
function page_custom_save_postdata( $post_id ) {
	// First we need to check if the current user is authorised to do this action.
	if ( 'fw_headlines' == $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_id ) )
			return;
	} else {
		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;
	}

	// Secondly we need to check if the user intended to change this value.
	if ( ! isset( $_POST['fw_headlines_noncename'] ) || ! wp_verify_nonce( $_POST['fw_headlines_noncename'], plugin_basename( __FILE__ ) ) )
		return;

	// Thirdly we can save the value to the database

	//if saving in a custom table, get post_ID
	$post_ID = $_POST['post_ID'];

	
	//source
	$mydata[fw_source] = sanitize_text_field( $_POST['fw_source_field'] );
	add_post_meta($post_ID, '_fw_source', $mydata[fw_source], true) or
	update_post_meta($post_ID, '_fw_source', $mydata[fw_source]);
	
	//url
	$mydata[fw_url] = sanitize_text_field( $_POST['fw_url_field'] );
	add_post_meta($post_ID, '_fw_url', $mydata[fw_url], true) or
	update_post_meta($post_ID, '_fw_url', $mydata[fw_url]);
	
	//media
	$mydata[fw_media] = sanitize_text_field( $_POST['fw_media_field'] );
	add_post_meta($post_ID, '_fw_media', $mydata[fw_media], true) or
	update_post_meta($post_ID, '_fw_media', $mydata[fw_media]);
	
	//mime
	$mydata[fw_mime] = sanitize_text_field( $_POST['fw_mime_field'] );
	add_post_meta($post_ID, '_fw_mime', $mydata[fw_mime], true) or
	update_post_meta($post_ID, '_fw_mime', $mydata[fw_mime]);
	
	//media title
	$mydata[fw_mtitle] = sanitize_text_field( $_POST['fw_mtitle_field'] );
	add_post_meta($post_ID, '_fw_mtitle', $mydata[fw_mtitle], true) or
	update_post_meta($post_ID, '_fw_mtitle', $mydata[fw_mtitle]);
	
	//display
	$mydata[fw_display] = sanitize_text_field( $_POST['fw_display_field'] );
	add_post_meta($post_ID, '_fw_display', $mydata[fw_display], true) or
	update_post_meta($post_ID, '_fw_display', $mydata[fw_display]);
	
	//new window
	$mydata[fw_newwindow] = sanitize_text_field( $_POST['fw_newwindow_field'] );
	add_post_meta($post_ID, '_fw_newwindow', $mydata[fw_newwindow], true) or
	update_post_meta($post_ID, '_fw_newwindow', $mydata[fw_newwindow]);
}

//*
//* fw_headlines columns
function modify_fw_headlines_columns($posts_columns) {
	$posts_columns = array(
			"cb" => '<input type="checkbox" />',
			"title" => __(Title),
			"date" => __(Date),
			"display" => __(Display),
			"source" => __(Source)
	);
	return $posts_columns;
}
add_filter('manage_fw_headlines_posts_columns', 'modify_fw_headlines_columns');
function fw_headlines_custom_columns($posts_column) {
	global $post;
	switch ( $posts_column ) {
		case 'display' :
			if(get_post_meta($post->ID, '_fw_display', true) == "")
				echo 'Yes';
			else
				echo 'No';
			break;
		case 'source' :
			echo get_post_meta($post->ID, '_fw_source', true);
			break;
	}
}
add_action( 'manage_fw_headlines_posts_custom_column' , 'fw_headlines_custom_columns');
