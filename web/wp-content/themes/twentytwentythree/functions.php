<?php

if(function_exists('acf_register_block')) { 
   add_action('acf/init', 'my_acf_init');
}

add_action( 'after_setup_theme', 'theme_supports' );
add_action( 'init', 'register_post_types' );
add_action( 'init', 'register_taxonomies' );
add_action( 'init', 'register_menus' );

/**
 * Add excerpt to search API endpoint.
 */
add_action( 'rest_api_init', function () {
  // Registers a REST field for the /wp/v2/search endpoint.
  register_rest_field( 'search-result', 'excerpt', array(
      'get_callback' => function ( $post_arr ) {
          return get_the_excerpt( $post_arr['id'] );
      },
  ) );
} );

function register_menus() {
  register_nav_menus(
    array(
      'main_menu' => __( 'Main Menu' ),
      'utility_menu' => __( 'Utility Menu' ),
      'social_menu' => __( 'Social Menu' )
    )
  );
}

function get_site_menus() {
  $site = top_nav_menu(array( 'id' => 10 ));
  $socials = top_nav_menu(array( 'id' => 11 ));
  $utility = top_nav_menu(array( 'id' => 12 ));

  $result = array(
    'site' => $site,
    'social' => $socials,
    'utility' => $utility,
  );

  return $result; 
}

// Return formatted top-nav menu
function top_nav_menu($data) {
  $menu_ids = $data['id'];

  $menu = wp_get_nav_menu_items( $menu_ids );
  $child_items = [];
  $result = [];

  // pull all child menu items into separate object
  foreach ($menu as $key => $item) {
    if ($item->menu_item_parent) {
      array_push($child_items, $item);
      unset($menu[$key]);
    }
  }

  // push child items into their parent item in the original object
  foreach ($menu as $item) {
    foreach ($child_items as $key => $child) {
      if ($child->menu_item_parent == $item->post_name) {
        if (!$item->child_items) {
            $item->child_items = [];
        }

        array_push($item->child_items, $child);
        unset($child_items[$key]);
      }
    }
  }

  foreach($menu as $item) {
      $my_item = [
          'id' => $item->ID,
          'title' => $item->title,
          'url' => $item->url,
          'children' => $item->child_items
      ];
      $result[] = $my_item;
  }
  return $result;
}
// add endpoint
add_action( 'rest_api_init', function() {

  register_rest_route( 'wp/v2', 'menus', array(
    'methods' => 'GET',
    'callback' => 'get_site_menus',
  ));

  register_rest_route( 'wp/v2', 'menus/(?P<id>\d+)', array(
    'methods' => 'GET',
    'callback' => 'top_nav_menu',
    'args' => array(
      'id' => array(
        'validate_callback' => function($param, $request, $key) {
          return is_numeric( $param );
        }
      ),
      'include' => array(
        'validate_callback' => function($param, $request, $key) {
          return is_numeric( $param );
        }
      ),
    ),
  ) );

  // Breadcrumbs
  register_rest_route( 'wp/v2', 'breadcrumbs/(?P<id>\d+)', array(
    'methods' => 'GET',
    'callback' => 'get_breadcrumbs',
    'args' => array(
      'id' => array(
        'validate_callback' => function($param, $request, $key) {
          return is_numeric( $param );
        }
      ),
    ),
  ) );

  // Exhibitions and events
  register_rest_route( 'wp/v2', 'eoe', array(
    'methods' => 'GET',
    'callback' => 'get_eoe_by_date',
  ));
  register_rest_route( 'wp/v2', 'eoe/(?P<id>\d+)', array(
    'methods' => 'GET',
    'callback' => 'get_eoe_post_by_date',
  ));
});

