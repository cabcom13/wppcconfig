<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class My_List_Table extends WP_List_Table
{

    public function __construct()
    {
        global $status, $page;

        parent::__construct(array(
            'singular' => 'notification',
            'plural' => 'notifications',
            'ajax' => false,
        ));
    }

    public function get_columns()
    {
        $columns = array(
            'cb' => 'Kategorie',
            'component_image' => 'Bild',
            'component_status' => 'Status',
            'component_item_number' => 'Artikelnummer',
            'component_build_in_count' => 'Eingebaut (Buildin)',
            'component_name' => 'Name',

            'components_categories_name' => 'Kategorie',
            'component_purchasing_price' => 'Einkaufspreis',
            'component_retail_price' => 'Verkaufspreis',
        );

        return $columns;
    }

    public function get_views()
    {

        global $wpdb; //This is used only if making any database queries
        $views = array();

        $categories_terms = get_terms(array(
            'taxonomy' => 'component_categorie',
            'parent' => 0,
            'hide_empty' => false,
        ));
        #print_r($categories_terms);
        $current = (!empty($_REQUEST['filter']) ? $_REQUEST['filter'] : 'all');

        //All link
        $class = ($current == 'all' ? ' class="current"' : '');
        $all_url = remove_query_arg('filter');
        $views['all'] = "<a href='{$all_url}' {$class} >Alle</a>";

        foreach ($categories_terms as $data) {
            //Foo link
            $title = $data->name;
            $id = $data->term_id;
            $slug = $data->slug;

            $foo_url = add_query_arg('filter', $id);
            $class = ($current == $id ? ' class="current"' : '');
            $views[$slug] = "<a href='{$foo_url}' {$class} >$title</a>";
        }

        return $views;
    }

    public function no_items()
    {
        _e('No books found, dude.');
    }

    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'cb' => '<input type="checkbox" />',
            'components_categories_name' => array('components_categories_name', false),
            'component_item_number' => array('component_item_number', false),
            'component_status' => array('status', false),
            'component_name' => array('component_name', false),
            'component_purchasing_price' => array('purchasing_price', false),
            'component_retail_price' => array('retail_price', false),
        );
        return $sortable_columns;
    }
    public function column_component_name($item)
    {
        $ibi = $this->in_build_in($item['component_id']) == 0 ? true : false;

        $actions = array(
            'edit' => sprintf('<a href="?page=%s&action=%s&componentid=%s">Bearbeiten</a>', $_REQUEST['page'], 'edit', $item['component_id']),

            'outstock' => sprintf('<a href="?page=%s&action=%s&componentid=%s">' . ($item['component_out_of_stock'] ? 'wieder Lieferbar' : 'nicht Lieferbar') . '</a>', $_REQUEST['page'], 'outofstock', $item['component_id']),

        );
        if ($ibi) {
            $actions['changestate'] = sprintf('<a class="changestate" href="?page=%s&action=%s&componentid=%s">' . ($item['component_status'] ? 'Ausschalten' : 'Einschalten') . '</a>', $_REQUEST['page'], 'changestate', $item['component_id']);
            $actions['delete'] = sprintf('<a href="?page=%s&action=%s&componentid=%s">Löschen</a>', $_REQUEST['page'], 'delete', $item['component_id']);
        } else {
            $actions['delete'] = 'Löschen nicht möglich da eingebaut';
        }

        return sprintf('%1$s %2$s', $item['component_out_of_stock'] ? '<strong style="color:red;">Nicht Lieferbar</strong><br/> ' . $item['component_name'] : $item['component_name'], $this->row_actions($actions));
    }

    public function get_bulk_actions()
    {
        $actions = array(
            'delete' => 'Delete',
        );
        return $actions;
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="componentid[]" value="%s" />', $item['component_id']
        );
    }

    public function usort_reorder($a, $b)
    {
        // If no sort, default to title
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'components_categories_name';
        // If no order, default to asc
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
        // Determine sort order
        $result = strcmp($a[$orderby], $b[$orderby]);
        // Send final sort direction to usort
        return ($order === 'asc') ? $result : -$result;
    }

    public function prepare_items()
    {
        global $wpdb;
        $customvar = (isset($_REQUEST['filter']) ? $_REQUEST['filter'] : 'all');

        $categories_terms = get_terms(array(
            'taxonomy' => 'component_categorie',
            'parent' => $customvar,
            'hide_empty' => false,
        ));
        $sql_stat_ex = '';
        foreach ($categories_terms as $ct) {
            $sql_stat_ex .= ' OR component_categorie = ' . $ct->term_id;

        }

        if ($customvar !== 'all') {
            $sql_stat = ' WHERE (component_categorie = ' . $customvar . $sql_stat_ex . ')';
        }

        $database_name = $wpdb->prefix . 'components';

        $query = "SELECT * FROM $database_name  $sql_stat ORDER BY component_categorie DESC";

        $datas = $wpdb->get_results($query, ARRAY_A);

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        usort($datas, array(&$this, 'usort_reorder'));
        $this->items = $datas;
    }

    public function in_build_in($component_id)
    {
        $args = array(
            'post_type' => 'product',
        );

        $loop = new WP_Query($args);
        $a = 0;
        while ($loop->have_posts()): $loop->the_post();
            global $product;

            $x = get_post_meta(get_the_ID(), 'components');

            if (is_array($x[0]['buildin'])) {
                if (in_array($component_id, $x[0]['buildin'])) {
                    $a++;
                }
            }
            #print_r($x[0]['buildin']);
            #echo '<br /><a href="'.get_permalink().'">' . woocommerce_get_product_thumbnail().' '.get_the_title().'</a>';
        endwhile;

        wp_reset_query();
        return $a;
    }

    public function column_default($item, $column_name)
    {

        switch ($column_name) {
            case 'component_item_number':
                return $item[$column_name];

            case 'components_categories_name':
                $name = get_term($item['component_categorie'], 'component_categorie');
                if (!empty($name->parent)) {
                    $parent = get_term($name->parent, 'component_categorie')->name . '/';
                } else {
                    $parent = '';
                }

                return $parent . $name->name;
            case 'component_image':
                if (empty($item[$column_name])) {
                    $img = wp_get_attachment_image_src(48, array(50, 50), false);
                } else {
                    $img = wp_get_attachment_image_src($item[$column_name], array(50, 50), false);
                }

                return '<img width="' . $img[1] . '" height="' . $img[2] . '" src="' . $img[0] . '" />';

            case 'component_status':

                if ($item['component_status']) {
                    return '<i class="fa fa-circle active" aria-hidden="true"></i>';
                } else {
                    return '<i class="fa fa-circle inactive" aria-hidden="true"></i>';
                }
            case 'component_purchasing_price':
                return wc_price($item[$column_name]);
            case 'component_retail_price':
                return wc_price($item[$column_name]);

            case 'component_build_in_count':

                return $this->in_build_in($item['component_id']);

                break;

            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

}
