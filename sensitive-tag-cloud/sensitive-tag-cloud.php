<?php
//-----------------------------------------------------------------------------
/*
Plugin Name: Sensitive Tag Cloud
Version: 0.8.2
Plugin URI: http://www.rene-ade.de/inhalte/wordpress-plugin-sensitivetagcloud.html
Description: This wordpress plugin provides a highly configurable tagcloud that shows tags depending of the current context.
Author: Ren&eacute; Ade
Author URI: http://www.rene-ade.de
Min WP Version: 2.3
*/
//-----------------------------------------------------------------------------
?>
<?php

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

// check display conditions
function stc_widget_display_allowed( $options=null ) {

  // options
  if( !$options )
    $options = get_option( 'stc_widget' ); // get options

  // check if conditions are active
  if( $options['display'][null] ) // conditions inactive
    return true;
    
  // search for matching conditions
  foreach( $options['display'] as $condition=>$active ) { // get all display conditions
    if( $condition && $active ) { // is condition valid and active
      if( $condition() ) // check condition
        return true; // condition matching: display
    }
  }
  
  // no condition matching
  return false;
}

//-----------------------------------------------------------------------------

// get posts
function stc_get_posts( $options=null ) {

  // options
  if( !$options )
    $options = get_option( 'stc_widget' ); // get options
  
  // query vars
  global $wp_the_query; // current query
  $queryvars = $wp_the_query->query_vars; // current query vars
  $queryvars['nopaging'] = true; // get all posts
  
  // get posts
  global $stc_filter_query_onlyminimum_active; // query performance optimization
  $query =& new WP_Query(); // a new query object
  $stc_filter_query_onlyminimum_active_reset = 
    $stc_filter_query_onlyminimum_active; // get last state
  if( $options['activateperformancehacks'] ) // check option
    $stc_filter_query_onlyminimum_active = true; // get only the ids, dont load all post fields    
  $posts = $query->query( $queryvars ); // query posts
  $stc_filter_query_onlyminimum_active =  
    $stc_filter_query_onlyminimum_active_reset; // reset to last state

  // get only real posts
  $posts_tmp = array();
  foreach( $posts as $post ) {
    if( $post->post_type=='post' ) // check post type
      $posts_tmp[] = $post;
  }  
  $posts = $posts_tmp;
    
  // return posts
  return $posts;
}

//-----------------------------------------------------------------------------

// the tagcloud widget
function stc_widget( $args ) {

  // check display conditions
  if( !stc_widget_display_allowed() )
    return; // cancle

  // comment // if you dont like this comment, you may remove it :-(
  echo '<!-- ';
  echo 'WordPress Plugin SensitiveTagCloud by René Ade';
  echo ' - ';
  echo 'http://www.rene-ade.de/inhalte/wordpress-plugin-sensitivetagcloud.html';
  echo ' -->';

  // args
  extract( $args ); // extract args
 
  // options
  $options = get_option( 'stc_widget' ); // get options
  
  // get current posts
  $posts = stc_get_posts( $options );

  // get tags
  $tags = array();
  if( empty($posts) ) { // if there are no posts
    $tags = get_tags( $args ); // get all tags 
  }
  else { // there are posts
    // get tags of posts
    foreach( $posts as $post ) {  // go through posts
      $posttags = wp_get_post_tags( $post->ID, $args ); // get tags of the current post
      foreach( $posttags as $posttag ) { // go through tags of the current post
        if( !array_key_exists($posttag->name,$tags)  ) { // is tag missing in list
          $tags[ $posttag->name ] = $posttag; // add tag
          $tags[ $posttag->name ]->count = 1; // this is the first occurrence
        }
        else { // tag is already in list
          $tags[ $posttag->name ]->count += 1; // increment occurrence counter
        }
      }
    }
  }
  
  // exclude tags
  if( !empty($options['excludetags']) ) {
    $tags_tmp = array();
    foreach( $tags as $tag=>$value ) {
      if( !in_array($tag,$options['excludetags']) ) // only if not excluded
        $tags_tmp[ $tag ] = $value; // readd      
    }
    $tags = $tags_tmp;
  }
  
  // check if there are tags to display
  if( empty($tags) ) // no tags
    return; // dont display cloud
    
  // limit tags
  if( isset($options['args']['number']) && count($tags)>$options['args']['number'] ) {
    $tags = array_chunk( $tags, $options['args']['number'] );    
    $tags = $tags[0];
  }
  
  // generate cloud
  global $stc_filter_tag_link_active; // restricted links flag
  $args_cloud = array_merge( $args, $options['args'] ); // cloud args
  $stc_filter_tag_link_active_reset = 
    $stc_filter_tag_link_active; // get last state
  if( $options['restrictlinks']['cat'] || $options['restrictlinks']['tag'] ) // check if restrict links
    $stc_filter_tag_link_active = true; // restricted links
  $cloud = wp_generate_tag_cloud( $tags, $args_cloud ); // generate cloud
  $stc_filter_tag_link_active =  
    $stc_filter_tag_link_active_reset; // reset to last state
  if( is_wp_error($cloud) ) // error generating cloud
    return; // cancle
  $cloud = apply_filters( 'wp_tag_cloud', $cloud, $args ); // apply cloud filter
   
  // output
  echo $before_widget;
  echo $before_title . $options['title'] . $after_title;
  echo $cloud;
  echo $after_widget;
  
  // tagcloud completed
  return;
}