function get_eoe_post_by_date( WP_REST_Request $request ) {

  $id = $request['id'];
  $type = $request->get_param('type');

  $ee = get_posts(array(
    'post_type' => $type,
    'posts_per_page' => 1,
    'include' => array($id)
  ));

  for($i = 0; $i < count($ee); $i++) {
    $ee[$i]->acf = get_fields($ee[$i]->ID);
    $ee[$i]->link = get_permalink($ee[$i]->ID);

    $featured_image_id = get_post_thumbnail_id($ee[$i]->ID);
    if ($featured_image_id) {
        $ee[$i]->featured_media = array(
            $featured_image_id,
        );
    } else {
        $ee[$i]->featured_media = null;
    }
  }
  return $ee;
}

function get_eoe_by_date( WP_REST_Request $request ) {

  $type = $request->get_param('type');
  $key = $request->get_param('key');
  $order = $request->get_param('order');
  $chronology = $request->get_param('chronology');
  $page = $request->get_param('page');
  $per_page = $request->get_param('per_page');

  if ($chronology == 'past') {
    $tax_q = array(
      'taxonomy' => 'chronologies',
      'field' => 'slug',
      'terms' => 'past',
    );
  } elseif ($chronology == 'current' || $chronology == 'future') {
    $tax_q = array(
      'taxonomy' => 'chronologies',
      'field' => 'slug',
      'terms' => $chronology,
    );
  } else {
    $tax_q = array(
      'taxonomy' => 'chronologies',
      'field' => 'slug',
      'terms' => 'past',
      'operator' => 'NOT IN'
    );
  }

  $ee = get_posts(array(
    'post_type' => $type,
    'posts_per_page' => $per_page,
    'offset' => ($per_page * $page) - $per_page,
    'page' => $page,
    'meta_key' => $key,
    'meta_type' => 'DATE',
    'orderby' => 'meta_value',
    'order' => $order,
    'tax_query' => array(
      $tax_q
    )
  ));

  for($i = 0; $i < count($ee); $i++) {
    $ee[$i]->acf = get_fields($ee[$i]->ID);
    $ee[$i]->link = get_permalink($ee[$i]->ID);

    $featured_image_id = get_post_thumbnail_id($ee[$i]->ID);
    if ($featured_image_id) {
        $ee[$i]->featured_media = array(
            $featured_image_id,
        );
    } else {
        $ee[$i]->featured_media = null;
    }
  }

  return $ee;
}

function register_post_types() {
  register_post_type(
    'events',
    array(
      'labels'            => array(
        'name'               => __( 'Events' ),
        'singular_name'      => __( 'Event' ),
        'add_new_item'       => __( 'Add Event' ),
        'edit_item'          => __( 'Edit Event' ),
        'new_item'           => __( 'New Event' ),
        'view_item'          => __( 'View Event' ),
        'search_items'       => __( 'Search Events' ),
        'not_found'          => __( 'Event not found.' ),
        'not_found_in_trash' => __( 'No Event found in trash.' ),
      ),
      'public'            => true,
      'show_in_rest'      => true,
      'menu_icon'         => 'dashicons-calendar-alt',
      'show_in_nav_menus' => true,
      'supports'          => array( 'title', 'editor', 'revisions', 'excerpt', 'thumbnail' ),
    )
  );

  register_post_type(
    'exhibitions',
    array(
      'labels'            => array(
        'name'               => __( 'Exhibitions' ),
        'singular_name'      => __( 'Exhibition' ),
        'add_new_item'       => __( 'Add Exhibition' ),
        'edit_item'          => __( 'Edit Exhibition' ),
        'new_item'           => __( 'New Exhibition' ),
        'view_item'          => __( 'View Exhibition' ),
        'search_items'       => __( 'Search Exhibitions' ),
        'not_found'          => __( 'Exhibition not found.' ),
        'not_found_in_trash' => __( 'No Exhibition found in trash.' ),
      ),
      'public'            => true,
      'show_in_rest'      => true,
      'menu_icon'         => 'dashicons-art',
      'show_in_nav_menus' => true,
      'supports'          => array( 'title', 'editor', 'revisions', 'excerpt', 'thumbnail' ),
    )
  );

  register_post_type(
    'collections',
    array(
      'labels'            => array(
        'name'               => __( 'Collections' ),
        'singular_name'      => __( 'Collection' ),
        'add_new_item'       => __( 'Add Collection' ),
        'edit_item'          => __( 'Edit Collection' ),
        'new_item'           => __( 'New Collection' ),
        'view_item'          => __( 'View Collection' ),
        'search_items'       => __( 'Search Collections' ),
        'not_found'          => __( 'Collection not found.' ),
        'not_found_in_trash' => __( 'No Collection found in trash.' ),
      ),
      'public'            => true,
      'show_in_rest'      => true,
      'menu_icon'         => 'dashicons-images-alt',
      'show_in_nav_menus' => true,
      'supports'          => array( 'title', 'editor', 'revisions', 'excerpt', 'thumbnail' ),
    )
  );
}

