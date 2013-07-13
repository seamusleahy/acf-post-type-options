<?php
/*
Plugin Name: Advanced Custom Fields: Post Type Options
Plugin URI: 
Description: Adds per post type options to ACF
Version: 1.0.0
Author: Seamus P. H. Leahy
Author URI: http://www.seamusleahy.com/
License: MIT
Copyright: Seamus Leahy
*/

class acf_post_types_options_plugin {
	var $settings;
	var $current_post_type = '';
	const KEY_SEPARATOR = '___';
	
	/**
	 *  Constructor
	 */
	function __construct() {
		// vars
		$this->settings = array(
			'title' => __('Post Type Options','acf'), // title / menu name ('Post Type Options')
			'capability' => 'edit_posts', // capability to view options page
		);
				
		// actions
		// Add our page to the menu
		add_action('admin_menu', array($this,'admin_menu'), 11, 0);
		
		
		// filters
		// Adds our location options for selecting where a field group appears
		add_filter('acf/location/rule_types', array($this,'acf_location_rules_types'));
		add_filter('acf/location/rule_values/post_type_options', array($this,'acf_location_rules_values_post_type_options'));
		add_filter('acf/location/rule_match/post_type_options', array($this, 'rule_match'), 10, 3);
	}
	
	
	
	/**
	 * The filter for 'acf/location/rule_types'
	 *
	 * Adds 'Post Types Options' to the rule type select (the left select in the UI) in the
	 * location settings of a field group
	 *
	 * @param array $choices
	 */
	function acf_location_rules_types( $choices ) {
	    $choices[ __("Other",'acf') ]['post_type_options'] = __("Post Types Options",'acf');
	 
	    return $choices;
	}
	
	
	/**
	 * The filter for 'acf/location/rule_values/post_type_options'
	 *
	 * Adds values to compare in the value select (the right select in the UI) in the
	 * location settings of a field group
	 *
	 * @param array $choices
	 */
	function acf_location_rules_values_post_type_options( $choices ) {			
		$choices = array(
			'all' => __( 'All Post Types', 'acf' )
		);
		
	    return $choices;
	}


	/**
	 * The filter for 'acf/location/rule_match/post_type_options'
	 *
	 * Determines if the rule for a field group is match for the post type options
	 *
	 * @param boolean $match - the match value
	 * @param assoc array $rule - the rule values
	 * @param $options
	 */
	function rule_match( $match, $rule, $options ) {

		// Only handle if it is our's
		if ( $rule['param'] == 'post_type_options' ) {
			if ( !is_admin() ) {
				return false;
			}

			$screen = get_current_screen();
			if ( $screen->id != 'settings_page_acf-post-type-options' ) {
				return false;
			}

			$match = $rule['operator'] == '==' && $rule['value'] == 'all';
		}

		return $match;
	}

	
	/**
	 * The action for 'admin_menu'
	 *
	 * Adds our submenu page to the Settings menu
	 */
	function admin_menu() {
		// vars
		$menu_slug = 'acf-post-type-options';
		$title = $this->settings['title'];
		$menu_label = $this->settings['title'];		
		
		$page = add_options_page($title, $menu_label, $this->settings['capability'], $menu_slug, array($this, 'html'));	
		
		// loading action for this page
		add_action('load-' . $page, array($this,'admin_load'));
	}
	
	
	/**
	 * The action for "load-$pagehook"
	 *
	 * This adds hooks for enqueuing scripts and more for our page.
	 */
	function admin_load() {
		add_action('admin_enqueue_scripts', array($this,'admin_enqueue_scripts'));
		add_action('admin_head', array($this,'admin_head'));
		add_action('admin_footer', array($this,'admin_footer'));
	}
	
	
	/**
	 * The action for 'admin_enqueue_scripts'
	 *
	 * This adds the JS needed for our page
	 */
	function admin_enqueue_scripts()
	{
		// actions
		do_action('acf/input/admin_enqueue_scripts');
		wp_enqueue_style( 'acf-post-type-options', plugins_url( '/acf-post-type-options.css', __FILE__ ) );
	}
	
	
	/**
	 * The action for 'admin_head'
	 *
	 * Saves the results if need be, and setups the metaboxes to display on the page.
	 */	
	function admin_head() {	
	
		// SAVE
		// verify nonce and that the fields were saved
		if( isset($_POST['acf_nonce']) && wp_verify_nonce($_POST['acf_nonce'], 'input') && isset($_POST['fields']) && !empty($_POST['fields']) ) {

			// Because the same field appears more than once, the field name are the "$field_key{KEY_SEPARATOR}$post_type_name"
			foreach ( $_POST['fields'] as $k => $v ) {
				// get the post type name and field key
				$key_parts = explode( self::KEY_SEPARATOR, $k );
				if ( count($key_parts) == 2 ) {
					$field_key = $key_parts[0];
					$post_type = $key_parts[1];

					// get field
					$f = apply_filters('acf/load_field', false, $field_key );

					// update field
					do_action('acf/update_value', $v, 'post_type_option_'.$post_type, $f );
				}
			}
			
			$this->data['admin_message'] = __("Post Type Options Updated",'acf');
		}
		
		// SETUP
		// get field groups
		$filter = array();
		$metabox_ids = array();
		$metabox_ids = apply_filters( 'acf/location/match_field_groups', $metabox_ids, $filter );

		// Check if fields exists
		if( empty($metabox_ids) ) {
			$this->data['no_fields'] = true;
			return false;	
		}
		
		// Style
		echo '<style type="text/css">#side-sortables.empty-container { border: 0 none; }</style>';
		
		// add user js + css
		do_action('acf/input/admin_head');
		
		// get field groups
		$acfs = apply_filters('acf/get_field_groups', array());
		
		if( $acfs ) {
			$post_types = $this->get_post_types();
			foreach ( $post_types as $post_type ) {
				foreach( $acfs as $acf ) {
					// load options for the field group
					$acf['options'] = apply_filters('acf/field_group/get_options', array(), $acf['id']);
					
					// vars
					$show = in_array( $acf['id'], $metabox_ids ) ? 1 : 0;
					
					if( $show ) {
						// add meta box
						// notice that the 'post_id' is "post_type_option_{$post_type->name}"
						add_meta_box(
							'acf_' . $acf['id'] . '_' . $post_type->name, 
							$acf['title'], 
							array($this, 'meta_box_input'), 
							'acf_post_type_options',
							$acf['options']['position'] . '-' . $post_type->name , 
							'high',
							array( 'field_group' => $acf, 'show' => $show, 'post_id' => 'post_type_option_'.$post_type->name, 'post_type' => $post_type )
						);
					}
				}
			}
		}	
	}
	
	
	/**
	 * Render the meta box for a field group
	 *
	 * @param WP_Post $post - N/A
	 * @param array $args - custom data we set when registering the meta box
	 */
	function meta_box_input( $post, $args ) {
		// vars
		$options = $args['args'];
		
		echo '<div class="options" data-layout="' . $options['field_group']['options']['layout'] . '" data-show="' . $options['show'] . '" style="display:none"></div>';
		
		// Get the fields for this field group
		$fields = apply_filters('acf/field_group/get_fields', array(), $options['field_group']['id']);

		// Alter the keys to be "$post_type{self::KEY_SEPARATOR}$field_key" because multiple of the same field will appear 
		foreach ( $fields as &$field ) {
			$field['key'] = $field['key'] . self::KEY_SEPARATOR . $options['post_type']->name;
		}

		// render the field
		do_action('acf/create_fields', $fields, $options['post_id']);
		
	}
	
	
	/**
	 * The action for 'admin_footer'
	 */
	function admin_footer() {
		// add togle open / close postbox
		?>
		<script type="text/javascript">
		(function($){
			
			$('.postbox .handlediv').live('click', function(){
				
				var postbox = $(this).closest('.postbox');
				
				if( postbox.hasClass('closed') )
				{
					postbox.removeClass('closed');
				}
				else
				{
					postbox.addClass('closed');
				}
				
			});
			
		})(jQuery);
		</script>
		<?php
	}
	
