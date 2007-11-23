<?php
//-----------------------------------------------------------------------------
/*
Plugin Name: Sensitive Tag Cloud
Version: 0.6
Plugin URI: http://www.rene-ade.de/inhalte/wordpress-plugin-sensitivetagcloud.html
Description: This wordpress plugin provides a configurable tagcloud that shows tags depending of the current context only. For example it is possible to let the tagcloud show only tags that really occur in the current category (and if desired subcategories). The widget can get configured to be only visible on pages that really show a category. The style and size of the tagcloud can be configured.
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

// sort tags
function stc_tagsort( $a, $b ) {
  if( $a->count > $b->count )
    return 1;
  if( $a->count < $b->count )
    return -1;        
  return 0;    
}

// get tags
function stc_get_tags( $args = '' ) {
  $defaults = array(
    'orderby' => 'name', 'order' => 'ASC',
    'exclude' => '', 'include' => '',
    'category' => -1, 'categories' => -1,
    'number' => 45
  );
  $args = wp_parse_args( $args, $defaults );
  extract($args); 
          
  // options
  $options = get_option('stc_widget');
  
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
  global $stc_filter_query_onlyid_active;
  foreach( $categories_array as $categories_element ) {  
    if( $categories_element==0 ) {
      $tags = array_merge( $tags, get_tags($args_gettags) );
    }
    else {
      $stc_filter_query_onlyid_active = true && $options['activateperformancehacks']; // get only the ids, dont load all post fields
      $posts_array = get_posts( array('category'=>$categories_element,'numberposts'=>-1) );
      $stc_filter_query_onlyid_active = false;
      foreach( $posts_array as $post ) {  
        $posttags = wp_get_post_tags( $post->ID, $args );
        foreach( $posttags as $posttag )
          $tags[$posttag->name] = $posttag;
      }
    }
  }

  // order and cut
  if( isset($options['args']['number']) && count($tags)>$options['args']['number'] ) {
    usort( $tags, 'stc_tagsort' );
    $tags = array_reverse( $tags );
    $tags = array_chunk( $tags, $options['args']['number'] );    
    $tags = $tags[0];
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
  $category             = get_query_var('cat'); // current category
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
  global $stc_filter_tag_link_active;
  $cloud_args = array_merge( $args, $options['args'] ); 
  $stc_filter_tag_link_active = true && $options['sensitivetaglinks'];
  $cloud = wp_generate_tag_cloud( $tags, $cloud_args );
  $stc_filter_tag_link_active = false;
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

  // define args
  $argkeys = array( 'smallest'=>0, // number
                    'largest'=>0, // number
                    'unit'=>array('pt'), // options
                    'number'=>0, // number
                    'format'=>array('flat','list'), // options
                    'orderby'=>array('name','count'), // options
                    'order'=>array('ASC','DESC') // options
  );
    
  // get options
  if ( $_POST['stc-widget-submit'] ) {
    $newoptions['title']             = strip_tags(stripslashes($_POST['stc-widget-title']));
    
    // checkboxes
    $newoptions['showchildcattags']  = isset($_POST['stc-widget-showchildcattags']);        
    $newoptions['showalways']        = isset($_POST['stc-widget-showalways']);    
    $newoptions['showinparentcats']  = isset($_POST['stc-widget-showinparentcats']);   
    $newoptions['showinchildcats']   = isset($_POST['stc-widget-showinchildcats']);
    $newoptions['showinchildcats']   = isset($_POST['stc-widget-showinchildcats']);
    $newoptions['sensitivetaglinks'] = isset($_POST['stc-widget-sensitivetaglinks']);
    $newoptions['activateperformancehacks'] 
      = isset($_POST['stc-widget-activateperformancehacks']);
      
    // get args      
    foreach( $argkeys as $argkey=>$type ) {
      if( is_string($type) )
        $newoptions['args'][$argkey] = $_POST['stc-widget-args-'.$argkey];
      if( is_int($type) )
        $newoptions['args'][$argkey] = is_numeric($_POST['stc-widget-args-'.$argkey]) ?
                                         (int) $_POST['stc-widget-args-'.$argkey] : $type;
      if( is_array($type) )
        $newoptions['args'][$argkey] = in_array($_POST['stc-widget-args-'.$argkey],$type) ? 
                                         $_POST['stc-widget-args-'.$argkey] : $type[0];
    }
  }
  
  // update options if needed
  if ( $options != $newoptions ) {
    $options = $newoptions;
    update_option('stc_widget', $options);
  }
  
  // checkboxes
  $showchildcattags  = $options['showchildcattags']  ? 'checked="checked"' : '';
  $showalways        = $options['showalways']        ? 'checked="checked"' : '';
  $showinparentcats  = $options['showinparentcats']  ? 'checked="checked"' : '';
  $showinchildcats   = $options['showinchildcats']   ? 'checked="checked"' : '';
  $sensitivetaglinks = $options['sensitivetaglinks'] ? 'checked="checked"' : ''; 
  $activateperformancehacks 
    = $options['activateperformancehacks']  ? 'checked="checked"' : '';
  
  // form
  echo '<p><label for="stc-widget-title">'._e('Title:');
  echo '<input type="text" style="width:300px" id="stc-widget-title" name="stc-widget-title" value="'.attribute_escape( $options['title'] ).'" /></label>';
  echo '</p>';
  echo '<p><label for="stc-widget-options">'._e('Display:');
  echo '<input type="checkbox" class="checkbox" id="stc-widget-showchildcattags" name="stc-widget-showchildcattags" '.$showchildcattags.' />'._e( 'Show also tags of subcategories' ).'<br />';  
  echo '<input type="checkbox" class="checkbox" id="stc-widget-showalways" name="stc-widget-showalways" '.$showalways.' />'._e( 'Show always' ).'<br />';  
  echo '<input type="checkbox" class="checkbox" id="stc-widget-showinparentcats" name="stc-widget-showinparentcats" '.$showinparentcats.' />'._e( 'Show in categories with subcategories' ).'<br />';
  echo '<input type="checkbox" class="checkbox" id="stc-widget-showinchildcats" name="stc-widget-showinchildcats" '.$showinchildcats.' />'._e( 'Show in categories without subcategories' ).'<br />';
  echo '</p>';
  echo '<p><label for="stc-widget-options">'._e('Links:');
  echo '<input type="checkbox" class="checkbox" id="stc-widget-sensitivetaglinks" name="stc-widget-sensitivetaglinks" '.$sensitivetaglinks.' />'._e( 'Restricted to current context' ).' (experimental)'.'<br />';    
  echo '</p>';  
  echo '<p><label for="stc-widget-options">'._e('Style:');
  foreach( $argkeys as $argkey=>$values ) {
    echo _e( $argkey ).' ';
    if( is_int($values) || is_string($values) )
      echo '<input type="text" style="width:150px" id="stc-widget-args-'.$argkey.'" name="stc-widget-args-'.$argkey.'" value="'.$options['args'][$argkey].'" /></label>';
    if( is_array($values) ) {
      echo '<select id="stc-widget-args-'.$argkey.'" name="stc-widget-args-'.$argkey.'">';
      foreach( $values as $value ) {
        echo '<option '.($value==$options['args'][$argkey]?'selected':'').'>'.$value.'</option>';      
      }
      echo '</select>';
    }
    echo '<br />';
  }
  echo '</p>';  
  echo '<p><label for="stc-widget-options">'._e('Troubleshooting:');
  echo '<input type="checkbox" class="checkbox" id="stc-widget-activateperformancehacks" name="stc-widget-activateperformancehacks" '.$activateperformancehacks.' />'._e( 'Activate Performance Hacks' ).'<br />';
  echo '</p>';  
  echo '<input type="hidden" name="stc-widget-submit" id="stc-widget-submit" value="1" />';
}

//-----------------------------------------------------------------------------

// filter query
function stc_filter_query_onlyid( $query ) {
  global $stc_filter_query_onlyid_active;
  if( !$stc_filter_query_onlyid_active )
    return $query;
      
  // we need only the ids of the posts, dont load all fields
  global $wpdb;
  return str_replace( "SELECT DISTINCT * FROM $wpdb->posts", 
                      "SELECT DISTINCT $wpdb->posts.ID FROM $wpdb->posts", 
                      $query );
}

//-----------------------------------------------------------------------------

// get tag link
function stc_get_tag_link( $term_ids ) {
	global $wp_rewrite;
	$taglink = $wp_rewrite->get_tag_permastruct();

  $term_slugs = array();
  foreach( $term_ids as $term_id=>$term_taxonomy ) {
  	$term = &get_term( $term_id, $term_taxonomy );
  	if ( is_wp_error( $term ) )
  		return $term;
  	$term_slugs[$term_id] = $term->slug;
  }
  
	if ( empty($taglink) ) {
		$file = get_option('home') . '/';
		$taglink = $file . '?tag=' . implode( '+', $term_slugs );
	} else {
		$taglink = str_replace('%tag%', implode('+',$term_slugs), $taglink);
		$taglink = get_option('home') . user_trailingslashit($taglink, 'category');
	}
  
  return apply_filters('tag_link', $taglink, $tag_id);  
}

// filter tag link
function stc_filter_tag_link( $taglink, $tag_id ) {
  global $stc_filter_tag_link_active;
  if( !$stc_filter_tag_link_active )
    return $taglink;
  
  // get current
  $category = get_query_var('cat'); // current category
  
  if( !empty($category) ) { 
    $stc_filter_tag_link_active = false; // no loop, we are currently doing this
    $taglink = stc_get_tag_link( array( // add category to tag link
      $category=>'category',
      $tag_id=>'post_tag'
    ) );
    $stc_filter_tag_link_active = true; // reset
  }
    
  return $taglink;
}

//-----------------------------------------------------------------------------
    
// (de)activation
function stc_activate() {
  $defaultargs = array(
    'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 45,
    'format' => 'flat', 'orderby' => 'name', 'order' => 'ASC'
  );
  $options = array( 
    'title'             => __('Tags'), 
    'args'              => $defaultargs,
    'showchildcattags'  => true,
    'showalways'        => false, 
    'showinparentcats'  => true, 
    'showinchildcats'   => true,
    'sensitivetaglinks' => false,
    'activateperformancehacks' => false
  );
  add_option( 'stc_widget', $options );
}
function stc_deactivate() {
  delete_option('stc_widget'); 
}

// initialization
function stc_init() {  

  // register widget
  $class['classname'] = 'stc_widget';
  wp_register_sidebar_widget('sensitive_tag_cloud', __('Sensitive Tag Cloud'), 'stc_widget', $class);
  wp_register_widget_control('sensitive_tag_cloud', __('Sensitive Tag Cloud'), 'stc_widget_control', 'width=300&height=160');
  
  // init globals
  global $stc_filter_query_onlyid_active;
  global $stc_filter_tag_link_active;  
  $stc_filter_query_onlyid_active = false;
  $stc_filter_tag_link_active     = false;
}

//-----------------------------------------------------------------------------

// actions
add_action( 'activate_'.plugin_basename(__FILE__),   'stc_activate' );
add_action( 'deactivate_'.plugin_basename(__FILE__), 'stc_deactivate' );
add_action( 'init', 'stc_init');

// filters
add_filter( 'query', 'stc_filter_query_onlyid', 9 ); // a filter for fields queried in post/get_posts() only would be better
add_filter( 'tag_link', 'stc_filter_tag_link', 5, 2 ); // extend tag links

//-----------------------------------------------------------------------------

?>