<?php
//-----------------------------------------------------------------------------
/*
Plugin Name: Sensitive Tag Cloud
Version: 0.4
Plugin URI: http://www.rene-ade.de/inhalte/wordpress-plugin-sensitivetagcloud.html
Description: This wordpress plugin provides a tagcloud that shows tags depending of the current context only. For example it is possible to let the tagcloud show only tags that really occur in the current category (and if desired subcategories). The widget can get configured to be only visible on pages that really show a category.
Author: Ren&eacute; Ade
Author URI: http://www.rene-ade.de
Min WP Version: 2.3
*/
//-----------------------------------------------------------------------------

// wordpress mu only: get term children (not included in wordpress mu 1.3)
if( !function_exists('get_term_children') ) {
  function get_term_children( $term, $taxonomy ) {
   if ( ! is_taxonomy($taxonomy) )
    return new WP_Error('invalid_taxonomy', __('Invalid Taxonomy'));
  
   $terms = _get_term_hierarchy($taxonomy);
  
   if ( ! isset($terms[$term]) )
    return array();
  
   $children = $terms[$term];
  
   foreach ( $terms[$term] as $child ) {
    if ( isset($terms[$child]) )
    	$children = array_merge($children, get_term_children($child, $taxonomy));
   }
  
   return $children;
  }
}

//-----------------------------------------------------------------------------

// word cloud
function stc_get_tags( $args = '' ) {
  $defaults = array(
    'orderby' => 'name', 'order' => 'ASC',
    'exclude' => '', 'include' => '',
    'category' => -1, 'categories' => -1
  );
  $args = wp_parse_args( $args, $defaults );
  extract($args); 
      
  // get categorys
  $categories_array = array();  
  if( $categories!=-1 ) {
    $categories_array[] = $categories;
    if( $categories!=0 ) {  
      foreach( $categories_array as $categories_element ) {
        $categories_element = abs( intval( $categories_element ) );
        if ( $categories_element!=0 ) {
          $categories_array = array_merge( $categories_array, get_term_children($categories_element,'category') );
        }
      }
    }
  }
  if( $category!=-1 ) {
    $category = abs( intval( $category ) );
    $categories_array = array_merge( $category_array, array($category) );
  } 
  
  // get tags
  $tags = array();
  $args_gettags = array_merge( $args, array('orderby' => 'count', 'order' => 'DESC') );
  global $stc_filter_query_onlyid_active;
  foreach( $categories_array as $categories_element ) {  
    if( $categories_element==0 ) {
      $tags = array_merge( $tags, get_tags($args_gettags) );
    }
    else {
      $stc_filter_query_onlyid_active = true; // get only the ids, dont load all post fields
      $posts_array = get_posts( array('category'=>$categories_element,'numberposts'=>-1) );
      $stc_filter_query_onlyid_active = false;
      foreach( $posts_array as $post ) {  
        $tags = array_merge( $tags, wp_get_post_tags($post->ID,$args_gettags) );
      }
    }
  }

  // return
  return $tags;
}

//-----------------------------------------------------------------------------

// widget
function stc_widget($args) {
  extract($args);
  
  // options
  $options = get_option('stc_widget');
  
  // environment
  $category             = get_query_var('cat');
  $category_haschildren = count(get_term_children($category,'category'))>0;  
  
  // show
  $show = $options['showalways'];
  if( !$show && !empty($category) ) {
    if( $category_haschildren )
      $show = $options['showinparentcats'];
    else
      $show = $options['showinchildcats'];
  }
  if( !$show )
    return;
      
  // title
  $title = empty($options['title']) ? __('Tags') : $options['title'];

  // tags
  if( $options['showchildcattags'] )
    $args['categories'] = $category;
  else
    $args['category']   = $category;
  $tags = stc_get_tags( $args );
  if( count($tags)<=0 )
    return; // dont show empty widget
  
  // cloud
  $cloud = wp_generate_tag_cloud( $tags, $args );
  if( is_wp_error($cloud) )
    return;
  $cloud = apply_filters( 'wp_tag_cloud', $cloud, $args );  
      
  // output
  echo $before_widget;
  echo $before_title . $title . $after_title;
  echo $cloud;
  echo $after_widget;
}

