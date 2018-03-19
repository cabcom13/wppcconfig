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

defined('ABSPATH') or exit;

// Check if WooCommerce is active & at least v2.5.5, and bail if it's not
if (!PCBuilder::is_woocommerce_active() || version_compare(get_option('woocommerce_db_version'), '2.5.5', '<')) {
    add_action('admin_notices', array('PCBuilder', 'render_woocommerce_requirements_notice'));
    return;
}

// INCLUDE COMPONENT MANAGER LIST TABLE CLASS
require_once 'component_manager.class.php';
// INCLUDE COMPONENT SHORTCUT LIST TABLE CLASS
require_once 'component_shortcut.class.php';

// INCLUDE COMPONENT SHORTCUT LIST TABLE CLASS
require_once 'func/calc_cart_price.php';

add_action('plugins_loaded', 'wpse_setup_theme');

function wpse_setup_theme()
{
    add_action('wp_ajax_nopriv_change_pc_configuration', 'change_pc_configuration');
    add_action('wp_ajax_nopriv_get_component_detail', 'get_component_detail');
    add_action('wp_ajax_get_component_detail', 'get_component_detail');
    add_action('wp_ajax_save_configuration', 'save_configuration');

    add_action('wp_ajax_change_pc_configuration', 'change_pc_configuration');
    add_action('wp_enqueue_scripts', 'ajax_pc_config_frontend');
    add_filter('wp_nav_menu_items', 'sk_wcmenucart', 10, 2);

    // all actions related to emojis
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');

    // filter to remove TinyMCE emojis
    add_filter('tiny_mce_plugins', 'disable_emojicons_tinymce');

}
function random_string($length)
{
    $key = '';
    $keys = array_merge(range(0, 9), range('a', 'z'));

    for ($i = 0; $i < $length; $i++) {
        $key .= $keys[array_rand($keys)];
    }

    return $key;
}

function save_configuration()
{
    if (defined('DOING_AJAX') && DOING_AJAX) {
        global $wpdb;
        parse_str($_POST['name'], $params1);
        parse_str($_POST['data'], $params);

        unset($params['action']);
        $params['name'] = $params1['configuration_name'];

        $database_name = $wpdb->prefix . 'saved_configs';

        $data_array = array(
            'pid' => $params['product_id'],
            'name' => $params['name'],
            'components' => json_encode($params['components']['selected']),
            'ukey' => substr(md5($params['name']), 0, 2) . $params['product_id'] . random_string(5),
        );
        $in = $wpdb->insert(
            $database_name,
            $data_array,
            array(
                '%d',
                '%s',
                '%s',
                '%s',
            )
        );

        if ($in) {

            setcookie("aw_configs[" . $data_array['ukey'] . "]", json_encode($params['components']['selected']), strtotime("1 Month"), '/awoo/');

            echo json_encode($data_array);
        } else {
            echo json_encode(array('status' => 'error'));
        }

        exit();
    }
}

function get_component_detail()
{
    if (defined('DOING_AJAX') && DOING_AJAX) {
        global $wpdb;
        $params = array();
        $ret = array();
        parse_str($_POST['data'], $params);

        $database_name = $wpdb->prefix . 'components';
        $sql_stat = ' WHERE component_id = ' . $_POST['data'];
        $query = "SELECT component_id, component_name,component_descripion, component_categorie, component_image, component_more_images FROM $database_name $sql_stat";
        $res = $wpdb->get_row($query);

        $gallery = array();

        foreach (json_decode($res->component_more_images) as $mimg) {
            $more_img_src = wp_get_attachment_image_src($mimg, array(500, 500));
            array_push($gallery, $more_img_src[0]);
        }

        $img_src = wp_get_attachment_image_src($res->component_image, array(500, 500));
        if ($img_src) {

            if (is_array($img_src)) {
                $img = $img_src[0];
            }
        } else {
            $img = get_template_directory_uri() . '/img/noimg.png';
        }
        array_unshift($gallery, $img);

        $ret = array(
            'id' => $res->component_id,
            'name' => $res->component_name,
            'component_descripion' => $res->component_descripion,
            'image' => $img,
            'gallery' => $gallery,

        );

        echo json_encode($ret);
        exit();
    }
}