function register_taxonomies() {
  register_taxonomy(
    'chronologies',
    array('events', 'exhibitions'),
    array(
      'hierarchical'      => true,
      'show_ui'           => true,
      'show_in_rest'      => true,
      'show_admin_column' => true,
      'query_var'         => true,
      'public'            => true,
      'show_tagcloud'     => false,
      'capabilities'      => array(
        'manage_terms' => 'manage_options',
        'edit_terms'   => 'manage_options',
        'delete_terms' => 'manage_options',
        'assign_terms' => 'manage_options',
      ),
      'labels'            => array(
        'name'          => __( 'Chronologies' ),
        'singular_name' => __( 'Chronology' ),
        'add_new_item'  => __( 'Add New Chronology' ),
        'menu_name'     => __( 'Chronology' ),
      ),
    )
  );

  register_taxonomy(
    'variant',
    array('exhibitions'),
    array(
      'hierarchical'      => true,
      'show_ui'           => true,
      'show_in_rest'      => true,
      'show_admin_column' => true,
      'query_var'         => true,
      'public'            => true,
      'show_tagcloud'     => false,
      'capabilities'      => array(
        'manage_terms' => 'manage_options',
        'edit_terms'   => 'manage_options',
        'delete_terms' => 'manage_options',
        'assign_terms' => 'manage_options',
      ),
      'labels'            => array(
        'name'          => __( 'Variants' ),
        'singular_name' => __( 'Variant' ),
        'add_new_item'  => __( 'Add New Variant' ),
        'menu_name'     => __( 'Variants' ),
      ),
    )
  );
}

