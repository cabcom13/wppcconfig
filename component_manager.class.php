<?php 
if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


class My_List_Table extends WP_List_Table {

	function __construct(){
		global $status, $page;                

		parent::__construct( array(
			'singular'  => 'notification',  
			'plural'    => 'notifications',   
			'ajax'      => false      
		) );        
	}
	
	function get_columns(){
	  $columns = array(
		'cb' => 'Kategorie',
		'component_image' => 'Bild',
		'component_status'    => 'Status',
		'component_item_number' => 'Artikelnummer',
		'component_name' => 'Name',
		'components_categories_name' => 'Kategorie',
		'component_purchasing_price'    => 'Einkaufspreis',
		'component_retail_price'      => 'Verkaufspreis'
	  );
	  
	  return $columns;
	}


	public function get_views(){
		
		global $wpdb; //This is used only if making any database queries
		$views = array();	
		
		$database_name = $wpdb->prefix.'components_categories' ;
		$query = "SELECT * FROM $database_name ORDER BY component_categorie_sort ASC";
		$datas =  $wpdb->get_results($query, ARRAY_A );
		$current = ( !empty($_REQUEST['filter']) ? $_REQUEST['filter'] : 'all');


		   //All link
		   $class = ($current == 'all' ? ' class="current"' :'');
		   $all_url = remove_query_arg('filter');
		   $views['all'] = "<a href='{$all_url }' {$class} >Alle</a>";	
		
		foreach($datas as $data){
		   //Foo link
			$title =$data['components_categories_name'];
			$id =$data['components_categories_id'];
			$slug = sanitize_title( $title);

		   $foo_url = add_query_arg('filter',$id);
		   $class = ($current == $id ? ' class="current"' :'');
		   $views[$slug] = "<a href='{$foo_url}' {$class} >$title</a>";
		}
		
	return $views;
	}
	
	
	
	function no_items() {
	_e( 'No books found, dude.' );
	}
  
	function get_sortable_columns() {
	  $sortable_columns = array(
		'cb'        => '<input type="checkbox" />',
		'components_categories_name' =>  array('components_categories_name',false),
		'component_item_number' => array('component_item_number',false),
		'component_status' => array('status',false),
		'component_name'  => array('component_name',false),
		'component_purchasing_price' => array('purchasing_price',false),
		'component_retail_price'   => array('retail_price',false)
	  );
	  return $sortable_columns;
	}	
	function column_component_name($item) {
	  $actions = array(
				'edit'      => sprintf('<a href="?page=%s&action=%s&componentid=%s">Bearbeiten</a>',$_REQUEST['page'],'edit',$item['component_id']),
				'changestate'    => sprintf('<a class="changestate" href="?page=%s&action=%s&componentid=%s">'.($item['component_status'] ? 'Ausschalten':'Einschalten').'</a>',$_REQUEST['page'],'changestate',$item['component_id']),
				'outstock'    => sprintf('<a href="?page=%s&action=%s&componentid=%s">'.($item['component_out_of_stock'] ? 'wieder Lieferbar':'nicht Lieferbar').'</a>',$_REQUEST['page'],'outofstock',$item['component_id']),
				'delete'    => sprintf('<a href="?page=%s&action=%s&componentid=%s">Löschen</a>',$_REQUEST['page'],'delete',$item['component_id']),
			);

	  return sprintf('%1$s %2$s', $item['component_out_of_stock'] ? '<strong style="color:red;">Nicht Lieferbar</strong><br/> '.$item['component_name']:$item['component_name'] , $this->row_actions($actions) );
	}	
	
	function get_bulk_actions() {
	  $actions = array(
		'delete'    => 'Delete'
	  );
	  return $actions;
	}
	
	function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="componentid[]" value="%s" />', $item['component_id']
        );    
	}

	
	function usort_reorder( $a, $b ) {
	  // If no sort, default to title
	  $orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'components_categories_name';
	  // If no order, default to asc
	  $order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
	  // Determine sort order
	  $result = strcmp( $a[$orderby], $b[$orderby] );
	  // Send final sort direction to usort
	  return ( $order === 'asc' ) ? $result : -$result;
	}
	
	function prepare_items() {
		global $wpdb;
	  $customvar = ( isset($_REQUEST['filter']) ? $_REQUEST['filter'] : 'all');

		if($customvar !== 'all'){
			$sql_stat = ' WHERE components_categories_id = '.$customvar;
		}
	
		$database_name = $wpdb->prefix.'components' ;

		$query = "SELECT * FROM $database_name  $sql_stat ORDER BY component_sort DESC";
	
		$datas =  $wpdb->get_results($query, ARRAY_A );	
		
		
	  $columns = $this->get_columns();
	  $hidden = array();
	  $sortable = $this->get_sortable_columns();
	  $this->_column_headers = array($columns, $hidden, $sortable);
	   usort( $datas, array( &$this, 'usort_reorder' ) );
	  $this->items = $datas;
	}

	function column_default( $item, $column_name ) {
		
	  switch( $column_name ) { 
		case 'component_item_number':
			return $item[ $column_name ];
		
		case 'components_categories_name':
			$name = get_term( $item['component_categorie' ],'component_categorie' );
			if(!empty($name->parent)){
				$parent = get_term( $name->parent,'component_categorie' )->name. '/';
			} else {
				$parent = '';
			}
			
			return $parent.$name->name;	
		case 'component_image':
			if(empty($item[ $column_name ])){
				$img = wp_get_attachment_image_src(48, array(50,50), false);
			} else{
				$img = wp_get_attachment_image_src($item[ $column_name ], array(50,50), false);
			}
			
			return '<img width="'.$img[1].'" height="'.$img[2].'" src="'.$img[0].'" />';

		case 'component_status':
		
			 if($item['component_status']){
				return '<i class="fa fa-circle active" aria-hidden="true"></i>'; 
			 } else {
				return '<i class="fa fa-circle inactive" aria-hidden="true"></i>';
			 }
		case 'component_purchasing_price':
			return wc_price($item[ $column_name ]);
		case 'component_retail_price':
			return wc_price($item[ $column_name ]);
		default:
		  return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
	  }
	}	

}

?>