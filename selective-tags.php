<?php
/**
Plugin Name: Selective Tag Cloud Widget
Plugin URI: http://www.digcms.com
Description: Provide sidebar widgets that can be used to display Selective Tags from a set of tags which is selected by admin in the sidebar.
Author: digcms.com
License: GPL
Version: 1.2
Author URI: http://www.digcms.com
Text Domain: selective-tags

*/

class SelectiveTags {

    /**
     * Initalize the plugin by registering the hooks
     */
    function __construct() {

        // Load localization domain
        load_plugin_textdomain( 'selective-tags', false, dirname(plugin_basename(__FILE__)) .  '/languages' );

        // Register hooks
        add_action('admin_print_scripts', array(&$this, 'add_script'));
        add_action('admin_head', array(&$this, 'add_script_config'));

        //Short code
        add_shortcode('selective-tags', array(&$this, 'shortcode_handler'));
    }

    /**
     * Add script to admin page
     */
    function add_script() {
        // Build in tag auto complete script
        wp_enqueue_script( 'suggest' );
    }

    /**
     * add script to admin page
     */
    function add_script_config() {
        // Add script only to Widgets page
        if (substr_compare($_SERVER['REQUEST_URI'], 'widgets.php', -11) == 0) {
    ?>

    <script type="text/javascript">
    // Function to add auto suggest
    function setSuggest(id) {
        jQuery('#' + id).suggest("<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin-ajax.php?action=ajax-tag-search&tax=post_tag", {multiple:true, multipleSep: ","});
    }
    </script>
    <?php
        }
    }

    /**
     * Expand the shortcode
     *
     * @param <array> $attributes
     */
    function shortcode_handler($attributes) {
        extract(shortcode_atts(array(
            "tags"      => ''   // comma Separated list of tags            
            
        ), $attributes));

        // call the template function
        return get_selective_tags($tags);
    }

    // PHP4 compatibility
    function SelectiveTags() {
        $this->__construct();
    }
}

// Start this plugin once all other plugins are fully loaded
add_action( 'init', 'SelectiveTags' ); function SelectiveTags() { global $SelectiveTags; $SelectiveTags = new SelectiveTags(); }

// register TagWidget widget
add_action('widgets_init', create_function('', 'return register_widget("TagWidget");'));

/**
 * TagWidget Class
 */
class TagWidget extends WP_Widget {
    /** constructor */
    function TagWidget() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'TagWidget', 'description' => __('Widget that shows selective tag from a set of tags which is selected by admin', 'selective-tags'));

		/* Widget control settings. */
		$control_ops = array('id_base' => 'tag-widget' );

		/* Create the widget. */
		parent::WP_Widget( 'tag-widget', __('Selective Topic', 'selective-tags'), $widget_ops, $control_ops );
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {
        extract( $args );

        $tags = $instance['tags'];
        $title = $instance['title'];

        echo $before_widget;
        echo $before_title;
        echo $title;
        echo $after_title;
        //posts_by_tag($tags, $number, $widget_id);
        echo show_selective_tags($tags, $number, $widget_id);
        echo $after_widget;
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {
		$instance = $old_instance;
        // validate data
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['tags'] = strip_tags($new_instance['tags']);
        
        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {

		/* Set up some default widget settings. */
		$defaults = array( 'title' => '', 'tags' => '');
		$instance = wp_parse_args( (array) $instance, $defaults );

        $title = esc_attr($instance['title']);
        $tags = $instance['tags'];
       
        
?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'selective-tags'); ?>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
        </p>

		<p>
			<label for="<?php echo $this->get_field_id('tags'); ?>">
				<?php _e( 'Tags:' , 'selective-tags'); ?><br />
                <input class="widefat" id="<?php echo $this->get_field_id('tags'); ?>" name="<?php echo $this->get_field_name('tags'); ?>" type="text" value="<?php echo $tags; ?>" onfocus ="setSuggest('<?php echo $this->get_field_id('tags'); ?>');" />
			</label><br />
            <?php _e('Separate multiple tags by comma', 'selective-tags');?>
		</p>

              
        
        
<?php
    }
} // class TagWidget