function my_acf_init() {

  // check function exists
  if ( function_exists( 'acf_register_block' ) ) {

    // register intro context
    acf_register_block(
      array(
        'name'				    => 'intro-context',
        'title'				    => __('Intro Context'),
        'description'	    => __('Introductory text block with optional CTA button'),
        'render_callback'	=> 'my_acf_block_render_callback',
        'category'			  => 'layout',
        'icon'				    => 'block-default',
        'keywords'			  => array( 'heading', 'subheading', 'context', 'arrow', 'button', 'layout' ),
      )
    );

    // register article grid
    acf_register_block(
      array(
        'name'				    => 'article-grid',
        'title'				    => __('Article Grid'),
        'description'	    => __('Grid of semantic articles and collection of associated items with heading, subheading, paragraph, and optional image and CTA.'),
        'render_callback'	=> 'my_acf_block_render_callback',
        'category'			  => 'layout',
        'icon'				    => 'block-default',
        'keywords'			  => array( 'article', 'grid', 'button', 'layout' ),
      )
    );

    // register media context
    acf_register_block(
      array(
        'name'				    => 'media-context',
        'title'				    => __('Media Context'),
        'description'	    => __('Image with supporting context featuring an optional carousel for numerous items.'),
        'render_callback'	=> 'my_acf_block_render_callback',
        'category'			  => 'layout',
        'icon'				    => 'block-default',
        'keywords'			  => array( 'media', 'context', 'carousel', 'layout' ),
      )
    );

    // register toggle context
    acf_register_block(
      array(
        'name'				    => 'toggle-context',
        'title'				    => __('Toggle Context'),
        'description'	    => __('List with optional toggle featue for a supporting image.'),
        'render_callback'	=> 'my_acf_block_render_callback',
        'category'			  => 'layout',
        'icon'				    => 'block-default',
        'keywords'			  => array( 'toggle', 'context', 'carousel', 'layout' ),
      )
    );

    // register marquee
    acf_register_block(
      array(
        'name'				    => 'marquee',
        'title'				    => __('Marquee'),
        'description'	    => __('Display style component featuring horizontally scrolling text.'),
        'render_callback'	=> 'my_acf_block_render_callback',
        'category'			  => 'layout',
        'icon'				    => 'block-default',
        'keywords'			  => array( 'marquee', 'display', 'layout' ),
      )
    );

    // register accordion section
    acf_register_block(
      array(
        'name'				    => 'accordion-section',
        'title'				    => __('Accordion Section'),
        'description'	    => __('Full-width section component housing one single-select accordion.'),
        'render_callback'	=> 'my_acf_block_render_callback',
        'category'			  => 'layout',
        'icon'				    => 'block-default',
        'keywords'			  => array( 'accordion', 'section', 'toggle', 'select' ),
      )
    );

    // register ordered list section
    acf_register_block(
      array(
        'name'				    => 'ordered-list-section',
        'title'				    => __('Ordered List Section'),
        'description'	    => __('Full-width section component housing one ordered list.'),
        'render_callback'	=> 'my_acf_block_render_callback',
        'category'			  => 'layout',
        'icon'				    => 'block-default',
        'keywords'			  => array( 'ordered', 'list', 'section' ),
      )
    );

    // register the video block
    acf_register_block(
      array(
        'name'				=> 'video',
        'title'				=> __('Video'),
        'description'		=> __('Inset video component for standalone instances.'),
        'render_callback'	=> 'my_acf_block_render_callback',
        'category'			=> 'layout',
        'icon'				=> 'block-default',
        'keywords'        => array( 'media', 'video' ),
      )
    );

    // register embed
    acf_register_block(
      array(
        'name'				    => 'embed',
        'title'				    => __('Embed'),
        'description'	    => __('Custom embed codes'),
        'render_callback'	=> 'my_acf_block_render_callback',
        'category'			  => 'layout',
        'icon'				    => 'block-default',
        'keywords'			  => array( 'embed', 'embeds', 'embed code' ),
      )
    );
  }
}

function theme_supports() {
  add_image_size( 'desktop', 1200, 1200, false );
	add_image_size( 'mobile', 600, 600, false );
  add_post_type_support( 'page', 'excerpt' );

  if ( function_exists( 'acf_add_options_page' ) ) {
    acf_add_options_page(
      array(
        'page_title' => 'Global Settings',
        'menu_title' => 'Global Settings',
        'menu_slug'  => 'global-settings',
        'capability' => 'edit_posts',
        'redirect'   => false,
      )
    );
  }
}

function set_current_event_date( $post_id ) {
  if ( get_post_meta( $post_id, $key = 'date', $single = true ) ) {

    // Get the start date of the event in unix grenwich mean time
    $acf_current_date = get_post_meta( $post_id, $key = 'date', $single = true );

  } else {

    // No start or end date. Lets delete any CRON jobs related to this post and end the function.
    wp_clear_scheduled_hook( 'make_current_event', array( $post_id ) );
    return;

  }

  // Convert our date to the correct format
  $unix_acf_end_date = strtotime( $acf_current_date );

  // Temporarily remove from 'Current Event' category
  wp_remove_object_terms( $post_id, array('past', 'current'), 'chronologies' );

  // If a CRON job exists with this post_id them remove it
  wp_clear_scheduled_hook( 'make_current_event', array( $post_id ) );
  // Add the new CRON job to run the day after the event with the post_id as an argument
  wp_schedule_single_event( $unix_acf_end_date , 'make_current_event', array( $post_id ) );
}

// Hook into the save_post_{post-type} function to create/update the cron job everytime an event is saved.
add_action( 'acf/save_post', 'set_current_event_date', 20 );

