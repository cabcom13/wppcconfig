<?php
/**
 * Plugin Name: PCBuilder
 * Plugin URI: https://www.skyverge.com/product/woocommerce-custom-product-tabs-lite/
 * Description: 
 * purchasing_price: Sven Schmalfuß
 * purchasing_price URI: http://www.skyverge.com/
 * Version: 1.0.0
 * Tested up to: 4.8
 * Text Domain: woocommerce-custom-product-tabs-lite
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2012-2017, SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package     WC-Custom-Product-Tabs-Lite
 * @purchasing_price      SkyVerge
 * @category    Plugin
 * @copyright   Copyright (c) 2012-2017, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

// Check if WooCommerce is active & at least v2.5.5, and bail if it's not
if ( ! PCBuilder::is_woocommerce_active() || version_compare( get_option( 'woocommerce_db_version' ), '2.5.5', '<' ) ) {
	add_action( 'admin_notices', array( 'PCBuilder', 'render_woocommerce_requirements_notice' ) );
	return;
}

// INCLUDE COMPONENT MANAGER LIST TABLE CLASS
require_once('component_manager.class.php');
// INCLUDE COMPONENT SHORTCUT LIST TABLE CLASS
require_once('component_shortcut.class.php');


function get_components_list($product_id, $return = 'array'){
	
	  global $product;
	  global $woocommerce;
	  $ava = get_post_meta($product_id, 'components');
	  $selected_data = array();
	  $total_price = $product->get_price();
	  echo $total_price;
	  foreach($ava[0]['available'] as $key => $components){
			$y = array();
			foreach($components as $component){
				$com_data = get_component_by_ID($component);
					
				if($ava[0]['buildin'][$key] == $com_data->component_id){	
					$selected_price_id = $com_data->component_id;	
				}
			
				if($_POST['components']['selected'][$key] == $com_data->component_id){
					  if($com_data->component_purchasing_price != 0){
						if($ava[0]['buildin'][$key] == $com_data->component_id){
							$calc_price = 0; 
						} else {
							$calc_price = $com_data->component_retail_price - get_component_price_by_ID($selected_price_id);   	
						}							
						
					  } else {
						$calc_price = $com_data->component_purchasing_price - get_component_price_by_ID($selected_price_id, 'component_retail_price');   
					  }
				} else {
					  if($com_data->component_purchasing_price != 0){
						$calc_price = $com_data->component_retail_price - get_component_price_by_ID($selected_price_id);   	
					  } else {
						$calc_price = $com_data->component_purchasing_price - get_component_price_by_ID($selected_price_id, 'component_retail_price');					
					  }  
				}

				$tax_rates    = WC_Tax::get_rates( $product->get_tax_class() );
				$taxes        = WC_Tax::calc_tax($calc_price, $tax_rates, false );
				$tax_amount   = WC_Tax::get_tax_total( $taxes );
				$price 		  = round( $calc_price + $tax_amount, wc_get_price_decimals() ); 

				if($ava[0]['buildin'][$key] == $com_data->component_id){	
					array_push($y, array(
						'id' => $com_data->component_id,
						'component_name' => $com_data->component_name,
						'component_image' => wp_get_attachment_image_src($com_data->component_image),
						'component_descripion' => htmlspecialchars($com_data->component_descripion),
						'component_status' => $com_data->component_status,
						'component_out_of_stock' =>  $com_data->component_out_of_stock,
						'is_build_in' => true,
						'is_selected' =>  true,
						'purchasing_price' =>  $com_data->component_purchasing_price,
						'retail_price' => $com_data->component_retail_price,
						'price' =>  array(
							'formated' => wc_price( 0),
							'natural' => 0,
							'tax' => array(
								'formated' => wc_price($taxes[1]),
								'natural' => $taxes[1]
							)
						)
					));

				} else {
					
					array_push($y, array(
						'id' => $com_data->component_id,
						'component_name' => $com_data->component_name,
						'component_image' => wp_get_attachment_image_src($com_data->component_image),
						'component_descripion' => htmlspecialchars($com_data->component_descripion),
						'component_status' => $com_data->component_status,
						'component_out_of_stock' =>  $com_data->component_out_of_stock,
						'is_build_in' => false,
						'is_selected' => $_POST['components']['selected'][$key] == $com_data->component_id ? true:false,
						'purchasing_price' =>  $com_data->component_purchasing_price,
						'retail_price' => $com_data->component_retail_price,
						'price' =>  array(
							'formated' => wc_price( $price),
							'natural' => $price,
							'tax' => array(
								'formated' => wc_price($taxes[1]),
								'natural' => $taxes[1]
							)
						)
					));
					
					if(($_POST['components']['selected'][$key] == $com_data->component_id)||($ava[0]['buildin'][$key] == $com_data->component_id)){
						$total_price += $price;	
					}

				
				}		

			}
			
			$total_price_clear = $total_price;
			$tax_rates    = WC_Tax::get_rates( $product->get_tax_class() );
			$taxes        = WC_Tax::calc_tax($total_price, $tax_rates, false );
			$tax_amount   = WC_Tax::get_tax_total( $taxes );
			$total_price_re  = round( $total_price + $tax_amount, wc_get_price_decimals() ); 
			
			array_push($selected_data, array(
				'cat' => $key,
				'selected_price_id' => $selected_price_id,
				'cat_data' => get_term($key),
				'data' => $y,
				'total_price' =>  array(
					'formated' =>wc_price($total_price_re),
					'natural' => $total_price,
					'taxless' => $total_price_clear,
					'taxrate' => round($tax_rates[1]['rate'],0).'% '.$tax_rates[1]['label'] ,
					'tax' => array(
						'formated' => wc_price($taxes[1]),
						'natural' => $taxes[1]
					)
				)
			)); 
	  }
	if($return == 'array'){
		return $selected_data;
	} else if($return == 'json'){
		return json_encode($selected_data);
	}
	
}


function get_component_by_ID($component_id){
	global $wpdb;
		$database_name = $wpdb->prefix.'components' ;
		$sql_stat = 'WHERE component_id = '.$component_id;
		$query = "SELECT * FROM $database_name  $sql_stat";				
	
	return $wpdb->get_row($query , OBJECT);
}

function get_component_price_by_ID($component_id, $typ = 'component_purchasing_price'){
	global $wpdb;
		$database_name = $wpdb->prefix.'components' ;
		$sql_stat = 'WHERE component_id = '.$component_id;
		$query = "SELECT $typ FROM $database_name  $sql_stat";				
	
	return $wpdb->get_var($query);
}



/**
 * Main plugin class PCBuilder
 *
 * @since 1.0.0
 */