/**
 * Template function to display selective tags
 *
 * @param <string> $tags 
 * @param <string> $widget_id - widget id (incase of widgets)
 */
function posts_by_tag($tags,  $widget_id = "0" ) {
    echo get_selective_tags($tags, $widget_id);
}

/**
 * Helper function for posts_by_tag
 *
 * @param <string> $tags
 * @param <string> $widget_id - widget id (incase of widgets)
 */
function get_selective_tags($tags,  $widget_id = "0" ) {
    global $wp_query;

    // first look in cache
    $output = wp_cache_get($widget_id, 'selective-tags');
    if ($output === false || $widget_id == "0") {
        // Not present in cache so load it

        // Get array of post info.
        $tag_array = explode(",", $tags);
        $tag_id_array = array();

        foreach ($tag_array as $tag) {
            $tag_id_array[] = get_tag_ID(trim($tag));
        }

        $comma_separated_tag_ids = implode(",", $tag_id_array);
        
$tags = get_tags();
$output = '<div class="post_tags">';

foreach ($tags as $tag){
    if(in_array($tag->term_id, $tag_id_array)){
	$tag_link = get_tag_link($tag->term_id);

	$output .= "<a href='{$tag_link}' title='{$tag->name} Tag' class='{$tag->slug}'>";
	$output .= "{$tag->name}</a>";
    }
}
$output .= '</div>';
//echo $output;

    
        // if it is not called from theme, save the output to cache
        if ($widget_id != "0") {
            wp_cache_set($widget_id, $output, 'selective-tags', 3600);
        }
    }

    return $output;
}

/**
 * get tag id from tag name
 *
 * @param <string> $tag_name
 * @return <int> term id. 0 if not found
 */
if (!function_exists("get_tag_ID")) {
    function get_tag_ID($tag_name) {
        $tag = get_term_by('name', $tag_name, 'post_tag');
        if ($tag) {
            return $tag->term_id;
        } else {
            return 0;
        }
    }
}


/**
 * Add navigation menu
 */
if(!function_exists('smbms_add_menu')) {
	function smbms_add_menu() {
	    //Add a submenu to Manage
        add_options_page("Selective Tags", "Selective Tags", 8, basename(__FILE__), "smbms_displayOptions");

	}

        /**
 * Show the Admin page
 */
if (!function_exists('smbms_displayOptions')) {
    function smbms_displayOptions() {
        global $wpdb;
?>
	
    <div class="wrap">
		<h2>Selective Tags</h2>

        <h3><?php _e("Select Tags", 'selective-tags'); ?></h3>
        <h4><?php _e("Select the tags which you want to show in widget.", 'selective-tags') ?></h4>


        <form name="smbm_form" id = "smbm_cat_form"
        action="<?php echo get_bloginfo("wpurl"); ?>/wp-admin/options-general.php?page=selective-tags.php" method="post" >

        <fieldset class="options">
		<table class="optiontable">
            <tr>
                <td scope="row" >
                <select multiple="multiple" id="mtags" name="mtags[]" >
            <?php
                $posttags = get_tags();
                $mtags = get_option('mtags');
                $mtags = explode(',', $mtags );
                
                if ($posttags) {
                  foreach($posttags as $tag) {
                         if (in_array($tag->term_id, $mtags)) {
                            echo '<option value="'.$tag->term_id.'" selected="selected">'.$tag->name.'</option>';
                         }  else {
                             echo '<option value="'.$tag->term_id.'">'.$tag->name.'</option>';
                         }
                    
                  }
                }
                ?>
                </select>
                
                </td>
  
            </tr>

		</table>
		</fieldset>
        <p class="submit">
                  <input type="submit" name="submit" value="<?php _e("Save", 'selective-tags') ?>&raquo;">
        </p>

    

		<input type="hidden" name="smbms_action" value="selective-tags-cats" />
		</form>
        
    </div>
<?php

    
    
    }
}
}


