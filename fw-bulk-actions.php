<?php
function my_custom_bulk_actions($actions){
	unset( $actions['edit'] );
	return $actions;
}
add_filter('bulk_actions-edit-fw_headlines','my_custom_bulk_actions');

if (!class_exists('fw_custom_bulk')) {
 
	class fw_custom_bulk {
		
		public function __construct() {
			
			if(is_admin()) {
				// admin actions/filters
				add_action('admin_footer-edit.php', array(&$this, 'custom_bulk_admin_footer'));
				add_action('load-edit.php',         array(&$this, 'custom_bulk_action'));
				add_action('admin_notices',         array(&$this, 'custom_bulk_admin_notices'));
				
			}
		}
		
		/**
		 * Step 1: add the custom Bulk Action to the select menus
		 */
		function custom_bulk_admin_footer() {
			global $post_type;
			
			if($post_type == 'fw_headlines') {
				?>
					<script type="text/javascript">
						jQuery(document).ready(function() {
							jQuery('<option>').val('display').text('<?php _e('Display')?>').appendTo("select[name='action']");
							jQuery('<option>').val('display').text('<?php _e('Display')?>').appendTo("select[name='action2']");

							jQuery('<option>').val('hide').text('<?php _e('Hide')?>').appendTo("select[name='action']");
							jQuery('<option>').val('hide').text('<?php _e('Hide')?>').appendTo("select[name='action2']");
						});
					</script>
				<?php
	    	}
		}
		
		
		/**
		 * Step 2: handle the custom Bulk Action
		 * 
		 * Based on the post http://wordpress.stackexchange.com/questions/29822/custom-bulk-action
		 */
		function custom_bulk_action() {
			global $typenow;
			$post_type = $typenow;				
			
			if($post_type == 'fw_headlines') {
				
				// get the action
				$wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
				$action = $wp_list_table->current_action();
				
				$allowed_actions = array("display","hide");
				if(!in_array($action, $allowed_actions)) return;
				
				// security check
				check_admin_referer('bulk-posts');
				
				// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
				if(isset($_REQUEST['post'])) {
					$post_ids = array_map('intval', $_REQUEST['post']);
				}
				
				if(empty($post_ids)) return;
				
				// this is based on wp-admin/edit.php
				$sendback = remove_query_arg( array('displayed', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
				if ( ! $sendback )
					$sendback = admin_url( "edit.php?post_type=$post_type" );
				
				$pagenum = $wp_list_table->get_pagenum();
				$sendback = add_query_arg( 'paged', $pagenum, $sendback );
				
				switch($action) {
					case 'display':
						
						// if we set up user permissions/capabilities, the code might look like:
						//if ( !current_user_can($post_type_object->cap->display_post, $post_id) )
						//	wp_die( __('You are not allowed to display this post.') );
						
						$displayed = 0;
						foreach( $post_ids as $post_id ) {
							
							if ( !$this->perform_display($post_id) )
								wp_die( __('Error displaying post.') );
							
							update_post_meta($post_id,'_fw_display',"");
							$displayed++;
						
						}
						
						$sendback = add_query_arg( array('displayed' => $displayed, 'ids' => join(',', $post_ids) ), $sendback );
						
					break;
					
					case 'hide':
						
						$hidden = 0;
						foreach( $post_ids as $post_id ) {
								
							if ( !$this->perform_display($post_id) )
								wp_die( __('Error displaying post.') );
							
							update_post_meta($post_id,'_fw_display',"1");
							$hidden++;
							
						}
						
						$sendback = add_query_arg( array('hidden' => $hidden, 'ids' => join(',', $post_ids) ), $sendback );
						
					break;
					
					default: return;
				}
				
				$sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );
				
				wp_redirect($sendback);
				exit();
			}
		}
		
		
		/**
		 * Step 3: display an admin notice on the Posts page after displaying
		 */
		function custom_bulk_admin_notices() {
			global $post_type, $pagenow;
			
			if($pagenow == 'edit.php' && $post_type == 'fw_headlines' && isset($_REQUEST['displayed']) && (int) $_REQUEST['displayed']) {
				$message = sprintf( _n( 'Post will be displayed.', '%s posts will be displayed.', $_REQUEST['displayed'] ), number_format_i18n( $_REQUEST['displayed'] ) );
				echo "<div class=\"updated\"><p>{$message}</p></div>";
			}
			
			if($pagenow == 'edit.php' && $post_type == 'fw_headlines' && isset($_REQUEST['hidden']) && (int) $_REQUEST['hidden']) {
				$message = sprintf( _n( 'Post will be hidden.', '%s posts will be hidden.', $_REQUEST['hidden'] ), number_format_i18n( $_REQUEST['hidden'] ) );
				echo "<div class=\"updated\"><p>{$message}</p></div>";
			}
		}
		
		function perform_display($post_id) {
			// do whatever work needs to be done
			return true;
		}
	}
}