// widget configuration
function stc_widget_control() {
  $options = $newoptions = get_option('stc_widget');

  // get options
  if ( $_POST['stc-widget-submit'] ) {
    $newoptions['title']            = strip_tags(stripslashes($_POST['stc-widget-title']));
    $newoptions['showchildcattags'] = isset($_POST['stc-widget-showchildcattags']);        
    $newoptions['showalways']       = isset($_POST['stc-widget-showalways']);    
    $newoptions['showinparentcats'] = isset($_POST['stc-widget-showinparentcats']);   
    $newoptions['showinchildcats']  = isset($_POST['stc-widget-showinchildcats']);
    $newoptions['showinchildcats']  = isset($_POST['stc-widget-showinchildcats']);
    $newoptions['activateperformancehacks'] 
      = isset($_POST['stc-widget-activateperformancehacks']);
  }

  // update options if needed
  if ( $options != $newoptions ) {
    $options = $newoptions;
    update_option('stc_widget', $options);
  }
  
  // checkboxes
  $title            = attribute_escape( $options['title'] );
  $showchildcattags = $options['showchildcattags'] ? 'checked="checked"' : '';
  $showalways       = $options['showalways']       ? 'checked="checked"' : '';
  $showinparentcats = $options['showinparentcats'] ? 'checked="checked"' : '';
  $showinchildcats  = $options['showinchildcats']  ? 'checked="checked"' : '';
  $activateperformancehacks 
    = $options['activateperformancehacks']  ? 'checked="checked"' : '';

  // form
  echo '<p><label for="stc-widget-title">'._e('Title:');
  echo '<input type="text" style="width:300px" id="stc-widget-title" name="stc-widget-title" value="'.$title.'" /></label>';
  echo '</p>';
  echo '<p><label for="stc-widget-options">'._e('Display:');
  echo '<input type="checkbox" class="checkbox" id="stc-widget-showchildcattags" name="stc-widget-showchildcattags" '.$showchildcattags.' />'._e( 'Show also tags of subcategories' ).'<br />';  
  echo '<input type="checkbox" class="checkbox" id="stc-widget-showalways" name="stc-widget-showalways" '.$showalways.' />'._e( 'Show always' ).'<br />';  
  echo '<input type="checkbox" class="checkbox" id="stc-widget-showinparentcats" name="stc-widget-showinparentcats" '.$showinparentcats.' />'._e( 'Show in categories with subcategories' ).'<br />';
  echo '<input type="checkbox" class="checkbox" id="stc-widget-showinchildcats" name="stc-widget-showinchildcats" '.$showinchildcats.' />'._e( 'Show in categories without subcategories' ).'<br />';
  echo '</p>';
  echo '<p><label for="stc-widget-options">'._e('Troubleshooting:');
  echo '<input type="checkbox" class="checkbox" id="stc-widget-activateperformancehacks" name="stc-widget-activateperformancehacks" '.$activateperformancehacks.' />'._e( 'Activate Performance Hacks' ).'<br />';
  echo '</p>';  
  echo '<input type="hidden" name="stc-widget-submit" id="stc-widget-submit" value="1" />';
}

//-----------------------------------------------------------------------------

// filter
function stc_filter_query_onlyid( $query )
{
  global $stc_filter_query_onlyid_active;
  if( $stc_filter_query_onlyid_active!==true )
    return $query;
    
  $options = get_option('stc_widget');
  if( !$options['activateperformancehacks'] )
    return $query;
      
  // we need only the ids of the posts, dont load all fields
  global $wpdb;
  return str_replace( "SELECT DISTINCT * FROM $wpdb->posts", 
                      "SELECT DISTINCT $wpdb->posts.ID FROM $wpdb->posts", 
                      $query );
}

//-----------------------------------------------------------------------------
    
// (de)activation
function stc_activate() {
  $options = array( 
    'title'            => __('Tags'), 
    'showchildcattags' => true,
    'showalways'       => false, 
    'showinparentcats' => true, 
    'showinchildcats'  => true,
    'activateperformancehacks' => false
  );
  add_option( 'stc_widget', $options );
}
function stc_deactivate() {
  delete_option('stc_widget'); 
}

// initialization
function stc_init() {  
  $class['classname'] = 'stc_widget';
  wp_register_sidebar_widget('sensitive_tag_cloud', __('Sensitive Tag Cloud'), 'stc_widget', $class);
  wp_register_widget_control('sensitive_tag_cloud', __('Sensitive Tag Cloud'), 'stc_widget_control', 'width=300&height=160');
}

//-----------------------------------------------------------------------------

// actions
add_action( 'activate_'.plugin_basename(__FILE__),   'stc_activate' );
add_action( 'deactivate_'.plugin_basename(__FILE__), 'stc_deactivate' );
add_action( 'init', 'stc_init');

// filters
add_filter( 'query', 'stc_filter_query_onlyid' ); // a filter for fields queried in post/get_posts() only would be better

//-----------------------------------------------------------------------------

?>