	/**
	 * Get the post types we want to display
	 */
	function get_post_types() {
		$post_types = get_post_types( array( 'public' => true, ), 'objects' );

		return wp_list_filter( $post_types, array( 'name' => 'attachment' ), 'NOT' );
	}
	
	/**
	 * Render the contents of the page
	 */
	function html() {
		?>
		<div class="wrap no_move">
		
			<div class="icon32" id="icon-options-general"><br></div>
			<h2><?php echo get_admin_page_title(); ?></h2>
			
			<?php if(isset($this->data['admin_message'])): ?>
			<div id="message" class="updated"><p><?php echo $this->data['admin_message']; ?></p></div>
			<?php endif; ?>
			
			<?php if(isset($this->data['no_fields'])): ?>
			<div id="message" class="updated"><p><?php _e("No Custom Field Group found for the options page",'acf'); ?>. <a href="<?php echo admin_url(); ?>post-new.php?post_type=acf"><?php _e("Create a Custom Field Group",'acf'); ?></a></p></div>
			<?php else: ?>
			
			<form id="post" method="post" name="post">
			<div class="metabox-holder has-right-sidebar" id="poststuff">
				
				<!-- Sidebar -->
				<div class="inner-sidebar" id="side-info-column">
					
					<!-- Update -->
					<div class="postbox">
						<h3 class="hndle"><span><?php _e("Publish",'acf'); ?></span></h3>
						<div class="inside">
							<input type="hidden" name="HTTP_REFERER" value="<?php echo $_SERVER['HTTP_REFERER'] ?>" />
							<input type="hidden" name="acf_nonce" value="<?php echo wp_create_nonce( 'input' ); ?>" />
							<input type="submit" class="acf-button" value="<?php _e("Save Post Type Options",'acf'); ?>" />
						</div>
					</div>
					
					<?php $meta_boxes = do_meta_boxes('acf_post_type_options', 'side', null); ?>
					
				</div>
					
				<!-- Main -->
				<div id="post-body">
				<div id="post-body-content">
					<?php
						$post_types = $this->get_post_types();
						foreach ( $post_types as $post_type ): ?>
						<section class="acf-post-type-options-group">
							<header>
								<h2><?php echo esc_html( $post_type->labels->name ); ?></h2>
								<nav>
									<?php if ( get_post_type_archive_link( $post_type->name ) ): ?>
									<a href="<?php echo get_post_type_archive_link( $post_type->name ); ?>"><?php _e( 'View archive', 'acf' ); ?></a> |
									<?php endif; ?>
									<a href="<?php echo get_admin_url( '', "edit.php?post_type={$post_type->name}" ); ?>"><?php echo $post_type->labels->all_items; ?></a>
								</nav>
							</header>
							<?php $meta_boxes = do_meta_boxes('acf_post_type_options', 'normal-'.$post_type->name, null); ?>
						</section>
						<?php endforeach; ?>
					<script type="text/javascript">
					(function($){
					
						$('#poststuff .postbox[id*="acf_"]').addClass('acf_postbox');

					})(jQuery);
					</script>
				</div>
				</div>
			
			</div>
			</form>
			
			<?php endif; ?>
		
		</div>
		
		<?php
				
	}
	
}

new acf_post_types_options_plugin();