function sk_wcmenucart($menu, $args)
{

    // Check if WooCommerce is active and add a new item to a menu assigned to Primary Navigation Menu location
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) || 'user' !== $args->theme_location) {
        return $menu;
    }

    ob_start();
    global $woocommerce;
    $viewing_cart = __('View your shopping cart', 'your-theme-slug');
    $start_shopping = __('Start shopping', 'your-theme-slug');
    $cart_url = $woocommerce->cart->get_cart_url();
    $shop_page_url = get_permalink(woocommerce_get_page_id('shop'));
    $cart_contents_count = $woocommerce->cart->cart_contents_count;

    if ($cart_contents_count == 0) {
        $cl = '';
    } else {
        $cl = ' badge-danger ';

    }
    $cart_contents = sprintf(_n('<span class="badge badge-pill ' . $cl . '">%d</span> Warenkorb', '<span class="badge badge-pill ' . $cl . '">%d</span> Warenkorb', $cart_contents_count, 'your-theme-slug'), $cart_contents_count);

    $cart_total = $woocommerce->cart->get_total();
    #print_r($woocommerce);
    // Uncomment the line below to hide nav menu cart item when there are no items in the cart
    // if ( $cart_contents_count > 0 ) {
    if ($cart_contents_count == 0) {
        $menu_item = '<li><a class="nav-link" href="' . $shop_page_url . '" title="' . $start_shopping . '">';
    } else {
        $menu_item = '<li><a class="nav-link" href="' . $cart_url . '" title="' . $viewing_cart . '">';
    }

    $menu_item .= $cart_contents;
    $menu_item .= '</a></li>';
    // Uncomment the line below to hide nav menu cart item when there are no items in the cart
    // }
    echo $menu_item;
    $social = ob_get_clean();
    return $menu . $social;

}
function ajax_pc_config_frontend()
{

    wp_enqueue_script('pc_config', plugins_url('/js/configurator.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_script('sticky', plugins_url('/js/jquery.sticky-kit.min.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_script('overlay_loading', 'https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@1.5.4/src/loadingoverlay.min.js', array('jquery'), '1.0', true);
    wp_enqueue_script('owl_carousel', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.2.1/owl.carousel.min.js', array('jquery'), '1.0', true);

    wp_enqueue_style('owl-css', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.2.1/assets/owl.carousel.min.css');
    wp_enqueue_style('owl-theme-css', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.2.1/assets/owl.theme.default.min.css');

    wp_localize_script('pc_config', 'pc_config', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));

}

function intro_shortcode($atts, $content = null)
{
    global $product;
    if (!isset($atts['count'])) {
        $atts['count'] = 2;
    }

    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'orderby' => 'rand',
        'ignore_sticky_posts' => 1,
        'posts_per_page' => $atts['count'],
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'terms' => $atts['categorie'],
                'operator' => 'IN',
            ),
        ),
    );
    $products = new WP_Query($args);
    $html = '';
    if (is_object($products)) {

        $term = get_term($atts['categorie'], 'product_cat');
        if (is_object($term)) {
            $html .= '<div class="row mt-5">';
            $html .= '<div class="col">';
            $html .= '<h1>';
            $html .= $term->name;
            $html .= '</h1>';
            $html .= '</div>';

            $html .= '<div class="col text-right my-2">';
            $html .= '<a href="' . esc_url(get_term_link($term)) . '" title="mehr Produkte der Kategorie Gaming PCs">weitere PCs dieser Kategorie</a>';

            $html .= '</div>';

            $html .= '</div>';
        }
        $html .= '<div class="card-group">';

        while ($products->have_posts()): $products->the_post();
            global $product;
            $html .= '<div class="card" >';
            $img_src = wp_get_attachment_image_src($product->get_image_id(), array(500, 500));
            if ($img_src) {
                if (is_array($img_src)) {
                    $img = $img_src[0];
                }
            } else {
                $img = get_template_directory_uri() . '/img/noimg.png';
            }
            $html .= '<img class="card-img-top" src="' . $img . '" alt="' . $product->get_name() . '" />';

            $html .= '<div class="card-body text-center"">';
            $html .= '<div style="min-height:60px;">';
            $html .= '<span itemprop="name">';
            $html .= '<a class="card-title" title="zum Produkt ' . $product->get_name() . ' springen"  href="' . get_permalink($product->get_id()) . '">';
            $html .= $product->get_name();
            $html .= '</a>';
            $html .= '</span>';
            $html .= '</div>';
            $html .= '<p class="card-text my-3">';
            #$html .='<ul class="list-group">';
            #    $html .=get_short_description($product->get_id());
            #$html .='</ul>';
            $html .= 'ab ' . woocommerce_price($product->get_price_including_tax()) . ' *';
            $html .= '</p>';
            $html .= '<a class="btn btn-success" href="' . esc_url(add_query_arg('konfigurieren', '', get_permalink($product->get_id()))) . '">Details anzeigen</a>';
            $html .= '<div class="mt-3">';
            $html .= '<small>';
            $tax_rates = WC_Tax::get_rates($product->get_tax_class());
            $html .= '* je nach Konfiguration zzg. Versandkosten (inkl. ' . round($tax_rates[35]['rate'], wc_get_price_decimals()) . '% ' . $tax_rates[35]['label'] . ')';
            $html .= '</small>';
            $html .= '</div>';

            $html .= '</div>';
            $html .= '</div>';

        endwhile;

        wp_reset_query();

        $html .= '</div>';

    }
    return $html;
}
add_shortcode('intro', 'intro_shortcode');

function get_short_description($product_id)
{
    global $wpdb;
    $database_name = $wpdb->prefix . 'components';
    $ava = get_post_meta($product_id, 'components');
    $html = '';
    $includes = array(22, 24);
    if (isset($ava[0]['buildin'])) {
        foreach ($ava[0]['buildin'] as $component) {

            $sql_stat = 'WHERE component_id = ' . $component . ' AND component_name != "nichts Ausgewählt" AND component_name != " "';
            $query = "SELECT component_name, component_categorie FROM $database_name $sql_stat";
            $res = $wpdb->get_row($query);
            if (!empty($res->component_name)) {
                $child = get_term($res->component_categorie, 'component_categorie');
                if (in_array($child->parent, $includes)) {
                    $html .= '<li>' . $res->component_name . '</li>';
                }
            }
        }
    } else {
        $html = 'Keine Optionen vorhanden';
    }

    return $html;
}

function build_configurator($product_compos, $group_id, $index = 0)
{

    $terms = get_terms('component_categorie', array(
        "orderby" => "term_order",
        "hide_empty" => false,
    )
    );

    echo '<div id="accordion_' . $group_id . '" role="tablist">';

    foreach ($terms as $term) {

        if ($term->parent) {
            continue;
        }

        $pc = $product_compos['components'][$term->term_id];

        $term_data = get_option('taxonomy_' . $pc['cat_data']->term_id);

        if ($group_id == $term_data['component_group']) {
            ?>
						<div class="card">
						<div class="card-header" role="tab" id="heading_<?php echo $pc['cat_data']->term_id; ?>">
							<h5 class="mb-0">
								<a data-toggle="collapse" href="#collapse_<?php echo $pc['cat_data']->term_id; ?>" aria-controls="collapse_<?php echo $pc['cat_data']->term_id; ?>"><?php echo $pc['cat_data']->name; ?></a>
								<?php
if ($pc['selected_component_name'] == 'nichts Ausgewählt') {
                $style = 'nothing_selected';
            } else {
                $style = '';
            }
            ?>
								<span class="selected_name text-center <?php echo $style; ?>"><?php echo $pc['selected_component_name']; ?></span>
							</h5>
						</div>
						<div id="collapse_<?php echo $pc['cat_data']->term_id; ?>" class="collapse <?php echo $index == 0 ? ' show ' : '' ?>" role="tabpanel" data-parent="#<?php echo 'accordion_' . $group_id ?>">
							<div class="card-body">
							<?php if (z_taxonomy_image_url($pc['cat_data']->term_id)): ?>
							<img class="my-2" src="<?php echo z_taxonomy_image_url($pc['cat_data']->term_id); ?>" />
							<?php endif;?>
							<?php if (!empty($pc['cat_data']->description)): ?>
							<small class="d-block mb-5"><?php echo $pc['cat_data']->description; ?></small>
							<?php endif;?>
							<?php

            switch ($term_data['grid_style']) {

                case 'list':
                    $group_style = 'list';
                    break;

                case 'grid':
                    $group_style = 'grid';
                    break;

                default:
                    $group_style = 'list';
                    break;
            }

            $r = get_terms('component_categorie', array(
                'parent' => $term->term_id,
                "orderby" => "term_order",
                "hide_empty" => false,
            ));
            array_unshift($r, $term);

            if ($group_style == 'list') {
                echo '<div class="row ">';
                echo '<div class="col col-2 ">';
                echo '<img  class="img-thumbnail component_image config_thumbnail_' . $pc['cat_data']->term_id . '" src="' . $pc['selected_image'][0] . '" />';
                echo '</div>';
                echo '<div class="col">';
                $last = 0;
                #array_reverse($pc['data']);
                foreach ($pc['data'] as $co) {
                    echo '<div class="container">';
                    if ($co['is_selected']) {
                        $selected = 'checked="checked"';
                        $overview_html .= '<strong>' . $pc['cat_data']->name . '</strong>';
                        $overview_html .= '<div>' . $co['component_name'] . '</div>';
                    } else {
                        $selected = '';
                    }
                    $subterm = get_term($co['cat-id'], 'component_categorie');

                    if (($co['cat-id'] != $last) && ($co['cat-id'] != $pc['cat_data']->term_id)) {
                        echo '<div class="row my-3"><strong>' . $subterm->name . '</strong>';

                        echo '</div>';
                    }
                    echo '<div class="row" style="border-bottom:1px solid rgba(21,21,21,.1);">';
                    echo '<div class="col col-8 ">';
                    echo '<div class="form-check">';
                    echo '<label class="form-check-label mt-2">';

                    echo '<input class="option-input radio" ' . $selected . '  type="radio" id="components[selected][' . $pc['cat_data']->term_id . ']" name="components[selected][' . $pc['cat_data']->term_id . ']" value="' . $co['id'] . '" />';
                    echo ' ' . $co['component_name'];
                    #echo $co['component_retail_count'];
                    if ($co['component_retail_count'] >= 10) {
                        echo ' <span class="ml-3 badge badge-warning">Bestseller</span>';
                    }
                    $datetime1 = new DateTime($co['compontent_added']);
                    $datetime2 = new DateTime(date('Y-m-d H:i:s', strtotime("now")));
                    $interval = $datetime1->diff($datetime2);

                    if ($interval->format('%a') <= 10) {
                        echo ' <span class="ml-3 badge badge-success">Neu</span>';
                    }

                    echo '</label>';
                    echo '</div>';
                    echo '</div>';
                    echo '<div class="col">';
                    if ($co['component_name'] != 'nichts Ausgewählt') {
                        echo '<a href="" data-component-id="' . $co['id'] . '" class="show_component_details btn mt-1 btn-sm btn-secondary" >Details</a>';
                    }
                    echo '</div>';
                    echo '<div class="col text-right">';
                    echo '<strong class=" mt-1 d-block price_' . $pc['cat'] . '_' . $co['id'] . '">' . $co['prefix'] . $co['price']['formated'] . '</strong>';
                    echo '</div>';
                    echo '</div>';

                    echo '</div>';
                    $last = $co['cat-id'];
                }
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="row ">';
                echo '<div class="col col-2 ">';
                echo '<img class="img-thumbnail component_image config_thumbnail_' . $pc['cat_data']->term_id . '" src="' . $pc['selected_image'][0] . '" />';
                echo '</div>';
                echo '<div class="col">';
                $last = 0;

                foreach ($pc['data'] as $co) {

                    echo '<div class="container">';
                    if ($co['is_selected']) {
                        $selected = 'checked="checked"';
                        $overview_html .= '<strong>' . $pc['cat_data']->name . '</strong>';
                        $overview_html .= '<div>' . $co['component_name'] . '</div>';
                    } else {
                        $selected = '';
                    }
                    $subterm = get_term($co['cat-id'], 'component_categorie');

                    if (($co['cat-id'] != $last) && ($co['cat-id'] != $pc['cat_data']->term_id)) {
                        echo '<div class="row my-3"><strong>' . $subterm->name . '</strong></div>';
                    }
                    echo '<div class="row" style="border-bottom:1px solid rgba(21,21,21,.1);">';
                    echo '<div class="col col-6 ">';
                    echo '<div class="form-check">';
                    echo '<label class="form-check-label mt-2">';
                    echo '<input class="option-input radio" ' . $selected . '  type="radio" id="components[selected][' . $pc['cat_data']->term_id . ']" name="components[selected][' . $pc['cat_data']->term_id . ']" value="' . $co['id'] . '" />';
                    echo ' ' . $co['component_name'];
                    if ($co['component_retail_count'] >= 10) {
                        echo ' <span class="ml-3 badge badge-warning">Bestseller</span>';
                    }
                    $datetime1 = new DateTime($co['compontent_added']);

                    $datetime2 = new DateTime(date('Y-m-d H:i:s', strtotime("now")));
                    $interval = $datetime1->diff($datetime2);

                    if ($interval->format('%a') <= 10) {
                        echo ' <span class="ml-3 badge badge-success">Neu</span>';
                    }

                    echo '</label>';
                    echo '</div>';
                    echo '</div>';
                    echo '<div class="col">';
                    if ($co['component_name'] != 'nichts Ausgewählt') {
                        echo '<a href="" data-component-id="' . $co['id'] . '" class="show_component_details btn mt-1 btn-sm btn-secondary" >Details</a>';
                    }
                    echo '</div>';
                    echo '<div class="col text-right">';
                    echo '<strong class=" mt-1 d-block price_' . $pc['cat'] . '_' . $co['id'] . '">' . $co['prefix'] . $co['price']['formated'] . '</strong>';
                    echo '</div>';
                    echo '</div>';

                    echo '</div>';
                    $last = $co['cat-id'];
                }
                echo '</div>';
                echo '</div>';
            }

            ?>
							</div>
						</div>


					</div>
						<?php
$index++;
        }

    }

    echo '</div>';
}

function change_pc_configuration()
{
    if (defined('DOING_AJAX') && DOING_AJAX) {
        global $wpdb;
        $params = array();
        parse_str($_POST['data'], $params);

        $database_name = $wpdb->prefix . 'components';
        foreach ($params['components']['selected'] as $sels) {
            $wpdb->get_results("UPDATE $database_name SET component_selected_count = component_selected_count + 1  WHERE component_id = " . $sels);
        }

        get_components_list($params['product_id'], 'json');

        exit();

    }
}
function get_components_list($product_id, $return = 'array')
{
    global $woocommerce;

    $product = wc_get_product($product_id);
    $ava = get_post_meta($product_id, 'components');
    #echo '<pre>';
    #print_r($ava);
    #echo '</pre>';
    $selected_data = array();
    $total_price = $product->get_price();
    $return_ar = array();

    if (is_array($_POST['data'])) {
        $post_data = $_POST['components'];
    } else {
        parse_str($_POST['data'], $params);
        $post_data = $params['components'];
    }
    $compo_list = array();
    foreach ($ava[0]['available'] as $key => $components) {
        $y = array();
        $selected_image = '';
        foreach ($components as $component) {

            $com_data = get_component_by_ID($component);

            if ($ava[0]['buildin'][$key] == $com_data->component_id) {
                $selected_price_id = $com_data->component_id;
            }

            if ($post_data['selected'][$key] == $com_data->component_id) {
                if ($com_data->component_purchasing_price != 0) {
                    if ($ava[0]['buildin'][$key] == $com_data->component_id) {
                        $calc_price = 0;
                    } else {
                        $calc_price = $com_data->component_retail_price - get_component_price_by_ID($selected_price_id);
                    }

                } else {
                    $calc_price = $com_data->component_purchasing_price - get_component_price_by_ID($selected_price_id, 'component_retail_price');
                }
            } else {
                if ($com_data->component_purchasing_price != 0) {
                    $calc_price = $com_data->component_retail_price - get_component_price_by_ID($selected_price_id);
                } else {
                    $calc_price = $com_data->component_purchasing_price - get_component_price_by_ID($selected_price_id, 'component_retail_price');
                    $calc_price = 0;
                }
            }

            $tax_rates = WC_Tax::get_rates($product->get_tax_class());
            $taxes = WC_Tax::calc_tax($calc_price, $tax_rates, false);
            $tax_amount = WC_Tax::get_tax_total($taxes);
            $price = round($calc_price + $tax_amount, wc_get_price_decimals());

            if (is_array($post_data['selected'])) {
                if ($post_data['selected'][$key] == $com_data->component_id) {
                    $_is_selected = true;
                } else {
                    $_is_selected = false;
                }
            } else {
                if ($ava[0]['buildin'][$key] == $com_data->component_id) {
                    $_is_selected = true;
                    $_is_buildin = true;
                } else {
                    $_is_selected = false;
                    $_is_buildin = false;
                }
            }

            if ($_is_buildin) {
                $price = 0;
                $selected_image = $_compo_image;
                $selected_name = $com_data->component_name;
                #array_push($compo_list , $com_data->component_name);
            }
            if ($_is_selected) {
                if (get_component_price_by_ID($com_data->component_id) == 0) {
                    $price = 0;
                }
                $selected_name = $com_data->component_name;
                #array_push($compo_list , $com_data->component_name);
                if (!empty($com_data->component_image)) {
                    $_compo_image = wp_get_attachment_image_src($com_data->component_image);
                } else {
                    $_compo_image = array(plugins_url() . '/pcbuilder/img/noimage.png', 150, 150);
                }
                $selected_image = $_compo_image;

                $total_price += $price;

            }

            if ($_is_buildin) {

                $subterm = get_term($com_data->component_categorie, 'component_categorie');
                if ($subterm->parent == 0) {

                    array_unshift($y, array(
                        'id' => $com_data->component_id,
                        'component_name' => $com_data->component_name,
                        'cat-id' => $com_data->component_categorie,
                        'component_image' => $_compo_image,
                        'component_status' => $com_data->component_status,
                        'component_out_of_stock' => $com_data->component_out_of_stock,
                        'component_retail_count' => $com_data->component_retail_count,
                        'compontent_added' => $com_data->component_added,
                        'is_build_in' => $_is_buildin,
                        'is_selected' => $_is_selected,
                        'purchasing_price' => $com_data->component_purchasing_price,
                        'retail_price' => $com_data->component_retail_price,
                        'price' => array(
                            'formated' => wc_price($price),
                            'natural' => $price,
                            'tax' => array(
                                'formated' => wc_price($taxes[1]),
                                'natural' => $taxes[1],
                            ),
                        ),
                    ));
                } else {
                    array_push($y, array(
                        'id' => $com_data->component_id,
                        'component_name' => $com_data->component_name,
                        'cat-id' => $com_data->component_categorie,
                        'component_image' => $_compo_image,
                        'component_status' => $com_data->component_status,
                        'component_out_of_stock' => $com_data->component_out_of_stock,
                        'component_retail_count' => $com_data->component_retail_count,
                        'compontent_added' => $com_data->component_added,
                        'is_build_in' => $_is_buildin,
                        'is_selected' => $_is_selected,
                        'purchasing_price' => $com_data->component_purchasing_price,
                        'retail_price' => $com_data->component_retail_price,
                        'price' => array(
                            'formated' => wc_price($price),
                            'natural' => $price,
                            'tax' => array(
                                'formated' => wc_price($taxes[1]),
                                'natural' => $taxes[1],
                            ),
                        ),
                    ));
                }

            } else {

                $subterm = get_term($com_data->component_categorie, 'component_categorie');
                if ($subterm->parent == 0) {
                    array_push($y, array(
                        'id' => $com_data->component_id,
                        'component_name' => $com_data->component_name,
                        'cat-id' => $com_data->component_categorie,
                        'component_image' => $_compo_image,
                        'component_status' => $com_data->component_status,
                        'component_out_of_stock' => $com_data->component_out_of_stock,
                        'component_retail_count' => $com_data->component_retail_count,
                        'compontent_added' => $com_data->component_added,
                        'is_build_in' => $_is_buildin,
                        'is_selected' => $_is_selected,
                        'purchasing_price' => $com_data->component_purchasing_price,
                        'retail_price' => $com_data->component_retail_price,
                        'price' => array(
                            'formated' => wc_price($price),
                            'natural' => $price,
                            'tax' => array(
                                'formated' => wc_price($taxes[1]),
                                'natural' => $taxes[1],
                            ),
                        ),
                        'prefix' => $price >= 0 ? '+' : '',
                    ));
                } else {
                    array_push($y, array(
                        'id' => $com_data->component_id,
                        'component_name' => $com_data->component_name,
                        'cat-id' => $com_data->component_categorie,
                        'component_image' => $_compo_image,
                        'component_status' => $com_data->component_status,
                        'component_out_of_stock' => $com_data->component_out_of_stock,
                        'component_retail_count' => $com_data->component_retail_count,
                        'compontent_added' => $com_data->component_added,
                        'is_build_in' => $_is_buildin,
                        'is_selected' => $_is_selected,
                        'purchasing_price' => $com_data->component_purchasing_price,
                        'retail_price' => $com_data->component_retail_price,
                        'price' => array(
                            'formated' => wc_price($price),
                            'natural' => $price,
                            'tax' => array(
                                'formated' => wc_price($taxes[1]),
                                'natural' => $taxes[1],
                            ),
                        ),
                        'prefix' => $price >= 0 ? '+' : '',
                    ));
                }

            }

        }

        $total_price_clear = $total_price;
        $tax_rates = WC_Tax::get_rates($product->get_tax_class());
        $taxes = WC_Tax::calc_tax($total_price, $tax_rates, false);
        $tax_amount = WC_Tax::get_tax_total($taxes);
        $total_price_re = round($total_price + $tax_amount, wc_get_price_decimals());
        #echo $selected_image[0].'<br />';

        $t = get_term($key);
        $t_ID = $t->term_id;

/*             array_push($selected_data, array(
'cat' => $key,
'selected_price_id' => $selected_price_id,
'selected_image' => $selected_image,
'selected_component_name' => $selected_name,
'cat_data' => $t,
'cat_options' => get_option("taxonomy_$t_ID"),
'data' => $y,

));  */
        $selected_data[$key] = array(

            'selected_price_id' => $selected_price_id,
            'selected_image' => $selected_image,
            'selected_component_name' => $selected_name,
            'cat_data' => $t,
            'cat_options' => get_option("taxonomy_$t_ID"),
            'data' => $y,

        );

    }

    $return_ar = array(
        'components' => $selected_data,
        'total_price' => array(
            'formated' => wc_price($total_price_re),
            'natural' => $total_price,
            'taxless' => $total_price_clear,
            'taxrate' => round($tax_rates[1]['rate'], 0) . '% ' . $tax_rates[1]['label'],
            'tax' => array(
                'formated' => wc_price($taxes[1]),
                'natural' => $taxes[1],
            ),
        ),
    );

    if ($return == 'array') {
        return $return_ar;
    } else if ($return == 'json') {
        header('Content-Type: application/json');
        #print_r($return_ar);
        echo json_encode($return_ar);
        exit;
    }

}

function get_component_by_ID($component_id)
{
    global $wpdb;
    $database_name = $wpdb->prefix . 'components';
    $sql_stat = 'WHERE component_id = ' . $component_id;
    $query = "SELECT * FROM $database_name $sql_stat";

    return $wpdb->get_row($query, OBJECT);
}

function get_component_price_by_ID($component_id, $typ = 'component_purchasing_price')
{
    global $wpdb;
    $database_name = $wpdb->prefix . 'components';
    $sql_stat = 'WHERE component_id = ' . $component_id;
    $query = "SELECT $typ FROM $database_name  $sql_stat";

    return $wpdb->get_var($query);
}

/**
 * Main plugin class PCBuilder
 *
 * @since 1.0.0
 */
class PCBuilder
{

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
    public function __construct()
    {

        // Installation
        if (is_admin() && !defined('DOING_AJAX')) {
            $this->install();
        }

        add_action('init', array($this, 'load_translation'));
        add_action('woocommerce_init', array($this, 'init'));
    }

    /**
     * Cloning instances is forbidden due to singleton pattern.
     *
     * @since 1.5.0
     */
    public function __clone()
    {
        /* translators: Placeholders: %s - plugin name */
        _doing_it_wrong(__FUNCTION__, sprintf(esc_html__('You cannot clone instances of %s.', 'woocommerce-custom-product-tabs-lite'), 'WooCommerce Custom Product Tabs Lite'), '1.5.0');
    }

    /**
     * Unserializing instances is forbidden due to singleton pattern.
     *
     * @since 1.5.0
     */
    public function __wakeup()
    {
        /* translators: Placeholders: %s - plugin name */
        _doing_it_wrong(__FUNCTION__, sprintf(esc_html__('You cannot unserialize instances of %s.', 'woocommerce-custom-product-tabs-lite'), 'WooCommerce Custom Product Tabs Lite'), '1.5.0');
    }

    /**
     * Load translations
     *
     * @since 1.2.5
     */
    public function load_translation()
    {
        // localization
        load_plugin_textdomain('woocommerce-custom-product-tabs-lite', false, dirname(plugin_basename(__FILE__)) . '/i18n/languages');
    }

    /**
     * Init WooCommerce Product Tabs Lite extension once we know WooCommerce is active
     */
    public function init()
    {

        // backend stuff
        add_action('woocommerce_product_write_panel_tabs', array($this, 'product_write_panel_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'product_write_panel'));
        add_action('woocommerce_process_product_meta', array($this, 'product_save_data'), 10, 2);
        add_action('admin_menu', array($this, 'mt_add_pages'));
        add_action('admin_footer', array(&$this, 'ajax_script'));
        add_action('parent_file', array(&$this, 'prefix_highlight_taxonomy_parent_menu'));

        add_action('admin_enqueue_scripts', array($this, 'load_custom_wp_admin_style'));
        add_action('admin_enqueue_scripts', array($this, 'my_enqueue'));
        // frontend stuff
        add_filter('woocommerce_product_tabs', array($this, 'add_custom_product_tabs'));

        // allow the use of shortcodes within the tab content
        add_filter('woocommerce_custom_product_tabs_lite_content', 'do_shortcode');
        add_action('admin_head', array(&$this, 'admin_header'));

        add_action('component_categorie_add_form_fields', array($this, 'extra_category_fields'), 10, 2);
        add_action('component_categorie_edit_form_fields', array($this, 'extra_category_fields'), 10, 2);
        add_action('edited_component_categorie', array($this, 'save_extra_taxonomy_fileds'), 10, 2);
        add_action('created_component_categorie', array($this, 'save_extra_taxonomy_fileds'), 10, 2);

        add_action('product_cat_add_form_fields', array($this, 'extra_category_product_cat_fields'), 10, 2);
        add_action('product_cat_edit_form_fields', array($this, 'extra_category_product_cat_fields'), 10, 2);
        add_action('edited_product_cat', array($this, 'save_extra_product_cat_taxonomy_fileds'), 10, 2);
        add_action('created_product_cat', array($this, 'save_extra_product_cat_taxonomy_fileds'), 10, 2);

        add_filter('manage_edit-product_cat_columns', function ($columns) {
            if (isset($columns['description'])) {
                $old = '';
            }

            $old = $columns['description'];

            unset($columns['description']);

            return $columns;
        });

        #add_filter( 'woocommerce_add_to_cart_validation', array( $this,'so_validate_add_cart_item'), 10, 5 );
        #add_action('woocommerce_add_cart_item_data', array( $this,'custom_add_to_cart'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'wdm_add_item_data'), 1, 2);
        add_filter('woocommerce_before_calculate_totals', array($this, 'return_custom_price'), 10, 2);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'wdm_get_cart_items_from_session'), 1, 3);
        add_filter('woocommerce_cart_item_name', array($this, 'wdm_add_user_custom_option_from_session_into_cart'), 1, 3);
        #add_filter('woocommerce_cart_item_price',array( $this,'wdm_add_user_custom_option_from_session_into_cart'),1,3);
        add_action('woocommerce_add_order_item_meta', array($this, 'wdm_add_values_to_order_item_meta'), 1, 2);
        add_action('woocommerce_before_cart_item_quantity_zero', array($this, 'wdm_remove_user_custom_data_options_from_cart'), 1, 1);

// REGISTER COMPONENT_CATEGORIE TERM
        $labels = array(
            'name' => _x('Komponenten Kategorie', 'taxonomy general name'),
            'singular_name' => _x('Komponente', 'taxonomy singular name'),
            'search_items' => __('Komponenten Kategorie suchen'),
            'all_items' => __('Alle Komponenten Kategorie'),
            'parent_item' => __('Parent Komponenten Kategorie'),
            'parent_item_colon' => __('Parent Komponenten Kategorie:'),
            'edit_item' => __('Komponenten Kategorie bearbeiten'),
            'update_item' => __(' Komponenten Kategorie Updaten'),
            'add_new_item' => __('Neue Komponenten Kategorie'),
            'new_item_name' => __('Neue Komponenten Kategorie'),
            'menu_name' => __('Komponenten Kategorien'),
        );

        // Now register the taxonomy

        register_taxonomy('component_categorie', array('post'), array(
            'hierarchical' => true,
            'labels' => $labels,
            'sort' => true,
            'show_ui' => true,
            'show_admin_column' => false,
            'exclude_from_search' => true,
            'public' => false,
            'query_var' => true,
            'rewrite' => false,
        ));

    }

    public function wdm_remove_user_custom_data_options_from_cart($cart_item_key)
    {
        global $woocommerce;
        // Get cart
        $cart = $woocommerce->cart->get_cart();
        // For each item in cart, if item is upsell of deleted product, delete it
        foreach ($cart as $key => $values) {
            if ($values['wdm_user_custom_data_value'] == $cart_item_key) {
                unset($woocommerce->cart->cart_contents[$key]);
            }

        }
    }
    public function wdm_add_values_to_order_item_meta($item_id, $values)
    {
        global $woocommerce, $wpdb;
        $user_custom_values = $values['wdm_user_custom_data_value']['selected'];
        if (!empty($user_custom_values)) {

            foreach ($user_custom_values as $key => $co) {

                $database_name = $wpdb->prefix . 'components';

                $term = get_term($key, "component_categorie");

                $query = "SELECT component_name FROM $database_name WHERE component_id = " . $co;
                $datas = $wpdb->get_var($query);
                if ($datas != 'nichts Ausgewählt') {
                    //update retail count status
                    $wpdb->get_results("UPDATE $database_name SET component_retail_count = component_retail_count + 1  WHERE component_id = " . $co);
                    wc_add_order_item_meta($item_id, $term->name, $datas);
                }
            }

        }
    }

    public function return_custom_price($cart_object)
    {

        foreach ($cart_object->cart_contents as $key => $value) {

            $x = calc_component_prices($value['product_id'], $value['wdm_user_custom_data_value']);

            // for WooCommerce version 3+ use:
            $value['data']->set_price($x['total_price']['natural']);
        }
    }

    public function wdm_add_user_custom_option_from_session_into_cart($product_name, $values, $cart_item_key)
    {
        /*code to add custom data on Cart & checkout Page*/
        global $wpdb;
        if (count($values['wdm_user_custom_data_value']) > 0) {
            $return_string = $product_name . "</a><dl class='variation'>";
            $return_string .= "<table class='wdm_options_table' id='" . $values['product_id'] . "'>";
            $return_string .= "<tr><td>";
            $return_string .= '<h5> Deine Konfiguration</h5>';
            foreach ($values['wdm_user_custom_data_value']['selected'] as $key => $co) {
                $term = get_term($key, "component_categorie");
                $database_name = $wpdb->prefix . 'components';
                $query = "SELECT component_name FROM $database_name WHERE component_id = " . $co;
                $datas = $wpdb->get_var($query);
                if ($datas != 'nichts Ausgewählt') {
                    $return_string .= '<strong class="d-block">' . $term->name . '</strong>';
                    $return_string .= $datas;
                }
            }

            $return_string .= "</td></tr>";
            $return_string .= "</table></dl>";
            return $return_string;
        } else {
            return $product_name;
        }
    }
    public function wdm_get_cart_items_from_session($item, $values, $key)
    {
        if (array_key_exists('wdm_user_custom_data_value', $values)) {
            $item['wdm_user_custom_data_value'] = $values['wdm_user_custom_data_value'];
        }
        return $item;
    }

    public function wdm_add_item_data($cart_item_data, $product_id)
    {
        /*Here, We are adding item in WooCommerce session with, wdm_user_custom_data_value name*/
        global $woocommerce;
        session_start();

        $_SESSION['wdm_user_custom_data'] = $_POST['components'];
        if (isset($_SESSION['wdm_user_custom_data'])) {
            $option = $_SESSION['wdm_user_custom_data'];
            $new_value = array('wdm_user_custom_data_value' => $option);
        }
        if (empty($option)) {
            return $cart_item_data;
        } else {
            if (empty($cart_item_data)) {
                return $new_value;
            } else {
                return array_merge($cart_item_data, $new_value);
            }

        }
        unset($_SESSION['wdm_user_custom_data']);
        //Unset our custom session variable, as it is no longer needed.
    }

    public function custom_add_to_cart($cart_item_meta, $product_id)
    {
        // let's consider that the user is logged in
        $user_id = get_current_user_id();
        if (0 != $user_id) {
            // set the values as bookings meta
            $cart_item_meta['components'] = $_POST['components']['selected'];

        }

        return $cart_item_meta;

    }

    public function so_validate_add_cart_item($passed, $product_id, $quantity, $variation_id = '', $variations = '')
    {

        // do your validation, if not met switch $passed to false
        if (1 != 2) {
            $passed = false;
            wc_add_notice(__('You can not do that', 'textdomain'), 'error');
            echo ($product_id);
        }
        return $passed;

    }

    public function save_extra_product_cat_taxonomy_fileds($term_id)
    {

        if (isset($_POST['term_meta'])) {
            $t_id = $term_id;
            $term_meta = get_option("taxonomy_$t_id");
            $cat_keys = array_keys($_POST['term_meta']);
            foreach ($cat_keys as $key) {
                if (isset($_POST['term_meta'][$key])) {
                    $term_meta[$key] = $_POST['term_meta'][$key];
                }
            }
            //save the option array
            update_option("taxonomy_$t_id", $term_meta);
        }
    }

    public function extra_category_product_cat_fields($tag)
    { //check for existing featured ID
        $t_id = $tag->term_id;
        $term_meta = get_option("taxonomy_$t_id");

        ?>

<tr class="form-field term-name-wrap">
	<th scope="row" valign="top"><label for="cat_Image_url"><?php _e('Überschrift');?></label></th>
	<td>
		<input type="text" name="term_meta[title_shown]" id="term_meta[title_shown]" size="40" value="<?php echo $term_meta['title_shown']; ?>" />
		<p class="description">Ist dieses Feld leer wird der Kategorie Title ausgegeben im Listing.</p>
	</td>
</tr>


<?php
}

    public function save_extra_taxonomy_fileds($term_id)
    {

        if (isset($_POST['term_meta'])) {
            $t_id = $term_id;
            $term_meta = get_option("taxonomy_$t_id");
            $cat_keys = array_keys($_POST['term_meta']);
            foreach ($cat_keys as $key) {
                if (isset($_POST['term_meta'][$key])) {
                    $term_meta[$key] = $_POST['term_meta'][$key];
                }
            }
            //save the option array
            update_option("taxonomy_$t_id", $term_meta);
        }
    }

    public function extra_category_fields($tag)
    { //check for existing featured ID
        $t_id = $tag->term_id;

        $term_meta = get_option("taxonomy_$t_id");

        ?>
<tr class="form-field">
	<th scope="row" valign="top"><label for="cat_Image_url"><?php _e('Listing Style');?></label></th>
	<td>
		<?php
switch ($term_meta['component_group']) {

            case 'main':
                $main_grp = ' checked="checked" ';
                $modding_grp = '';
                break;
            case 'modding':
                $main_grp = '  ';
                $modding_grp = ' checked="checked" ';
                break;
            default:
                $main_grp = ' checked="checked" ';
                $modding_grp = '  ';
                break;
        }

        ?>
		<input type="radio" name="term_meta[component_group]" id="term_meta[component_group]" value="main" <?php echo $main_grp; ?> />
		Hauptkomponenten

		<input type="radio" name="term_meta[component_group]" id="term_meta[component_group]" value="modding" <?php echo $modding_grp; ?> />
		Modding

	</td>
</tr>

<tr class="form-field">
	<th scope="row" valign="top"><label for="cat_Image_url"><?php _e('Überschrift');?></label></th>
	<td>
		<input type="input" name="term_meta[title_shown]" id="term_meta[title_shown]" value="<?php $term_meta['title_shown']?>" />
	</td>
</tr>

<tr class="form-field">
	<th scope="row" valign="top"><label for="cat_Image_url"><?php _e('Listing Style');?></label></th>
	<td>
		<?php
switch ($term_meta['grid_style']) {

            case 'list':
                $gridstyle = ' ';
                $liststyle = ' checked="checked" ';
                break;
            case 'grid':
                $gridstyle = ' checked="checked" ';
                $liststyle = '  ';
                break;
            default:
                $gridstyle = ' ';
                $liststyle = ' checked="checked" ';
                break;
        }

        ?>
		<input type="radio" name="term_meta[grid_style]" id="term_meta[grid_style]" value="list" <?php echo $liststyle; ?> />
		Liste

		<input type="radio" name="term_meta[grid_style]" id="term_meta[grid_style]" value="grid" <?php echo $gridstyle; ?> />
		Grid

	</td>
</tr>

<?php
}

    public function ajax_script()
    {
        ?>
		<script type="text/javascript">
			(function($) {
					$('.changestate').click(function(e){


					});

			})(jQuery);
		</script>

		<?php

    }

    public function prefix_highlight_taxonomy_parent_menu($parent_file)
    {
        if (get_current_screen()->taxonomy == 'component_categorie') {
            $parent_file = 'manage_components';
        }
        return $parent_file;
    }

    public function admin_header()
    {

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
        echo '#wpfooter {display:none;}';
        echo '</style>';
    }
    // action function for above hook
    public function mt_add_pages()
    {
        remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=component_categorie');
        // Add a new top-level menu (ill-advised):
        add_menu_page(__('Komponenten', 'menu-test'), __('Komponenten', 'menu-test'), 'manage_options', 'manage_components', array(&$this, 'mt_toplevel_page'));

        // Add a submenu to the custom top-level menu:
        add_submenu_page('manage_components', __('Kategorien', 'menu-test'), __('Kategorien', 'menu-test'), 'manage_options', 'edit-tags.php?taxonomy=component_categorie');
        add_submenu_page('manage_components', __('Verknüpfungen', 'menu-test'), __('Verknüpfungen', 'menu-test'), 'manage_options', 'sub-page2', array(&$this, 'mt_component_shortcut_page'));
        // Add a second submenu to the custom top-level menu:
        add_submenu_page('manage_components', __('Importieren', 'menu-test'), __('Test Sublevel 2', 'menu-test'), 'manage_options', 'sub-page3', array(&$this, 'mt_import_page'));
    }

    public function parse_csv($csv_string, $delimiter = ",", $skip_empty_lines = true, $trim_fields = true)
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
    public function upload_image($url, $post_id, $name)
    {
        $image = "";
        if ($url != "") {

            $file = array();
            $file['name'] = $name;
            $file['tmp_name'] = download_url($url);

            if (is_wp_error($file['tmp_name'])) {
                @unlink($file['tmp_name']);
                var_dump($file['tmp_name']->get_error_messages());
            } else {
                $attachmentId = media_handle_sideload($file, $post_id);

                if (is_wp_error($attachmentId)) {
                    @unlink($file['tmp_name']);
                    var_dump($attachmentId->get_error_messages());
                } else {
                    $image = wp_get_attachment_url($attachmentId);
                }
            }
        }
        return $image;
    }

    public function mt_component_shortcut_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        $action = $_POST['action'];

        switch ($action) {

            case 'display_shortcuts':
                $current_post_id = $_POST['product_id'];
                $post_meta_data = get_post_meta($current_post_id, 'components');

                break;

            case 'submit_shortcuts':
                $current_post_id = $_POST['product_id'];
/*                 foreach($_POST['components']['retail_price'] as $group_key => $retail_group_prices){
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
                add_post_meta($current_post_id, 'components', $_POST['components'], true);
                $post_meta_data = get_post_meta($current_post_id, 'components');

                break;

            default:
                $current_post_id = '';
                break;

        }

        ?>
		<?php wp_enqueue_script('ap13', plugins_url('/shortcut_configurator.js', __FILE__));?>
		<div class="wrap">
			<h1>
				Verknüpfungen
			</h1>

		</div>

		<div class="postbox">
			<div class="inside">
			<?php
echo '<form name="my_form" method="post">';
        $datas_obj = new WP_Query(array('post_type' => array('product'), 'posts_per_page' => -1));
        $options = '';

        if ($datas_obj->have_posts()):
            while ($datas_obj->have_posts()) {
                $datas_obj->the_post();
                $selected = '';
                if (!empty($current_post_id)) {
                    $selected = ' selected="selected" ';
                }
                $options .= '<option ' . $selected . ' value="' . get_the_ID() . '">' . get_the_title() . '</option>';

            }
        endif;

        echo '<select name="product_id">';
        echo $options;
        echo '</select>';

        echo '<input type="hidden" name="action" value="display_shortcuts">';
        echo ' <input type="submit" name="submit" id="submit" class="button button-primary" value="Los">';
        echo '</form>';
        ?>
			</div>
		</div>

		<form name="submit_shortcuts" method="post">

		<input type="hidden" name="action" value="submit_shortcuts">
		<input type="hidden" name="product_id" value="<?php echo $current_post_id ?>">
		<?php

        $taxonomy = "component_categorie";
        /** Get all taxonomy terms */
        $terms = get_terms($taxonomy, array(
            'orderby' => 'term_order',
            "hide_empty" => false,
        )
        );

        if (!empty($current_post_id)) {
            submit_button('Speichern');
            echo '<div class="postbox-container" style="width:100%;">';
            echo '<div class="postbox">';
            echo '<h2 class="hndle ui-sortable-handle">Komponenten Verknüpfen <a style="font-size:.8rem;" href="#" class="mark_all">Alles Auswählen</a> <a style="font-size:.8rem;" href="#" class="unmark_all">Alles Abwählen</a></h2>';
            echo '<div class="inside" id="items">';
            global $wpdb;
            $_product = new WC_Product($current_post_id);
            $database_name = $wpdb->prefix . 'components';

            $categories_terms = get_terms(array(
                'orderby' => 'term_order',
                'taxonomy' => 'component_categorie',
                'hide_empty' => false,
                'parent' => 0,
            ));

            foreach ($categories_terms as $ct) {

                $sql_stat = 'WHERE component_categorie = ' . $ct->term_id;
                $query = "SELECT * FROM $database_name  $sql_stat ORDER BY component_sort DESC";
                $datas = $wpdb->get_results($query);

                if ($ct->parent == 0) {
                    echo '<h1 style="background:rgba(21,21,21,.1);padding:.5rem;margin:.5rem 0 .5rem 0;">' . $ct->name . ' <a data-cat-id="' . $ct->term_id . '" style="font-size:.8rem;" class="mark_all_categories" href="#">alles Auswählen</a> <a data-cat-id="' . $ct->term_id . '" style="font-size:.8rem;" class="unmark_all_categories" href="#">alles Abwählen</a></h1>';
                } else {
                    echo '<h3 style="padding:.2rem;margin:.2rem 0 .2rem 0;">' . $ct->name . '</h3>';
                }

                foreach ($datas as $data) {
                    echo '<div style="padding:.2rem .5rem">';
                    if ($ct->parent == 0) {
                        $term_group_id = $ct->term_id;
                    } else {
                        $term_group_id = $ct->parent;
                    }

                    if (is_array($post_meta_data[0]['available'][$term_group_id])) {
                        if (in_array($data->component_id, $post_meta_data[0]['available'][$term_group_id]) == 1) {
                            $checked_ava = 'checked="checked"';
                        } else {
                            $checked_ava = '';
                        }
                    } else {
                        $checked_ava = '';
                    }

                    if ($data->component_id == $post_meta_data[0]['buildin'][$term_group_id]) {
                        $checked_build = 'checked="checked"';
                    } else {
                        $checked_build = '';
                    }

                    ?>
								<table>
									<tr>
										<td>
											<input <?php echo $checked_ava; ?> type="checkbox" name="components[available][<?php echo $term_group_id; ?>][]" value="<?php echo $data->component_id ?>" />
										</td>
										<td>
											<input <?php echo $checked_build; ?> type="radio" name="components[buildin][<?php echo $term_group_id; ?>]" value="<?php echo $data->component_id; ?>" />
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

                $terms = get_terms(array(
                    'taxonomy' => 'component_categorie',
                    'hide_empty' => false,
                    'child_of' => $ct->term_id,
                    'orderby' => 'term_order',
                ));
                foreach ($terms as $term) {
                    echo '<div style="margin-left:2rem;">';
                    echo '<h3>' . $term->name . '</h3>';
                    $sql_stat = 'WHERE component_categorie = ' . $term->term_id;
                    $query = "SELECT * FROM $database_name  $sql_stat ORDER BY component_sort DESC";

                    $datas = $wpdb->get_results($query);
                    $count = $wpdb->num_rows;

                    foreach ($datas as $data) {
                        echo '<div style="padding:.2rem .5rem">';
                        if ($ct->parent == 0) {
                            $term_group_id = $ct->term_id;
                        } else {
                            $term_group_id = $ct->parent;
                        }

                        if (is_array($post_meta_data[0]['available'][$term_group_id])) {
                            if (in_array($data->component_id, $post_meta_data[0]['available'][$term_group_id]) == 1) {
                                $checked_ava = 'checked="checked"';
                            } else {
                                $checked_ava = '';
                            }
                        } else {
                            $checked_ava = '';
                        }

                        if ($data->component_id == $post_meta_data[0]['buildin'][$term_group_id]) {
                            $checked_build = 'checked="checked"';
                        } else {
                            $checked_build = '';
                        }

                        ?>
								<table>
									<tr>
										<td>
											<input <?php echo $checked_ava; ?> type="checkbox" name="components[available][<?php echo $term_group_id; ?>][]" value="<?php echo $data->component_id ?>" />
										</td>
										<td>
											<input <?php echo $checked_build; ?> type="radio" name="components[buildin][<?php echo $term_group_id; ?>]" value="<?php echo $data->component_id; ?>" />
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

            }

            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

    }

    public function mt_import_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $args = array(
            'post_type' => 'product',
        );

        $loop = new WP_Query($args);
        $a = 0;

        ?>
		<table>

		<?php

        while ($loop->have_posts()): $loop->the_post();
            global $product;

            $x = get_post_meta(get_the_ID(), 'components');

            if (is_array($x[0]['buildin'])) {
                if (in_array($component_id, $x[0]['buildin'])) {
                    $a++;
                }
            }
            #print_r($x[0]['buildin']);
            ?>
				<tr>
					<td>
					<?php echo '<a href="' . get_permalink() . '">' . get_the_title() . '</a>'; ?>
					</td>
					<td>
					<?php

            if (is_array($x[0]['buildin'])) {
                foreach ($x[0]['buildin'] as $key => $cid) {
                    #echo $cid;

                    echo $r->name;
                    $database_name = $wpdb->prefix . 'components';
                    $sql_stat = 'WHERE component_categorie = ' . $key;
                    $query = "SELECT * FROM $database_name $sql_stat";

                    #print_r( $wpdb->get_row($query , OBJECT));

                }
            }

            ?>
					</td>
				</tr>
				<?php

        endwhile;

        ?>
		</table>
		<?php
wp_reset_query();

    }

    // mt_toplevel_page() displays the page content for the custom Test Toplevel menu
    public function mt_toplevel_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        global $myListTable;

        ?>

 <?php if (($_GET['action'] === 'edit') || $_GET['action'] === 'add_new'): ?>

<?php wp_enqueue_script('ap12', plugins_url('/app.js', __FILE__));?>
		<div class="wrap">

			<h1>
			<a class="page-title-action" href="admin.php?page=manage_components" >Zurück</a>
			<?php
if ($_GET['action'] === 'add_new') {
            esc_html_e('neue Komponente', 'domain');
        } else {
            esc_html_e('Komponente bearbeiten', 'domain');
        }

        ?>

			</h1>

			<?php
global $wpdb;
        wp_enqueue_media();
        $database_name = $wpdb->prefix . 'components';
        $query = "SELECT * FROM $database_name WHERE component_id = " . $_GET['componentid'];
        $datas = $wpdb->get_row($query, ARRAY_A);
        $img = wp_get_attachment_image_src($datas['component_image'], array(400, 400));
        if (empty($img)) {
            $img = wp_get_attachment_image_src(48, array(400, 400));
        }

        if ($_POST['action'] === 'update_component') {

            if ($_GET['action'] === 'add_new') {
                $update_ok = $wpdb->insert(
                    $database_name,
                    array(
                        'component_sort' => $_POST['component_sort'],
                        'component_status' => $_POST['component_status'] == 'on' ? true : false,
                        'component_out_of_stock' => $_POST['component_out_of_stock'] == 'on' ? true : false,
                        'component_categorie' => $_POST['component_categorie'],
                        'component_item_number' => $_POST['component_item_number'],
                        'component_image' => $_POST['component_image'],
                        'component_more_images' => json_encode($_POST['more_images']),
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
                        '%s',
                        '%f',
                        '%f',
                        '%s',
                    ),
                    array('%d')
                );
            } else {
                $update_ok = $wpdb->update(
                    $database_name,
                    array(
                        'component_sort' => $_POST['component_sort'],
                        'component_status' => $_POST['component_status'] == 'on' ? true : false,
                        'component_out_of_stock' => $_POST['component_out_of_stock'] == 'on' ? true : false,
                        'component_categorie' => $_POST['component_categorie'],
                        'component_item_number' => $_POST['component_item_number'],
                        'component_image' => $_POST['component_image'],
                        'component_more_images' => json_encode($_POST['more_images']),
                        'component_name' => $_POST['component_name'],
                        'component_descripion' => $_POST['component_descripion'],
                        'component_purchasing_price' => $_POST['component_purchasing_price'],
                        'component_retail_price' => $_POST['component_retail_price'],
                        'component_modified' => strtotime('now'),

                    ),
                    array('component_id' => $_POST['component_id']),
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
                        '%s',
                        '%s',
                    ),
                    array('%d')
                );
            }

            if ($update_ok) {
                #echo '<pre>';
                #echo $_POST;
                #print_r($_POST);
                #echo '</pre>';

            }
            wp_redirect('admin.php?page=manage_components');

        }

        ?>

			<form name="my_form" method="post">
				<input type="hidden" name="action" value="update_component">
				<input type="hidden" name="component_id" value="<?php echo $datas['component_id']; ?>">

				<?php wp_nonce_field('some-action-nonce');

        $chk = '';

        $categories_terms = get_terms(array(
            'taxonomy' => 'component_categorie',
            "orderby" => "term_order",
            'hide_empty' => false,
            'parent' => 0,
        ));

        $component_categories = '<select name="component_categorie">';
        $component_categories .= '<option selected disabled value="0" >nicht zugeordnet</option>';
        foreach ($categories_terms as $ct) {

            $categories_terms_childs = get_terms(array(
                'taxonomy' => 'component_categorie',
                "orderby" => "term_order",
                'hide_empty' => false,
                'parent' => $ct->term_id,
            ));

            $component_categories .= '<optgroup label="' . $ct->name . '">';
            $component_categories .= '<option ' . ($ct->term_id == $datas['component_categorie'] ? ' selected ' : ' ') . '  value="' . $ct->term_id . '" >in Gruppe ' . $ct->name . ' (ohne Gruppierung)</option>';

            foreach ($categories_terms_childs as $ctc) {
                $component_categories .= "<option " . ($ctc->term_id == $datas['component_categorie'] ? ' selected ' : ' ') . " value=\"$ctc->term_id\" >" . $ctc->name . "</option>";

            }
            $component_categories .= '</optgroup>';
            if ($ct->term_parent !== 0) {

            }
        }

        $component_categories .= '</select>';

        /* Used to save closed meta boxes and their order */
        wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
        wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);?>

				<div id="poststuff">

					<div id="post-body" class="metabox-holder columns-2">

						<div id="post-body-content" style="position: relative;">
							<div id="titlediv">
								<div id="titlewrap">

									<label class="screen-reader-text" id="title-prompt-text" for="title">Titel hier eingeben</label>
									<input type="text" name="component_name" size="30" value="<?php echo $datas['component_name']; ?>" id="title" spellcheck="true" autocomplete="off">
								</div>
							</div>

							<br />
							<div class="postbox" >
								<h2 class="hndle ui-sortable-handle"><span>Komponenten Meta</span></h2>
								<div class="inside">
									<table>
										<tr valign="top">
											<td>
												<div>
													<label for="component_item_number">Kategorie</label><br/>
													<?php echo $component_categories; ?>
												</div>
											</td>
										</tr>
										<tr valign="top">
											<td align="left">
												<div>
													<label for="component_item_number">Artikelnummer</label><br/>
													<input type="text" id="component_item_number" name="component_item_number" value="<?php echo $datas['component_item_number']; ?>" />
												</div>

												<div>
													<label for="component_sort">Sortierung</label><br/>
													<input type="text" id="component_sort" name="component_sort" value="<?php echo $datas['component_sort']; ?>" />
												</div>
											</td>
											<td align="left">
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
								</div>
							</div>



							<div class="postbox" >
								<h2 class="hndle "><span>Komponenten Beschreibung</span></h2>
								<div class="inside">
									<?php
$settings = array(
            'quicktags' => array(
                'buttons' => 'strong,em,del,ul,ol,li,close',
            ),
            'media_buttons' => false,
            'editor_height' => 100,
            'textarea_name' => 'component_descripion',
        );
        $content = $datas['component_descripion'];
        $editor_id = 'editpost';

        wp_editor($content, $editor_id, $settings);

        ?>
								</div>
							</div>

							<div class="postbox" >
								<h2 class="hndle ui-sortable-handle"><span>Weitere Komponentenbilder</span></h2>
								<div class="inside">
									<input id="upload_image_gallery_button" type="button" class="button " value="mehr Bilder hinzufügen" />
									<hr />
									<table>
										<tr id="moreimages">
										<?php

        if (is_array(json_decode($datas['component_more_images']))) {
            foreach (json_decode($datas['component_more_images']) as $mimage) {
                $img = wp_get_attachment_image_src($mimage, array(200, 200));
                ?>
													<td>
														<img style="margin-right: .5rem; border:1px solid rgba(21,21,21,.5);" src="<?php echo $img[0]; ?>" height="<?php echo $img[2]; ?>" width="<?php echo $img[1]; ?>" />
														<br />
														<a href="#" class="remove_moreimage button">Entfernen</a>
														<input type="hidden" name="more_images[]" value="<?php echo $mimage; ?>" />
													</td>
													<?php

            }
        }
        ?>
										</tr>
									</table>
								</div>
							</div>




						</div>
						<div id="postbox-container-1" class="postbox-container">
							<div id="postmetadiv" class="postbox ">
								<h2 class="hndle ui-sortable-handle"><span>Speichern</span></h2>
								<div class="inside">
									<?php
if ($_GET['action'] === 'add_new') {
            submit_button('Hinzufügen', 'primary');
        } else {
            submit_button('Aktualisieren', 'primary');
        }
        ?>
								</div>
							</div>

							<div id="poststatusdiv" class="postbox ">
								<h2 class="hndle ui-sortable-handle">Status</h2>
								<div class="inside">
									<label class="selectit" for="component_status">
										<input type="checkbox" id="component_status" name="component_status" <?php checked($datas['component_status']);?> /> Status (Ein/Ausschalten)
									</label>
									<br />
									<label class="selectit" for="component_out_of_stock">
										<input type="checkbox" id="component_out_of_stock" name="component_out_of_stock" <?php checked($datas['component_out_of_stock']);?> /> Nicht Lieferbar
									</label>
								</div>
							</div>

							<div id="postimagediv" class="postbox ">
								<h2 class="hndle ui-sortable-handle">Komponentenbild</h2>
								<div class="inside">
									<div class='image-preview-wrapper' style="margin:1.3rem 0; ">
										<img id='image-preview' src='<?php echo $img[0]; ?>' width='<?php echo $img[1]; ?>' height='<?php echo $img[2]; ?>' >
									</div>
									<input id="upload_image_button" type="button" class="button" value="<?php _e('Upload image');?>" />
									<input type='hidden' name='component_image' id='component_image' value='<?php echo $datas['component_image']; ?>'>
								</div>
							</div>

						</div>

						<div id="postbox-container-2" class="postbox-container">

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
             <a href="<?php echo admin_url('admin.php?page=manage_components&action=add_new'); ?>" class="page-title-action">
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

        if ($_GET['action'] === 'changestate') {
            global $wpdb;
            $database_name = $wpdb->prefix . 'components';

            $query = "SELECT component_status from $database_name WHERE component_id = " . $_GET['componentid'];
            $current_state = $wpdb->get_var($query);

            if ($current_state == 0) {
                $newstate = 1;
            } else {
                $newstate = 0;
            }

            $wpdb->update(
                $database_name,
                array(
                    'component_status' => $newstate, // string

                ),
                array('component_id' => $_GET['componentid']),
                array(
                    '%s', // value1
                    '%d', // value2
                ),
                array('%d')
            );
            $myListTable->prepare_items();

        } else if ($_GET['action'] === 'outofstock') {
            global $wpdb;
            $database_name = $wpdb->prefix . 'components';

            $query = "SELECT component_out_of_stock from $database_name WHERE component_id = " . $_GET['componentid'];
            $current_state = $wpdb->get_var($query);

            if ($current_state == 0) {
                $newstate = 1;
            } else {
                $newstate = 0;
            }

            $wpdb->update(
                $database_name,
                array(
                    'component_out_of_stock' => $newstate, // string

                ),
                array('component_id' => $_GET['componentid']),
                array(
                    '%s', // value1
                    '%d', // value2
                ),
                array('%d')
            );
            $myListTable->prepare_items();
        } else if ($_GET['action'] === 'delete') {
            global $wpdb;
            $database_name = $wpdb->prefix . 'components';
            $wpdb->delete($database_name, array('component_id' => $_GET['componentid']));
            $myListTable->prepare_items();
        }

        ?>
	  <form method="post">
		<input type="hidden" name="page" value="ttest_list_table">
		<?php
$myListTable->search_box('search', 'search_id');
        $myListTable->display();
        echo '</form></div>';

        endif;
    }

    public function load_custom_wp_admin_style()
    {
        #wp_register_style( 'custom_wp_admin_css', plugins_url( '/skins/polaris/polaris.css' , __FILE__ ), false, '1.0.0' );
        wp_enqueue_style('custom', 'custom_wp_admin_css');
        wp_enqueue_style('faw', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css');

    }

    public function my_enqueue($hook)
    {
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
    public function add_custom_product_tabs($tabs)
    {
        global $product;

        if (!$product instanceof WC_Product) {
            return $tabs;
        }

        if ($this->product_has_custom_tabs($product)) {

            foreach ($this->tab_data as $tab) {
                $tab_title = __($tab['title'], 'woocommerce-custom-product-tabs-lite');
                $tabs[$tab['id']] = array(
                    'title' => apply_filters('woocommerce_custom_product_tabs_lite_title', $tab_title, $product, $this),
                    'priority' => 25,
                    'callback' => array($this, 'custom_product_tabs_panel_content'),
                    'content' => $tab['content'], // custom field
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
    public function custom_product_tabs_panel_content($key, $tab)
    {

        // allow shortcodes to function
        $content = apply_filters('the_content', $tab['content']);
        $content = str_replace(']]>', ']]&gt;', $content);

        echo apply_filters('woocommerce_custom_product_tabs_lite_heading', '<h2>' . $tab['title'] . '</h2>', $tab);
        echo apply_filters('woocommerce_custom_product_tabs_lite_content', $content, $tab);
    }

    /** Admin methods ******************************************************/

    /**
     * Adds a new tab to the Product Data postbox in the admin product interface.
     *
     * @since 1.0.0
     */
    public function product_write_panel_tab()
    {
        echo "<li class=\"product_tabs_lite_tab\"><a href=\"#PCBuilder\"><span>Komponenten</span></a></li>";
    }

    /**
     * Adds the panel to the Product Data postbox in the product interface
     *
     * TODO: We likely want to migrate getting meta to a product CRUD method post WC 3.1 {BR 2017-03-21}
     *
     * @since 1.0.0
     */
    public function product_write_panel()
    {
        global $post; // the product
        global $wpdb;
        // pull the custom tab data out of the database
        $tab_data = maybe_unserialize(get_post_meta($post->ID, 'frs_woo_product_tabs', true));

        if (empty($tab_data)) {

            // start with an array for PHP 7.1+
            $tab_data = array();

        }

        $results = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'components_categories ORDER BY component_categorie_sort ASC');

        foreach ($tab_data as $tab) {
            // display the custom tab panel

            echo '<div id="PCBuilder" class="panel wc-metaboxes-wrapper woocommerce_options_panel">';
            echo '<div style="padding:1rem;">';

            echo '<pre>';
            print_r($tab_data);

            echo '</pre>';

            echo '<span style="padding-left:140px;">Verfügbar</span><span style="padding-left:20px;">Eingebaut</span>';
            echo '<tabel>';

            $categories_terms = get_terms(array(
                'taxonomy' => 'component_categorie',
                'hide_empty' => false,
            ));

            foreach ($categories_terms as $ct) {
                if ($ct->parent == 0) {
                    echo '<h4 style="background:rgba(21,21,21,.2);">';
                    echo $ct->name;
                    echo '</h4>';
                }

                $component_results = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'components WHERE component_categorie = ' . $ct->term_id . ' ORDER BY component_sort ASC', OBJECT);
                if ($ct->parent == 0) {
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
                foreach ($component_results as $key => $component_result) {
                    if (isset($tab_data['buildin'][$ct->term_id])) {
                        if ($tab_data['buildin'][$ct->term_id] == $component_result->component_id) {
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

                    if (isset($tab_data['available'][$ct->term_id])) {
                        foreach ($tab_data['available'][$ct->term_id] as $checker) {
                            if ($checker === $component_result->component_id) {
                                echo ' checked ';
                            }
                        }
                    }
                    ?>

							type="checkbox" name="components[available][<?php echo $ct->term_id; ?>][]" value="<?php echo $component_result->component_id; ?>" />

						<?php
echo '<span>' . $component_result->component_name . '</span>';
                    if (isset($tab_data['buildin'][$ct->term_id])) {
                        if ($tab_data['buildin'][$ct->term_id] == $component_result->component_id) {
                            echo '</div>';
                        } else {
                            echo '</div>';
                        }
                    } else {
                        echo '</div>';
                    }

                }

            }

            foreach ($results as $result) {

                echo '<h4 style="background:rgba(21,21,21,.2);">';
                echo $result->components_categories_name;
                echo '</h4>';

                $component_results = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'components WHERE component_categorie = ' . $result->components_categories_id . ' ORDER BY component_sort ASC');

                $args = array(
                    'id' => 'components[defaults][' . $result->components_categories_id . '][]',
                    'label' => sanitize_text_field('Product IDs'),
                    'value' => $tab_data['defaults'][$result->components_categories_id][0],
                );
                woocommerce_wp_hidden_input($args);

                foreach ($component_results as $key => $component_result) {

                    echo '<div>';

                    ?>

												<?php if ($tab_data['defaults'][$result->components_categories_id][0] === $component_result->component_id) {?>
													<button>Ausbauen</button>
													<?php } else {?>
													<button>Einbauen</button>
													<?php }?>

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
    public function product_save_data($post_id, $post)
    {

/*    echo '<pre>';
print_r($_POST);
echo '</pre>';
exit;   */

        delete_post_meta($post_id, 'frs_woo_product_tabs');

        update_post_meta($post_id, 'frs_woo_product_tabs', $_POST['components']);

    }

    /**
     * Helper function to generate a text area input for the custom tab
     *
     * TODO: We likely want to migrate getting meta to a product CRUD method post WC 3.1 {BR 2017-03-21}
     *
     * @since 1.0.0
     * @param array $field the field data
     */
    private function woocommerce_wp_textarea_input($field)
    {
        global $thepostid, $post;

        $thepostid = !$thepostid ? $post->ID : $thepostid;
        $field['placeholder'] = isset($field['placeholder']) ? $field['placeholder'] : '';
        $field['class'] = isset($field['class']) ? $field['class'] : 'short';
        $field['value'] = isset($field['value']) ? $field['value'] : get_post_meta($thepostid, $field['id'], true);

        echo '<p class="form-field ' . $field['id'] . '_field"><label style="display:block;" for="' . $field['id'] . '">' . $field['label'] . '</label><textarea class="' . $field['class'] . '" name="' . $field['id'] . '" id="' . $field['id'] . '" placeholder="' . $field['placeholder'] . '" rows="2" cols="20"' . (isset($field['style']) ? ' style="' . $field['style'] . '"' : '') . '>' . esc_textarea($field['value']) . '</textarea> ';

        if (isset($field['description']) && $field['description']) {
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
    public static function instance()
    {
        if (is_null(self::$instance)) {
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
    private function product_has_custom_tabs($product)
    {

        if (false === $this->tab_data) {
            $this->tab_data = maybe_unserialize(get_post_meta($product->get_id(), 'frs_woo_product_tabs', true));
        }

        // tab must at least have a title to exist
        return !empty($this->tab_data) && !empty($this->tab_data[0]) && !empty($this->tab_data[0]['title']);
    }

    /**
     * Checks if WooCommerce is active
     *
     * @since  1.0.0
     * @return bool true if WooCommerce is active, false otherwise
     */
    public static function is_woocommerce_active()
    {

        $active_plugins = (array) get_option('active_plugins', array());

        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }

        return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
    }

    /**
     * Renders a notice when WooCommerce is inactive or version is outdated.
     *
     * @since 1.6.0
     */
    public static function render_woocommerce_requirements_notice()
    {

        $message = sprintf(
            /* translators: Placeholders: %1$s - <strong>, %2$s - </strong>, %3$s + %5$s - <a> tags, %4$s - </a> */
            esc_html__('%1$sWooCommerce Custom Product Tabs Lite is inactive.%2$s This plugin requires WooCommerce 2.5.5 or newer. Please %3$sinstall WooCommerce 2.5.5 or newer%4$s, or %5$srun the WooCommerce database upgrade%4$s.', 'woocommerce-custom-product-tabs-lite'),
            '<strong>',
            '</strong>',
            '<a href="' . admin_url('plugins.php') . '">',
            '</a>',
            '<a href="' . admin_url('plugins.php?do_update_woocommerce=true') . '">'
        );

        printf('<div class="error"><p>%s</p></div>', $message);
    }

    /** Lifecycle methods ******************************************************/

    /**
     * Run every time.  Used since the activation hook is not executed when updating a plugin
     *
     * @since 1.0.0
     */
    private function install()
    {

        $installed_version = get_option(self::VERSION_OPTION_NAME);

        // installed version lower than plugin version?
        if (-1 === version_compare($installed_version, self::VERSION)) {
            // new version number
            update_option(self::VERSION_OPTION_NAME, self::VERSION);
        }
    }

}

/**
 * Returns the One True Instance of Custom Product Tabs Lite
 *
 * @since 1.4.0
 * @return \PCBuilder
 */
function wc_custom_product_tabs_lite()
{
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