//-----------------------------------------------------------------------------

// widget configuration
function stc_widget_control() {

  // options
  $options = $newoptions = get_option('stc_widget'); // get options

  // define args
  $argkeys = array( 
    'smallest'=>0, // number
    'largest'=>0, // number
    'unit'=>array('pt'), // options
    'number'=>0, // number
    'format'=>array('flat','list'), // options
    'orderby'=>array('name','count'), // options
    'order'=>array('ASC','DESC') // options
  );
  // define display conditions
  $displayconditions = array( 
    // 'is_home'     => 'Show on home page (all tags)', not working
    'is_page'     => 'Show on pages (all tags)',
    'is_single'   => 'Show on post pages (tags of post)',
    'is_search'   => 'Show on search page (tags of posts)',
    'is_archive'  => 'Show in all archives (ignore archive type)',     
    'is_date'     => 'Show in date archives (tags of posts)',
    'is_author'   => 'Show in author archives (tags of posts)',
    'is_tag'      => 'Show in tag archives (tags of posts)',
    'is_category' => 'Show in categories archives (tags of posts)'
  );
  
  // set new options
  if( $_POST['stc-widget-submit'] ) {
    // texts
    $newoptions['title'] = strip_tags( stripslashes($_POST['stc-widget-title']) ); // the title
    // tag arrays
    $newoptions['excludetags'] = explode( ',', str_replace(' ','',strip_tags(stripslashes($_POST['stc-widget-excludetags']))) ); // exclude tags
    // display conditions
    $newoptions['display'][null] = isset( $_POST['stc-widget-display'] );
    foreach( $displayconditions as $key=>$displaycondition ) {
      $newoptions['display'][ $key ] = isset( $_POST['stc-widget-display-'.$key] );
    }
    // checkboxes
    $newoptions['restrictlinks'] = array( // restrict links
      'tag' => isset($_POST['stc-widget-restrictlinks-tag']), 
      'cat' => isset($_POST['stc-widget-restrictlinks-cat']),
      'cat-onlysubcats' => isset($_POST['stc-widget-restrictlinks-cat-onlysubcats'])
    );
    $newoptions['activateperformancehacks']    = isset( $_POST['stc-widget-activateperformancehacks'] ); // performance optimization      
    // display args
    foreach( $argkeys as $argkey=>$type ) {
      if( is_string($type) ) // string field
        $newoptions['args'][$argkey] = $_POST['stc-widget-args-'.$argkey];
      if( is_int($type) ) // int field
        $newoptions['args'][$argkey] = is_numeric($_POST['stc-widget-args-'.$argkey]) ?
                                         (int)$_POST['stc-widget-args-'.$argkey] : $type;
      if( is_array($type) ) // options
        $newoptions['args'][$argkey] = in_array($_POST['stc-widget-args-'.$argkey],$type) ? 
                                         $_POST['stc-widget-args-'.$argkey] : $type[0];
    }
  }
  
  // update options if needed
  if( $options != $newoptions ) {
    $options = $newoptions;
    update_option('stc_widget', $options);
  }
  
  // display form
  echo '<p>'._e('Title');
    echo '<input type="text" style="width:300px" id="stc-widget-title" name="stc-widget-title" value="'.attribute_escape($options['title']).'" />'.'<br />';
  echo '</p>';
  echo '<p>'._e('Display');
    $displayalways = $options['display'][null] ? 'checked="checked"' : '';  
    echo '<input type="checkbox" class="checkbox" id="stc-widget-display" name="stc-widget-display" '.$displayalways.' />'._e( 'Show always (ignore conditions)' ).'<br />';  
    foreach( $displayconditions as $key=>$displaycondition ) {
      $checked = $options['display'][ $key ] ? 'checked="checked"' : '';
      echo '<input type="checkbox" class="checkbox" id="stc-widget-display-'.$key.'" name="stc-widget-display-'.$key.'" '.$checked.' />'._e( $displaycondition ).'<br />';    
    }
  echo '</p>';  
  echo '<p>'._e('Links');
    $restrictlinks_tag = $options['restrictlinks']['tag'] ? 'checked="checked"' : ''; 
    $restrictlinks_cat = $options['restrictlinks']['cat'] ? 'checked="checked"' : ''; 
    $restrictlinks_cat_onlysubcats = $options['restrictlinks']['cat-onlysubcats'] ? 'checked="checked"' : '';
    echo '<input type="checkbox" class="checkbox" id="stc-widget-restrictlinks-tag" name="stc-widget-restrictlinks-tag" '.$restrictlinks_tag.' />'._e('Restricted to current tag').'<br />';    
    echo '<input type="checkbox" class="checkbox" id="stc-widget-restrictlinks-cat" name="stc-widget-restrictlinks-cat" '.$restrictlinks_cat.' />'._e('Restricted to current category').' (Subcategories not included!)'.'<br />';    
    echo '&nbsp<input type="checkbox" class="checkbox" id="stc-widget-restrictlinks-cat-onlysubcats" name="stc-widget-restrictlinks-cat-onlysubcats" '.$restrictlinks_cat_onlysubcats.' />'._e('Restrict only to categories without subcategories').'<br />';
  echo '</p>';  
  echo '<p>'._e('Style');
    foreach( $argkeys as $argkey=>$values ) {
      echo _e($argkey).' ';
      if( is_int($values) || is_string($values) )
        echo '<input type="text" style="width:150px" id="stc-widget-args-'.$argkey.'" name="stc-widget-args-'.$argkey.'" value="'.$options['args'][$argkey].'" />';
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
  echo '<p>'._e('Exclude');
    $excludetags = implode( ', ', $options['excludetags'] );
    echo _e('Exclude Tags').' '.'<input type="text" class="text" id="stc-widget-excludetags" name="stc-widget-excludetags" value="'.$excludetags.'"/>'.'<br />';
  echo '</p>';  
  echo '<p>'._e('Performance');
    $activateperformancehacks = $options['activateperformancehacks'] ? 'checked="checked"' : '';
    echo '<input type="checkbox" class="checkbox" id="stc-widget-activateperformancehacks" name="stc-widget-activateperformancehacks" '.$activateperformancehacks.' />'._e('Activate Performance Hacks').'<br />';
  echo '</p>';  
  echo '<input type="hidden" name="stc-widget-submit" id="stc-widget-submit" value="1" />';
  
  // completed control
  return;
}

//-----------------------------------------------------------------------------

// filter query
function stc_filter_query_onlyminimum( $query ) {
  global $stc_filter_query_onlyminimum_active;
  if( !$stc_filter_query_onlyminimum_active )
    return $query;
    
  global $wpdb;
  $query = str_replace( " $wpdb->posts.* ",
                        " $wpdb->posts.ID, $wpdb->posts.post_type ", 
                        $query ); // select only the id
                             
  // return the optimized query                        
  return $query;                        
}

//-----------------------------------------------------------------------------

// get tag link
function stc_get_tag_link( $slugs_and ) {
	global $wp_rewrite;
  
  // permastruct
	$taglink = $wp_rewrite->get_tag_permastruct();
  
  // slugs
  $slugs = implode( '+', $slugs_and );
  
  // build link
	if ( empty($taglink) ) { // no permalink: use getvars
		$file = get_option('home') . '/';
		$taglink = $file . '?tag=' . $slugs;
	} else { // permalink
		$taglink = str_replace('%tag%', $slugs, $taglink);
		$taglink = get_option('home') . user_trailingslashit($taglink, 'category');
	}
  
  // apply filter and return link
  return apply_filters('tag_link', $taglink, $tag_id);  
}

// get current slugs
function stc_filter_tag_link_get_slugs( $options=null ) {

  // use cached values if possible
  global $stc_filter_tag_link_get_slugs_cache;
  if( is_array($stc_filter_tag_link_get_slugs_cache) )
    return $stc_filter_tag_link_get_slugs_cache;
  
  // initialize
  $stc_filter_tag_link_get_slugs_cache = array(
    'slugs_and' => array() 
  );
  
  // options
  if( !$options )
    $options = get_option( 'stc_widget' ); // get options
      
  // get last slugs
  $stc_filter_tag_link_get_slugs_cache['slugs_and'] = 
    get_query_var('tag_slug__and');
    
  // add current slugs
  $tag_id = get_query_var('tag_id'); // tag
  $cat_id = get_query_var('cat'); // cat
  if( $options['restrictlinks']['tag'] && !empty($tag_id) ) { // restrict to tag?
    $tag_term = &get_term( $tag_id, 'post_tag' ); // get term
    if( !is_wp_error($tag_term) )
      $stc_filter_tag_link_get_slugs_cache['slugs_and'][] = $tag_term->slug; // slug
  }    
  if( $options['restrictlinks']['cat'] && !empty($cat_id) ) { // restirct to cat?
    $cat_hasnochilds = null;
    if( $options['restrictlinks']['cat-onlysubcats'] ) { // check for subcategories if needed
      $cat_children = get_term_children( $cat_id, 'category' ); // get direct subcategories
      if( !is_wp_error($cat_children) )
        $cat_hasnochilds = ( count($cat_children)==0 ); // count subcategories
    }
    if( !$options['restrictlinks']['cat-onlysubcats'] || $cat_hasnochilds ) { // check if has subcategories if restricted
      $cat_term = &get_term( $cat_id, 'category' ); // get term
      if( !is_wp_error($cat_term) )
        $stc_filter_tag_link_get_slugs_cache['slugs_and'][] = $cat_term->slug; // slug
    }
  }
    
  // unique
  $stc_filter_tag_link_get_slugs_cache['slugs_and'] = array_unique(
    $stc_filter_tag_link_get_slugs_cache['slugs_and'] );
    
  // return slugs
  return $stc_filter_tag_link_get_slugs_cache;
}

// filter tag link
function stc_filter_tag_link( $taglink, $tag_id ) {
  global $stc_filter_tag_link_active;
  if( !$stc_filter_tag_link_active )
    return $taglink;

  // options
  $options = get_option( 'stc_widget' ); // get options

  // only for tag archives ant cats
  $restrictlinks = false;
  if( $options['restrictlinks']['tag'] && is_tag() ) // tag
    $restrictlinks = true;
  if( $options['restrictlinks']['cat'] && is_category() ) // category
    $restrictlinks = true;
  if( !$restrictlinks )
    return $taglink;
    
  // get current slugs
  $slugs = stc_filter_tag_link_get_slugs( $options ); // get all restriction slugs
      
  // get tag slug by id
  $tag_term = &get_term( $tag_id, 'post_tag' );
  if( is_wp_error($tag_term) )
    return $taglink;
    
  // merge slugs
  $link_slugs = array( $tag_term->slug ); // the current tag slug
  $link_slugs = array_merge( $link_slugs, $slugs['slugs_and'] );
  // unique
  $link_slugs = array_unique( $link_slugs );

  // get link
  $stc_filter_tag_link_active = false; // no endless loop through filters
  $taglink = stc_get_tag_link( $link_slugs ); // get link by slugs
  $stc_filter_tag_link_active = true; // reset
    
  // return tag link
  return $taglink;
}

//-----------------------------------------------------------------------------
    
// (de)activation
function stc_activate() {
  
  // default args
  $defaultargs = array(
    'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 45,
    'format' => 'flat', 'orderby' => 'name', 'order' => 'ASC'
  );
  
  // options, defaultvalues
  $options = array( 
    'title'   => __('Tags'), 
    'args'    => $defaultargs,
    'display' => array( // display only if function evaluates to true
      null       => false,
      'is_single'   => false, 
      'is_page'     => false,
      'is_archive'  => true,     
      'is_date'     => true,
      'is_author'   => true,
      'is_category' => true,
      'is_tag'      => true,
      'is_home'     => true,
      'is_search'   => true
    ),
    'restrictlinks' => array( // restrict links to...
      'tag' => true,
      'cat' => false,
      'cat-onlysubcats' => false
    ),
    'activateperformancehacks' => false, // activate performance hacks
    'excludetags'              => array() // a list of tags to exclude from the tagcloud
  );
  
  // register option
  add_option( 'stc_widget', $options );
  
  // activeted
  return;
}
function stc_deactivate() {

  // unregister option
  delete_option('stc_widget'); 
  
  // deactivated
  return;
}

// initialization
function stc_init() {  

  // register widget
  $class['classname'] = 'stc_widget';
  wp_register_sidebar_widget('sensitive_tag_cloud', __('Sensitive Tag Cloud'), 'stc_widget', $class);
  wp_register_widget_control('sensitive_tag_cloud', __('Sensitive Tag Cloud'), 'stc_widget_control', 'width=300&height=800');
  
  // init globals
  global $stc_filter_query_onlyminimum_active; // performance hack
  global $stc_filter_tag_link_active;          // restrict links
  global $stc_filter_tag_link_get_slugs_cache; // current restriction slugs
  $stc_filter_query_onlyminimum_active = false;
  $stc_filter_tag_link_active          = false;
  $stc_filter_tag_link_get_slugs_cache = null;
  
  // initialized
  return;
}

//-----------------------------------------------------------------------------

// actions
add_action( 'activate_'.plugin_basename(__FILE__),   'stc_activate' );
add_action( 'deactivate_'.plugin_basename(__FILE__), 'stc_deactivate' );
add_action( 'init', 'stc_init');

// filters
add_filter( 'query', 'stc_filter_query_onlyminimum', 9 ); // a filter for fields queried in post/get_posts() only would be better
add_filter( 'tag_link', 'stc_filter_tag_link', 5, 2 ); // extend tag links

//-----------------------------------------------------------------------------

?>