// Create a function that adds the post to the past-events category
function set_current_event_category( $post_id ){

  // Set the post category to 'Current Event'
  wp_set_post_terms( $post_id, array( 9 ), 'chronologies' , true );

}

// Hook into the make_past_event CRON job so that the set_past_event_category function runs when the CRON job is fired.
add_action( 'make_current_event', 'set_current_event_category' );

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// Create a cron job to run the day after an event happens or ends
function set_expiry_date( $post_id ) {

  // See if an event_end_date or event_date has been entered and if not then end the function
  if( get_post_meta( $post_id, $key = 'end_date', $single = true ) ) {

    // Get the end date of the event in unix grenwich mean time
    $acf_end_date = get_post_meta( $post_id, $key = 'end_date', $single = true );

  } elseif ( get_post_meta( $post_id, $key = 'date', $single = true ) ) {

    // Get the start date of the event in unix grenwich mean time
    $acf_end_date = get_post_meta( $post_id, $key = 'date', $single = true );

  } else {

    // No start or end date. Lets delete any CRON jobs related to this post and end the function.
    wp_clear_scheduled_hook( 'make_past_event', array( $post_id ) );
    return;

  }

  // Convert our date to the correct format
  $unix_acf_end_date = strtotime( $acf_end_date.' +1 day' );

  // Get the number of seconds in a day
  $delay = 24 * 60 * 60; //24 hours * 60 minutes * 60 seconds

  // Add 1 day to the end date to get the day after the event
  $day_after_event = $unix_acf_end_date;

  // Temporarily remove from 'Past Event' category. Permanently remove currennt.
  wp_remove_object_terms( $post_id, array('past', 'current'), 'chronologies' );

  // If a CRON job exists with this post_id them remove it
  wp_clear_scheduled_hook( 'make_past_event', array( $post_id ) );
  // Add the new CRON job to run the day after the event with the post_id as an argument
  wp_schedule_single_event( $day_after_event , 'make_past_event', array( $post_id ) );

}

// Hook into the save_post_{post-type} function to create/update the cron job everytime an event is saved.
add_action( 'acf/save_post', 'set_expiry_date', 20 );

// Create a function that adds the post to the past-events category
function set_past_event_category( $post_id ){

  // Set the post category to 'Past Event'
  wp_set_post_terms( $post_id, array( 8 ), 'chronologies' , false );

}

// Hook into the make_past_event CRON job so that the set_past_event_category function runs when the CRON job is fired.
add_action( 'make_past_event', 'set_past_event_category' );

function get_breadcrumbs( $data ) {
  
  $crumbs = get_post_ancestors( $data['id'] );

  if ( $crumbs ) {
    $breadcrumbs_menu = array();

    foreach ( $crumbs as $ancestor ) {
      array_push(
        $breadcrumbs_menu,
        array(
          'id'    => $ancestor,
          'title' => get_the_title( $ancestor ),
          'url'   => get_permalink( $ancestor ),
        )
      );
    }
  }

  if ( isset( $breadcrumbs_menu ) ) {
    $breadcrumbs = array_reverse( $breadcrumbs_menu );
  }

  return $breadcrumbs;
}

function my_custom_rest_cors() {
  remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
  add_filter( 'rest_pre_serve_request', function( $value ) {
    header( 'Access-Control-Allow-Origin: *' );
    header( 'Access-Control-Allow-Methods: GET' );
    header( 'Access-Control-Allow-Credentials: true' );
    header( 'Access-Control-Expose-Headers: Link', false );

    return $value;
  } );
}
add_action( 'rest_api_init', 'my_custom_rest_cors', 15 );

function my_admin_confirm_delete_script() {
  // Get the path to your script
  $script_url = get_stylesheet_directory_uri() . '/js/admin-tools.js'; 

  // Load the script
  wp_enqueue_script(
      'admin-confirm-delete', 
      $script_url, 
      array('jquery'), // Make sure jQuery is loaded first
      '1.0', 
      true // Load in the footer
  );
}
// Hook into the admin to load the script
add_action('admin_enqueue_scripts', 'my_admin_confirm_delete_script');