class PCBuilder {


	/** @var bool|array tab data */
	private $tab_data = false;

	/** plugin version number */
	const VERSION = '1.0.0';

	/** plugin version name */
	const VERSION_OPTION_NAME = 'PCBuilder_db_version';

	/** @var PCBuilder single instance of this plugin */
	protected static $instance;


	/**
	 * Gets things started by adding an action to initialize this plugin once
	 * WooCommerce is known to be active and initialized
	 */
	public function __construct() {

		// Installation
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			$this->install();
		}

		add_action( 'init',             array( $this, 'load_translation' ) );
		add_action( 'woocommerce_init', array( $this, 'init' ) );
	}


	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 1.5.0
	 */
	public function __clone() {
		/* translators: Placeholders: %s - plugin name */
		_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'You cannot clone instances of %s.', 'woocommerce-custom-product-tabs-lite' ), 'WooCommerce Custom Product Tabs Lite' ), '1.5.0' );
	}


	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 1.5.0
	 */
	public function __wakeup() {
		/* translators: Placeholders: %s - plugin name */
		_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'You cannot unserialize instances of %s.', 'woocommerce-custom-product-tabs-lite' ), 'WooCommerce Custom Product Tabs Lite' ), '1.5.0' );
	}


	/**
	 * Load translations
	 *
	 * @since 1.2.5
	 */
	public function load_translation() {
		// localization
		load_plugin_textdomain( 'woocommerce-custom-product-tabs-lite', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );
	}

	


	

	/**
	 * Init WooCommerce Product Tabs Lite extension once we know WooCommerce is active
	 */
	public function init() {

		// backend stuff
		add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'product_write_panel_tab' ) );
		add_action( 'woocommerce_product_data_panels',      array( $this, 'product_write_panel' ) );
		add_action( 'woocommerce_process_product_meta',     array( $this, 'product_save_data' ), 10, 2 );
		add_action('admin_menu',  array( $this,'mt_add_pages'));
		add_action('admin_footer', array( &$this,'ajax_script'));
		add_action( 'parent_file', array( &$this,'prefix_highlight_taxonomy_parent_menu') );
	
		
		
		add_action( 'admin_enqueue_scripts', array( $this,'load_custom_wp_admin_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this,'my_enqueue') );
		// frontend stuff
		add_filter( 'woocommerce_product_tabs', array( $this, 'add_custom_product_tabs' ) );

		// allow the use of shortcodes within the tab content
		add_filter( 'woocommerce_custom_product_tabs_lite_content', 'do_shortcode' );
		add_action( 'admin_head', array( &$this, 'admin_header' ) );  
		

// REGISTER COMPONENT_CATEGORIE TERM
		$labels = array(
			'name' => _x( 'Komponenten Kategorie', 'taxonomy general name' ),
			'singular_name' => _x( 'Komponente', 'taxonomy singular name' ),
			'search_items' =>  __( 'Komponenten Kategorie suchen' ),
			'all_items' => __( 'Alle Komponenten Kategorie' ),
			'parent_item' => __( 'Parent Komponenten Kategorie' ),
			'parent_item_colon' => __( 'Parent Komponenten Kategorie:' ),
			'edit_item' => __( 'Komponenten Kategorie bearbeiten' ), 
			'update_item' => __( ' Komponenten Kategorie Updaten' ),
			'add_new_item' => __( 'Neue Komponenten Kategorie' ),
			'new_item_name' => __( 'Neue Komponenten Kategorie' ),
			'menu_name' => __( 'Komponenten Kategorien' ),
		  );    
		 
		// Now register the taxonomy
		 
		  register_taxonomy('component_categorie',array('post'), array(
			'hierarchical' => true,
			'labels' => $labels,
			'sort' => true,
			'show_ui' => true,
			'show_admin_column' => false,
			'exclude_from_search' => true,
			'public' => false,
			'query_var' => true,
			'rewrite' => false
		  ));

		
	}
		
	function ajax_script(){
		?>
		<script type="text/javascript">
			(function($) {			
					$('.changestate').click(function(e){
						
						
					});
						
			})(jQuery);
		</script>

		<?php
		
	}	
		
function prefix_highlight_taxonomy_parent_menu( $parent_file ) {
	if ( get_current_screen()->taxonomy == 'component_categorie' ) {
		$parent_file = 'manage_components';
	}
	return $parent_file;
}	
		
	function admin_header() {

		echo '<style type="text/css">';
		echo '.wp-list-table .column-cb{ width: 3%; }';
		
		echo '.wp-list-table .column-components_categories_name{ width: 8%; }';
		echo '.wp-list-table .column-component_item_number{ width: 10%; }';
		echo '.wp-list-table .column-component_image{ width: 5%; }';
		echo '.wp-list-table .column-component_name { width: 35%; }';
		echo '.wp-list-table .column-component_status { width: 5%;}';
	
		echo '.wp-list-table .column-componentpurchasing_price { width: 20%; }';
		echo '.wp-list-table .column-componentretail_price { width: 20%;}';
		echo '.active{color:green}';
		echo '.inactive{color:red}';
		echo '</style>';
	}
	// action function for above hook
	public function mt_add_pages() {
		remove_submenu_page( 'edit.php', 'edit-tags.php?taxonomy=component_categorie' );
		// Add a new top-level menu (ill-advised):
		add_menu_page(__('Komponenten','menu-test'), __('Komponenten','menu-test'), 'manage_options', 'manage_components', array( &$this,'mt_toplevel_page') );

		// Add a submenu to the custom top-level menu:
		add_submenu_page('manage_components', __('Kategorien','menu-test'), __('Kategorien','menu-test'), 'manage_options', 'edit-tags.php?taxonomy=component_categorie');
		add_submenu_page('manage_components', __('Verknüpfungen','menu-test'), __('Verknüpfungen','menu-test'), 'manage_options', 'sub-page2', array( &$this,'mt_component_shortcut_page'));
		// Add a second submenu to the custom top-level menu:
		add_submenu_page('manage_components', __('Importieren','menu-test'), __('Test Sublevel 2','menu-test'), 'manage_options', 'sub-page3', array( &$this,'mt_import_page'));
	}
	
function parse_csv ($csv_string, $delimiter = ",", $skip_empty_lines = true, $trim_fields = true)
{
    return array_map(
        function ($line) use ($delimiter, $trim_fields) {
            return array_map(
                function ($field) {
                    return str_replace('!!Q!!', '"', utf8_decode(urldecode($field)));
                },
                $trim_fields ? array_map('trim', explode($delimiter, $line)) : explode($delimiter, $line)
            );
        },
        preg_split(
            $skip_empty_lines ? ($trim_fields ? '/( *\R)+/s' : '/\R+/s') : '/\R/s',
            preg_replace_callback(
                '/"(.*?)"/s',
                function ($field) {
                    return urlencode(utf8_encode($field[1]));
                },
                $enc = preg_replace('/(?<!")""/', '!!Q!!', $csv_string)
            )
        )
    );
}
function upload_image($url, $post_id, $name) {
    $image = "";
    if($url != "") {
     
        $file = array();
        $file['name'] = $name;
        $file['tmp_name'] = download_url($url);
 
        if (is_wp_error($file['tmp_name'])) {
            @unlink($file['tmp_name']);
            var_dump( $file['tmp_name']->get_error_messages( ) );
        } else {
            $attachmentId = media_handle_sideload($file, $post_id);
             
            if ( is_wp_error($attachmentId) ) {
                @unlink($file['tmp_name']);
                var_dump( $attachmentId->get_error_messages( ) );
            } else {                
                $image = wp_get_attachment_url( $attachmentId );
            }
        }
    }
    return $image;
}
	
	function mt_component_shortcut_page(){
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		$action = $_POST['action'];
		
		switch ($action){
			
			case 'display_shortcuts':
				$current_post_id = $_POST['product_id'];
				$post_meta_data = get_post_meta($current_post_id , 'components');

				break;
				
			case 'submit_shortcuts':
				$current_post_id = $_POST['product_id'];
/* 				foreach($_POST['components']['retail_price'] as $group_key => $retail_group_prices){
					foreach($retail_group_prices as $sub_group_key => $retail_group_price){
						#print_r($retail_group_price);
					
						if($sub_group_key == $_POST['components']['buildin'][$group_key]){
							
							$_POST['components']['retail_price'][$group_key][$sub_group_key] = 0;
						} else {
							
						}

					}
					
				}
				echo '<pre>';
				print_r($_POST['components']);
				echo '</pre>'; */
				delete_post_meta($current_post_id, 'components', '');
				add_post_meta($current_post_id , 'components',$_POST['components'] , true);
				$post_meta_data = get_post_meta($current_post_id , 'components');
				
				
				
				
				
				break;
				
			default:
				$current_post_id = '';
				break;
			
		}
		
		
		?>
		<div class="wrap">
			<h1>
				Verknüpfungen
				 <!-- Check edit permissions -->
				 <a href="<?php echo admin_url( 'admin.php?page=manage_components&action=add_new' ); ?>" class="page-title-action">
					<?php echo esc_html_x('Neue Komponente', 'my-plugin-slug'); ?>
				</a>
				<?php
				?>
			</h1>

		</div>
		<form name="my_form" method="post">
		<input type="hidden" name="action" value="display_shortcuts">
		<?php 
		$datas_obj = new WP_Query(array('post_type' => array('product', 'product_variation'), 'posts_per_page' => -1));
		$options = '';
		foreach($datas_obj as $do){	
			$selected = '';
			if(!empty($current_post_id)){
				$selected = ' selected="selected" ';
			}

			if(!empty($do->ID)){
				$options .= '<option '.$selected.' value="'.$do->ID.'">'.$do->post_title.'</option>';	
			}
			
		}
		echo '<select name="product_id">';
		echo $options;
		echo '</select>';
		submit_button( 'Los' );
		echo '</form>';
		?>
		<form name="submit_shortcuts" method="post">
		<?php submit_button( 'Speichern' ); ?>
		<input type="hidden" name="action" value="submit_shortcuts">
		<input type="hidden" name="product_id" value="<?php echo $current_post_id ?>">
		<?php

		
		if(!empty($current_post_id)){
			echo '<div class="postbox-container" style="width:100%;">';
				echo '<div class="postbox " >';		
						echo '<h2 class="hndle ui-sortable-handle">asdasd</h2>';
						echo '<div class="inside" >';
							global $wpdb;
							$_product = new WC_Product($current_post_id);
							$database_name = $wpdb->prefix.'components' ;

							
				$categories_terms = get_terms( array(
					'taxonomy' => 'component_categorie',
					'hide_empty' => false,
				) );
	
	
				foreach($categories_terms as $ct){
						$sql_stat = 'WHERE component_categorie = '.$ct->term_id;
						$query = "SELECT * FROM $database_name  $sql_stat ORDER BY component_sort DESC";			
						
						$datas =  $wpdb->get_results($query );	
						$count = $wpdb->num_rows;
						echo '<div>';
							if($ct->parent == 0){
								echo '<h1 style="background:rgba(21,21,21,.2);padding:.5rem;margin:0;">'.$ct->name.$ct->parent.'</h1>';
							} else {
								echo '<h3 style="background:rgba(21,21,21,.1);padding:.5rem;margin:0;">'.$ct->name.$ct->parent.'</h3>';
							}

				
						foreach($datas as $data){
							echo '<div style="padding:.2rem .5rem">';
							if($ct->parent == 0){
								$term_group_id = $ct->term_id;
							} else {
								$term_group_id = $ct->parent;
							}
							
							if(is_array($post_meta_data[0]['available'][$term_group_id])){
								if(in_array($data->component_id,$post_meta_data[0]['available'][$term_group_id]) == 1){
									$checked_ava = 'checked="checked"';
								} else {
									$checked_ava= '';
								}
							} else {
								$checked_ava = '';
							}
							
							
								if($data->component_id == $post_meta_data[0]['buildin'][$term_group_id]){
									$checked_build = 'checked="checked"';
								} else {
									$checked_build= '';
								}
						
							?>
							<table>
								<tr>
									<td>
										<input <?php echo $checked_ava ; ?> type="checkbox" name="components[available][<?php echo $term_group_id; ?>][]" value="<?php echo $data->component_id ?>" />
									</td>
									<td>
										<input <?php echo $checked_build ; ?> type="radio" name="components[buildin][<?php echo $term_group_id; ?>]" value="<?php echo $data->component_id; ?>" />
									</td>
									<td>
										<?php echo $data->component_name; ?>
									</td>
									<td>
										<!--<input type="text" name="components[retail_price][<?php echo $term_group_id; ?>][<?php echo $data->component_id; ?>]" value="<?php echo $data->component_retail_price; ?>" />
										<input type="hidden" name="components[purchasing_price][<?php echo $term_group_id; ?>][<?php echo $data->component_id; ?>]" value="<?php echo $data->component_purchasing_price; ?>" />-->
									</td>
								</tr>
							
							</table>
							
							
							
							<?php
						
							echo '</div>';
						}
						echo '</div>';
				}
							
							

							
							
						echo '</div>';
				echo '</div>';
			echo '</div>';
		}
		
		
		

		
	}
	
	
	
	function mt_import_page(){
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		global $wpdb;
		$database_name = 'components' ;
		$query = "SELECT categories_id FROM `categories` WHERE `parent_id` = 21";
		$new = array();
		$count = 0;
		$datas =  $wpdb->get_results($query, ARRAY_A );		
		foreach($datas as $data){
		
				$query1 = "SELECT * FROM products_to_categories as a, products as b, products_description as c WHERE a.categories_id = ".$data['categories_id']." AND a.products_id = b.products_id AND b.products_id = c.products_id";
				$datax =  $wpdb->get_results($query1, ARRAY_A );
				foreach($datax as $da){
			
						
$image_url = 'https://www.aletoware.com/images/product_images/original_images/'.$da['products_image'];
$this->upload_image($image_url,0 , $da['products_image']);


							
	array_push($new, array(
		'components_categories_name' => $da['categories_name'],
		'component_item_number' => $da['products_ean'] ,
		'component_name'  => $da['products_name'],
		'component_descripion' => htmlspecialchars($da['products_description']),
		'component_status' => $da['products_vpe_status'],
		'component_purchasing_price' => 0,
		'component_retail_price'   => 0,
		'component_image' => $da['products_image'],
		'component_added' => strtotime('now'),
		'component_modified' => strtotime('now')
	));
				
	
				$count++;	
				}

					
		
		}
		
		echo '<pre>';
		print_r($new);
		echo '</pre>';
		
	}
	
	// mt_toplevel_page() displays the page content for the custom Test Toplevel menu
	public function mt_toplevel_page() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
	  global $myListTable;
	  
 ?>

 <?php if(($_GET['action'] === 'edit') || $_GET['action'] === 'add_new'): ?>
 
<?php wp_enqueue_script('ap12', plugins_url( '/app.js' , __FILE__ )); ?>
		<div class="wrap">
		 	
			<h1>
			<a class="page-title-action" href="admin.php?page=manage_components" >Zurück</a>
			<?php 
			if($_GET['action'] === 'add_new'){
				esc_html_e('neue Komponente','domain'); 
			} else {
				esc_html_e('Komponente bearbeiten','domain'); 	
			}
			
			
			?>
			
			</h1> 
		 
			<?php 
				global $wpdb;
				$database_name = $wpdb->prefix.'components' ;
				$query = "SELECT * FROM $database_name WHERE component_id = ".$_GET['componentid'];
				$datas =  $wpdb->get_row($query, ARRAY_A );		
				$img = wp_get_attachment_image_src($datas['component_image'], 'thumbnail');
				if(empty($img)){
					$img = wp_get_attachment_image_src(48, 'thumbnail');
				}
				#print_r($datas);
				
				
				if($_POST['action'] === 'update_component'){
					
					
					if($_GET['action'] === 'add_new'){
						$update_ok = $wpdb->insert( 
							$database_name, 
							array( 
								'component_sort' => $_POST['component_sort'],		
								'component_status' => $_POST['component_status'] == 'on' ? true:false,	
								'component_out_of_stock' => $_POST['component_out_of_stock'] == 'on' ? true:false,		
								'component_categorie' => $_POST['component_categorie'],		
								'component_item_number' => $_POST['component_item_number'],	
								'component_image' => $_POST['component_image'],	
								'component_name' => $_POST['component_name'],	
								'component_descripion' => $_POST['component_descripion'],	
								'component_purchasing_price' => $_POST['component_purchasing_price'],	
								'component_retail_price' => $_POST['component_retail_price'],	
								'component_modified' => strtotime('now'),	
						
							), 
						
							array( 
								'%s',
								'%d',
								'%s',
								'%s',
								'%s',
								'%s',
								'%s',
								'%s',							
								'%f',
								'%f',
								'%s'							
							), 
							array( '%d' ) 
						);	
					} else {
						$update_ok = $wpdb->update( 
							$database_name, 
							array( 
								'component_sort' => $_POST['component_sort'],		
								'component_status' => $_POST['component_status'] == 'on' ? true:false,	
								'component_out_of_stock' => $_POST['component_out_of_stock'] == 'on' ? true:false,		
								'component_categorie' => $_POST['component_categorie'],		
								'component_item_number' => $_POST['component_item_number'],	
								'component_image' => $_POST['component_image'],	
								'component_name' => $_POST['component_name'],	
								'component_descripion' => $_POST['component_descripion'],	
								'component_purchasing_price' => $_POST['component_purchasing_price'],	
								'component_retail_price' => $_POST['component_retail_price'],	
								'component_modified' => strtotime('now'),	
						
							), 
							array( 'component_id' => $_POST['component_id'] ), 
							array( 
								'%s',
								'%d',
								'%s',
								'%s',
								'%s',
								'%s',
								'%s',
								'%s',							
								'%s',
								'%s',
								'%s'							
							), 
							array( '%d' ) 
						);
					}
					

					
					if($update_ok){
						#echo '<pre>';
						#echo $_POST;
						#print_r($_POST);
					#echo '</pre>';
						
					}
					wp_redirect( 'admin.php?page=manage_components' );
					
				}	
				
				
			?>
		 
			<form name="my_form" method="post">
				<input type="hidden" name="action" value="update_component">
				<input type="hidden" name="component_id" value="<?php echo $datas['component_id']; ?>">
				
				<?php wp_nonce_field( 'some-action-nonce' );

				$chk = '';
				
				$categories_terms = get_terms( array(
					'taxonomy' => 'component_categorie',
					'hide_empty' => false,
				) );
	
	
				foreach($categories_terms as $ct){
				
					if($ct->term_id == $datas['component_categorie']){
						$chk = ' checked="checked" ';
					} else {
						$chk = '';
					}
					if($ct->parent != 0){
						$has_child = 'style="margin-left:2rem;"';	
					} else {
						$has_child ='';
					}

					$component_categories .= '<li '.$has_child.'>';
					$component_categories .= '<input name="component_categorie" type="radio" '.$chk.' id="'.$ct->term_id.'" value="'.$ct->term_id.'" /><label for="'.$ct->term_id.'">'.$ct->name.'</label>';
					$component_categories .= '</li>';
					if($ct->term_parent !== 0){
							
					}
				}
		 
		 
		 
				/* Used to save closed meta boxes and their order */
				wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
				wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
		 
				<div id="poststuff">
		 
					<div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>">
		 
						<div id="post-body-content">
							<div id="titlediv">
								<div id="titlewrap">
									
									<label class="screen-reader-text" id="title-prompt-text" for="title">Titel hier eingeben</label>
									<input type="text" name="component_name" size="30" value="<?php echo $datas['component_name']; ?>" id="title" spellcheck="true" autocomplete="off">
								</div>
							</div>
							
							<table>
								<tr valign="top">
									<td>
									<?php wp_enqueue_media(); ?>
										<div class='image-preview-wrapper' style="margin:1.3rem 0; ">
											<img id='image-preview' src='<?php echo $img[0]; ?>' width='<?php echo $img[1]; ?>' height='<?php echo $img[2]; ?>' >
										</div>
										<input id="upload_image_button" type="button" class="button" value="<?php _e( 'Upload image' ); ?>" />
										<input type='hidden' name='component_image' id='component_image' value='<?php echo $datas['component_image']; ?>'>
										
										</div>
									

										
									</td>
									<td>
										<div class="postbox ">
											<h2>Auszug</h2>
											<hr />
											<div class="inside">
											<!--<label for="component_categorie">Kategorie</label><br/>
											<select id="component_categorie" name="component_categorie" ><?php echo $component_categories; ?></select>-->
												<ul>
												<?php echo $component_categories; ?>
												</ul>
											</div>
										</div>
										<div>
											<label for="component_item_number">Artikelnummer</label><br/>
											<input type="text" id="component_item_number" name="component_item_number" value="<?php echo $datas['component_item_number']; ?>" />
										</div>								
										
										<div>
											<label for="component_sort">Sortierung</label><br/>
											<input type="text" id="component_sort" name="component_sort" value="<?php echo $datas['component_sort']; ?>" />
										</div>
										<div>
											<label for="component_purchasing_price">Einkaufspreis</label><br/>
											<input autocomplete="off" type="text" id="component_purchasing_price" name="component_purchasing_price" value="<?php echo $datas['component_purchasing_price']; ?>" />
										</div>
										<div>
											<label for="component_retail_price">Verkaufspreis</label><br/>
											<input autocomplete="off" type="text" id="component_retail_price" name="component_retail_price" value="<?php echo $datas['component_retail_price']; ?>" />
										</div>
									</td>

								</tr>
					
							</table>
							
							<?php 
							$settings = array( 
									'quicktags' => array( 
									'buttons' => 'strong,em,del,ul,ol,li,close'
								), 
								'media_buttons' => false,
								'editor_height' => 100,
								'textarea_name' => 'component_descripion'
							);
							$content = $datas['component_descripion'];
							$editor_id = 'editpost';

							wp_editor( $content, $editor_id, $settings );
							 
							?>
							<br />
							<div class="postbox-container">
								<label class="selectit" for="component_status">
									<input type="checkbox" id="component_status" name="component_status" <?php checked($datas['component_status']); ?> /> Status (Ein/Ausschalten)
								</label>
								
								<label class="selectit" for="component_out_of_stock">
									<input type="checkbox" id="component_out_of_stock" name="component_out_of_stock" <?php checked($datas['component_out_of_stock']); ?> /> Nicht Lieferbar
								</label>
							</div>
						
							
							
							<br />
							
							<?php 
							if($_GET['action'] === 'add_new'){
							submit_button( 'Hinzufügen','primary' );	
							} else {
							submit_button( 'Aktualisieren','primary' );	
							}
							

							?>
							
							
						<div id="postbox-container-1" class="postbox-container">
							<?php do_meta_boxes('','side',null); ?>
						</div>
		 
						<div id="postbox-container-2" class="postbox-container">
							<?php do_meta_boxes('','normal',null); ?>
							<?php do_meta_boxes('','advanced',null); ?>
						</div>
		 
					</div> <!-- #post-body -->
		 
				</div> <!-- #poststuff -->
		 
			</form>
		 
		</div><!-- .wrap -->
<?php else: ?>
 
    <div class="wrap">
        <h1>
            Komponenten
             <!-- Check edit permissions -->
             <a href="<?php echo admin_url( 'admin.php?page=manage_components&action=add_new' ); ?>" class="page-title-action">
                <?php echo esc_html_x('Neue Komponente', 'my-plugin-slug'); ?>
            </a>
            <?php
            ?>
        </h1>

    </div>
    <?php 

	  $myListTable = new My_List_Table();	
	  $myListTable->views(); 
	  $myListTable->prepare_items(); 
	  
	  if($_GET['action'] === 'changestate'){
		global $wpdb;
		$database_name = $wpdb->prefix.'components' ;
		
		$query = "SELECT component_status from $database_name WHERE component_id = ".$_GET['componentid'];
		$current_state =  $wpdb->get_var($query );	

		if($current_state == 0){
			$newstate = 1;
		} else {
			$newstate = 0;	
		}
			
			
			$wpdb->update( 
				$database_name, 
				array( 
					'component_status' => $newstate,	// string
					
				), 
				array( 'component_id' => $_GET['componentid'] ), 
				array( 
					'%s',	// value1
					'%d'	// value2
				), 
				array( '%d' ) 
			);	  
		  $myListTable->prepare_items(); 
		  
	  } else if($_GET['action'] === 'outofstock'){
		global $wpdb;
		$database_name = $wpdb->prefix.'components' ;
		
		$query = "SELECT component_out_of_stock from $database_name WHERE component_id = ".$_GET['componentid'];
		$current_state =  $wpdb->get_var($query );	

		if($current_state == 0){
			$newstate = 1;
		} else {
			$newstate = 0;	
		}
			
			
			$wpdb->update( 
				$database_name, 
				array( 
					'component_out_of_stock' => $newstate,	// string
					
				), 
				array( 'component_id' => $_GET['componentid'] ), 
				array( 
					'%s',	// value1
					'%d'	// value2
				), 
				array( '%d' ) 
			);	  
		$myListTable->prepare_items();  
	  } else if($_GET['action'] === 'delete'){
		  global $wpdb;
		  $database_name = $wpdb->prefix.'components' ;
		  $wpdb->delete( $database_name, array( 'component_id' => $_GET['componentid'] ) );
		  $myListTable->prepare_items();  
	  }
	  
		?>
	  <form method="post">
		<input type="hidden" name="page" value="ttest_list_table">
		<?php
		$myListTable->search_box( 'search', 'search_id' );
	  $myListTable->display(); 
	  echo '</form></div>'; 	
			
endif;	
	}

	public function load_custom_wp_admin_style() {
        #wp_register_style( 'custom_wp_admin_css', plugins_url( '/skins/polaris/polaris.css' , __FILE__ ), false, '1.0.0' );
        wp_enqueue_style( 'custom', 'custom_wp_admin_css' );
		wp_enqueue_style( 'faw','https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' );
		
	}
	
	function my_enqueue($hook) {
		#wp_enqueue_script('my_custom_script', 'https://cdnjs.cloudflare.com/ajax/libs/iCheck/1.0.2/icheck.min.js');
		
	}


	
	

	/** Frontend methods ******************************************************/


	/**
	 * Add the custom product tab
	 *
	 * $tabs structure:
	 * Array(
	 *   id => Array(
	 *     'title'    => (string) Tab title,
	 *     'priority' => (string) Tab priority,
	 *     'callback' => (mixed) callback function,
	 *   )
	 * )
	 *
	 * @since 1.2.0
	 * @param array $tabs array representing the product tabs
	 * @return array representing the product tabs
	 */
	public function add_custom_product_tabs( $tabs ) {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return $tabs;
		}

		if ( $this->product_has_custom_tabs( $product ) ) {

			foreach ( $this->tab_data as $tab ) {
				$tab_title = __( $tab['title'], 'woocommerce-custom-product-tabs-lite' );
				$tabs[ $tab['id'] ] = array(
					'title'    => apply_filters( 'woocommerce_custom_product_tabs_lite_title', $tab_title, $product, $this ),
					'priority' => 25,
					'callback' => array( $this, 'custom_product_tabs_panel_content' ),
					'content'  => $tab['content'],  // custom field
				);
			}
		}

		return $tabs;
	}


	/**
	 * Render the custom product tab panel content for the given $tab
	 *
	 * $tab structure:
	 * Array(
	 *   'title'    => (string) Tab title,
	 *   'priority' => (string) Tab priority,
	 *   'callback' => (mixed) callback function,
	 *   'id'       => (int) tab post identifier,
	 *   'content'  => (sring) tab content,
	 * )
	 *
	 * @param string $key tab key
	 * @param array $tab tab data
	 *
	 * @param array $tab the tab
	 */
	public function custom_product_tabs_panel_content( $key, $tab ) {

		// allow shortcodes to function
		$content = apply_filters( 'the_content', $tab['content'] );
		$content = str_replace( ']]>', ']]&gt;', $content );

		echo apply_filters( 'woocommerce_custom_product_tabs_lite_heading', '<h2>' . $tab['title'] . '</h2>', $tab );
		echo apply_filters( 'woocommerce_custom_product_tabs_lite_content', $content, $tab );
	}


	/** Admin methods ******************************************************/


	/**
	 * Adds a new tab to the Product Data postbox in the admin product interface.
	 *
	 * @since 1.0.0
	 */
	public function product_write_panel_tab() {
		echo "<li class=\"product_tabs_lite_tab\"><a href=\"#PCBuilder\"><span>Komponenten</span></a></li>";
	}


	/**
	 * Adds the panel to the Product Data postbox in the product interface
	 *
	 * TODO: We likely want to migrate getting meta to a product CRUD method post WC 3.1 {BR 2017-03-21}
	 *
	 * @since 1.0.0
	 */
	public function product_write_panel() {
		global $post; // the product
		global $wpdb;
		// pull the custom tab data out of the database
		$tab_data = maybe_unserialize( get_post_meta( $post->ID, 'frs_woo_product_tabs', true ) );

		if ( empty( $tab_data ) ) {

			// start with an array for PHP 7.1+
			$tab_data = array();


		}
		
		$results = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'components_categories ORDER BY component_categorie_sort ASC');

		foreach ( $tab_data as $tab ) {
			// display the custom tab panel

			
			echo '<div id="PCBuilder" class="panel wc-metaboxes-wrapper woocommerce_options_panel">';
				echo '<div style="padding:1rem;">';
				
				echo '<pre>';
				print_r($tab_data);
			
			echo '</pre>';
				
				echo '<span style="padding-left:140px;">Verfügbar</span><span style="padding-left:20px;">Eingebaut</span>';
				echo '<tabel>';
					
				$categories_terms = get_terms( array(
					'taxonomy' => 'component_categorie',
					'hide_empty' => false,
				) );
					
				foreach($categories_terms as $ct){
					if($ct->parent == 0){
					echo '<h4 style="background:rgba(21,21,21,.2);">';
						echo $ct->name;
					echo '</h4>';
					}
					
					$component_results = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'components WHERE component_categorie = '. $ct->term_id .' ORDER BY component_sort ASC', OBJECT);
					if($ct->parent == 0){
					?>
					<style>
					.item{
						padding:.5rem;

					.item.buildin{
						background:rgba(21,21,21,.2);
					}
					</style>
					
					<?php
					}
					foreach($component_results as $key => $component_result){
							if(isset($tab_data['buildin'][ $ct->term_id])){		
									if($tab_data['buildin'][ $ct->term_id] == $component_result->component_id){
										echo '<div class="item buildin">';	
									} else {
										echo '<div class="item">';			
									}
							} else {
								echo '<div class="item">';	
							}
						
						?>

							<input 
							<?php
							
								if(isset($tab_data['available'][ $ct->term_id])){
									foreach($tab_data['available'][ $ct->term_id] as $checker){
										if($checker === $component_result->component_id){
												echo ' checked ';
										}
									}
								}
							?>
							
							type="checkbox" name="components[available][<?php echo $ct->term_id; ?>][]" value="<?php echo $component_result->component_id; ?>" />
						
						<?php
							echo '<span>'. $component_result->component_name.'</span>';
							if(isset($tab_data['buildin'][ $ct->term_id])){		
									if($tab_data['buildin'][ $ct->term_id] == $component_result->component_id){
										echo '</div>';
									} else {
										echo '</div>';	
									}
							} else {
								echo '</div>';	
							}
						
					}
				
					
				}


					

					
					foreach($results as $result){
						
							echo '<h4 style="background:rgba(21,21,21,.2);">';
								echo $result->components_categories_name;
							echo '</h4>';
							
							
					
							
							
								$component_results = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'components WHERE component_categorie = '. $result->components_categories_id .' ORDER BY component_sort ASC');

								$args = array(
									'id' 		=> 'components[defaults]['.$result->components_categories_id.'][]',
									'label'		=> sanitize_text_field( 'Product IDs' ),
									'value' 	=> $tab_data['defaults'][$result->components_categories_id][0]
								);
								woocommerce_wp_hidden_input( $args );		

								foreach($component_results as $key => $component_result){
								
										echo '<div>';

											?>
																					
												<?php if($tab_data['defaults'][$result->components_categories_id][0] === $component_result->component_id){ ?>
													<button>Ausbauen</button>
													<?php } else { ?>
													<button>Einbauen</button>
													<?php }  ?>	
												
												<?php echo $component_result->component_name; ?>
												
												
						
													

							
										
											<?php
										
										echo '</div>';
										
								}
							
						
					}
					
				echo '</div>';
			echo '</div>';
		}

	}


	/**
	 * Saves the data input into the product boxes, as post meta data
	 * identified by the name 'frs_woo_product_tabs'
	 *
	 * TODO: We likely want to migrate getting / setting meta to a product CRUD method post WC 3.1 {BR 2017-03-21}
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id the post (product) identifier
	 * @param stdClass $post the post (product)
	 */
	public function product_save_data( $post_id, $post ) {

/*    echo '<pre>';
		print_r($_POST);
echo '</pre>';	 	
	exit;   */
			
			delete_post_meta($post_id,  'frs_woo_product_tabs');
		 
			update_post_meta( $post_id, 'frs_woo_product_tabs', $_POST['components'] );
		
	}


	/**
	 * Helper function to generate a text area input for the custom tab
	 *
	 * TODO: We likely want to migrate getting meta to a product CRUD method post WC 3.1 {BR 2017-03-21}
	 *
	 * @since 1.0.0
	 * @param array $field the field data
	 */
	private function woocommerce_wp_textarea_input( $field ) {
		global $thepostid, $post;

		$thepostid            = ! $thepostid                   ? $post->ID             : $thepostid;
		$field['placeholder'] = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
		$field['class']       = isset( $field['class'] )       ? $field['class']       : 'short';
		$field['value']       = isset( $field['value'] )       ? $field['value']       : get_post_meta( $thepostid, $field['id'], true );

		echo '<p class="form-field ' . $field['id'] . '_field"><label style="display:block;" for="' . $field['id'] . '">' . $field['label'] . '</label><textarea class="' . $field['class'] . '" name="' . $field['id'] . '" id="' . $field['id'] . '" placeholder="' . $field['placeholder'] . '" rows="2" cols="20"' . (isset( $field['style'] ) ? ' style="' . $field['style'] . '"' : '') . '>' . esc_textarea( $field['value'] ) . '</textarea> ';

		if ( isset( $field['description'] ) && $field['description'] ) {
			echo '<span class="description">' . $field['description'] . '</span>';
		}

		echo '</p>';
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Custom Product Tabs Lite Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.4.0
	 * @see wc_custom_product_tabs_lite()
	 * @return PCBuilder
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Lazy-load the product_tabs meta data, and return true if it exists,
	 * false otherwise
	 *
	 * TODO: We likely want to migrate getting meta to a product CRUD method post WC 3.1 {BR 2017-03-21}
	 *
	 * @param \WC_Product $product the product object
	 * @return true if there is custom tab data, false otherwise
	 */
	private function product_has_custom_tabs( $product ) {

		if ( false === $this->tab_data ) {
			$this->tab_data = maybe_unserialize( get_post_meta( $product->get_id(), 'frs_woo_product_tabs', true ) );
		}

		// tab must at least have a title to exist
		return ! empty( $this->tab_data ) && ! empty( $this->tab_data[0] ) && ! empty( $this->tab_data[0]['title'] );
	}


	/**
	 * Checks if WooCommerce is active
	 *
	 * @since  1.0.0
	 * @return bool true if WooCommerce is active, false otherwise
	 */
	public static function is_woocommerce_active() {

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}


	/**
	 * Renders a notice when WooCommerce is inactive or version is outdated.
	 *
	 * @since 1.6.0
	 */
	public static function render_woocommerce_requirements_notice() {

		$message = sprintf(
			/* translators: Placeholders: %1$s - <strong>, %2$s - </strong>, %3$s + %5$s - <a> tags, %4$s - </a> */
			esc_html__( '%1$sWooCommerce Custom Product Tabs Lite is inactive.%2$s This plugin requires WooCommerce 2.5.5 or newer. Please %3$sinstall WooCommerce 2.5.5 or newer%4$s, or %5$srun the WooCommerce database upgrade%4$s.', 'woocommerce-custom-product-tabs-lite' ),
			'<strong>',
			'</strong>',
			'<a href="' . admin_url( 'plugins.php' ) . '">',
			'</a>',
			'<a href="' . admin_url( 'plugins.php?do_update_woocommerce=true' ) . '">'
		);

		printf( '<div class="error"><p>%s</p></div>', $message );
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Run every time.  Used since the activation hook is not executed when updating a plugin
	 *
	 * @since 1.0.0
	 */
	private function install() {

		$installed_version = get_option( self::VERSION_OPTION_NAME );

		// installed version lower than plugin version?
		if ( -1 === version_compare( $installed_version, self::VERSION ) ) {
			// new version number
			update_option( self::VERSION_OPTION_NAME, self::VERSION );
		}
	}

}


/**
 * Returns the One True Instance of Custom Product Tabs Lite
 *
 * @since 1.4.0
 * @return \PCBuilder
 */
function wc_custom_product_tabs_lite() {
	return PCBuilder::instance();
}


/**
 * The PCBuilder global object
 *
 * TODO: Remove the global with WC 3.1 compat {BR 2017-03-21}
 *
 * @deprecated 1.4.0
 * @name $PCBuilder
 * @global PCBuilder $GLOBALS['PCBuilder']
 */
$GLOBALS['PCBuilder'] = wc_custom_product_tabs_lite();