/**
 * Adds the settings link in the Plugin page. 
 * @staticvar <type> $this_plugin
 * @param <type> $links
 * @param <type> $file
 */
function smbms_filter_plugin_actions($links, $file) {
    static $this_plugin;
    if( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

    if( $file == $this_plugin ) {
        $settings_link = '<a href="options-general.php?page=selective-tags.php">' . _('Manage', 'Seelective tags') . '</a>';
        array_unshift( $links, $settings_link ); // before other links
    }
    return $links;
}

if (!function_exists('smbms_request_handler')) {
function smbms_request_handler(){
    if (isset($_POST['smbms_action'])) {
            
            $mtags = join(',', $_POST[mtags]);

$option_name = 'mtags';
$newvalue = $mtags;
if ( get_option( $option_name ) != $newvalue ) {
    update_option( $option_name, $newvalue );
} else {
    $deprecated = ' ';
    $autoload = 'no';
    add_option( $option_name, $newvalue, $deprecated, $autoload );
}
        

    }
}
}


add_filter( 'plugin_action_links', 'smbms_filter_plugin_actions', 10, 2 );

add_action('admin_menu', 'smbms_add_menu');
add_action('init', 'smbms_request_handler');

function ad_scripts(){    
    //wp_enqueue_script('my-script', '/wp-content/plugins/selective-tags/jquery.js');
    wp_enqueue_script('my-script1', '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js');
    wp_enqueue_script('my-script2', '/wp-content/plugins/selective-tags/jquery.asmselect.js');
    wp_enqueue_style('my-style1', '/wp-content/plugins/selective-tags/jquery.asmselect.css');
    wp_enqueue_style('my-style2', '/wp-content/plugins/selective-tags/example.css');

}

add_action( 'admin_init', 'ad_scripts' );


add_action('admin_head', 'smbms_print_scripts');

/**
 * Print JavaScript
 */
function smbms_print_scripts() {
?>
<link rel='stylesheet' id='my-style1-css'  href='<?php bloginfo('url')?>/wp-content/plugins/selective-tags/jquery.asmselect.css?ver=3.2.1' type='text/css' media='all' />
<link rel='stylesheet' id='my-style2-css'  href='<?php bloginfo('url')?>/wp-content/plugins/selective-tags/example.css?ver=3.2.1' type='text/css' media='all' />
<script type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js'></script>
<script type='text/javascript' src='<?php bloginfo('url')?>/wp-content/plugins/selective-tags/jquery.asmselect.js?ver=3.2.1'></script>



<script type="text/javascript">

		jQuery(function() {

			jQuery("#mtags").asmSelect({
				addItemTarget: 'bottom',
				animate: true,
				highlight: true,
				sortable: true

			}).after(jQuery("<a href='#'>Select All</a>").click(function() {
				jQuery("#mtags").children().attr("selected", "selected").end().change();
				return false;
			}));


		});

	</script>
<?php
}


function show_selective_tags($tags,  $widget_id = "0" ) {
    global $wp_query;

    // first look in cache
    $output = wp_cache_get($widget_id, 'selective-tags');
    if ($output === false || $widget_id == "0") {
        // Not present in cache so load it

        $tag_id_array  =get_option('mtags');
        $comma_separated_tag_ids = explode(",", $tag_id_array);

      

$tags = get_tags();
$output = '<div class="post_tags-select">';

  
foreach ($tags as $tag){
    if(in_array($tag->term_id, $comma_separated_tag_ids)){
	$tag_link = get_tag_link($tag->term_id);

	$output .= "<a href='{$tag_link}' title='{$tag->name} Tag' class='{$tag->slug}'>";
	$output .= "{$tag->name}</a>";
    }
 
}
$output .= '</div>';
//echo $output;
 
    }

    return $output;
}


?>
