<?php 
function calc_component_prices($product_id, $selected_components = array(), $return = 'array'){
	  global $woocommerce;

	  $product = wc_get_product($product_id );
	  $ava = get_post_meta( $product_id, 'components');
	  #echo '<pre>';
	  #print_r($ava);
	  #echo '</pre>';
	  $selected_data = array();
	  $total_price = $product->get_price();
	  $return_ar = array();

		$post_data  = $selected_components;
		
		$compo_list = array();
	  foreach($ava[0]['available'] as $key => $components){
			$y = array();
			$selected_image = '';
			foreach($components as $component){
				
				$com_data = get_component_by_ID($component);
					
				if($ava[0]['buildin'][$key] == $com_data->component_id){	
					$selected_price_id = $com_data->component_id;	
				}
			
				if($post_data['selected'][$key] == $com_data->component_id){
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
						$calc_price = 0;
					  }  
				}

				$tax_rates    = WC_Tax::get_rates( $product->get_tax_class() );
				$taxes        = WC_Tax::calc_tax($calc_price, $tax_rates, false );
				$tax_amount   = WC_Tax::get_tax_total( $taxes );
				$price 		  = round( $calc_price + $tax_amount, wc_get_price_decimals() ); 

				if(is_array($post_data['selected'])){
						if($post_data['selected'][$key] == $com_data->component_id){
							$_is_selected = true;
						} else {
							$_is_selected = false;
						}
				}	else {
					if($ava[0]['buildin'][$key] == $com_data->component_id){
						$_is_selected = true;
						$_is_buildin = true;
					} else {
						$_is_selected = false;
						$_is_buildin = false;
					}
				}
				

				if($_is_buildin){
					$price = 0;
					$selected_image = $_compo_image;
					$selected_name = $com_data->component_name;
					#array_push($compo_list , $com_data->component_name); 
				}
				if($_is_selected){
					if(get_component_price_by_ID($com_data->component_id) == 0){
						$price = 0;
					}
					$selected_name = $com_data->component_name;
					#array_push($compo_list , $com_data->component_name); 
					if(!empty($com_data->component_image)){
						$_compo_image = wp_get_attachment_image_src($com_data->component_image);
					} else {
						$_compo_image = array(plugins_url().'/pcbuilder/img/noimage.png', 150,150);
					} 
					$selected_image = $_compo_image;
					
					$total_price += $price;		
					
				}
				
				if($_is_buildin){
					
					$subterm = get_term($com_data->component_categorie,'component_categorie');
					if($subterm->parent == 0){
					array_unshift($y, array(
						'id' => $com_data->component_id,
						'component_name' => $com_data->component_name,
						'cat-id' => $com_data->component_categorie,
						'component_image' => $_compo_image,
						'component_status' => $com_data->component_status,
						'component_out_of_stock' =>  $com_data->component_out_of_stock,
						'is_build_in' => $_is_buildin,
						'is_selected' => $_is_selected,
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
					} else {
						array_push($y, array(
							'id' => $com_data->component_id,
							'component_name' => $com_data->component_name,
							'cat-id' => $com_data->component_categorie,
							'component_image' => $_compo_image,
							'component_status' => $com_data->component_status,
							'component_out_of_stock' =>  $com_data->component_out_of_stock,
							'is_build_in' => $_is_buildin,
							'is_selected' => $_is_selected,
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
					}
					

				} else {
					
					$subterm = get_term($com_data->component_categorie,'component_categorie');
					if($subterm->parent == 0){
						array_unshift($y, array(
							'id' => $com_data->component_id,
							'component_name' => $com_data->component_name,
							'cat-id' => $com_data->component_categorie,
							'component_image' => $_compo_image,
							'component_status' => $com_data->component_status,
							'component_out_of_stock' =>  $com_data->component_out_of_stock,
							'is_build_in' => $_is_buildin,
							'is_selected' => $_is_selected,
							'purchasing_price' =>  $com_data->component_purchasing_price,
							'retail_price' => $com_data->component_retail_price,
							'price' =>  array(
								'formated' => wc_price( $price),
								'natural' => $price,
								'tax' => array(
									'formated' => wc_price($taxes[1]),
									'natural' => $taxes[1]
								)
							),
							'prefix' => $price >= 0 ? '+':''
						));		
					} else {
						array_push($y, array(
							'id' => $com_data->component_id,
							'component_name' => $com_data->component_name,
							'cat-id' => $com_data->component_categorie,
							'component_image' => $_compo_image,
							'component_status' => $com_data->component_status,
							'component_out_of_stock' =>  $com_data->component_out_of_stock,
							'is_build_in' => $_is_buildin,
							'is_selected' => $_is_selected,
							'purchasing_price' =>  $com_data->component_purchasing_price,
							'retail_price' => $com_data->component_retail_price,
							'price' =>  array(
								'formated' => wc_price( $price),
								'natural' => $price,
								'tax' => array(
									'formated' => wc_price($taxes[1]),
									'natural' => $taxes[1]
								)
							),
							'prefix' => $price >= 0 ? '+':''
						));		
					}

					
				}
				


			}
			
			$total_price_clear = $total_price;
			$tax_rates    = WC_Tax::get_rates( $product->get_tax_class() );
			$taxes        = WC_Tax::calc_tax($total_price, $tax_rates, false );
			$tax_amount   = WC_Tax::get_tax_total( $taxes );
			$total_price_re  = round( $total_price +$tax_amount , wc_get_price_decimals() ); 
			#echo $selected_image[0].'<br />';
			
			$t = get_term($key);
			$t_ID = $t->term_id;
			
			array_push($selected_data, array(
				#'cat' => $key,
				'selected_price_id' => $selected_price_id,
				'selected_image' => $selected_image,
				'selected_component_name' => $selected_name,
				#'cat_data' => $t,
				#'cat_options' => get_option("taxonomy_$t_ID"),
				'data' => $y,

			)); 
	  }

	$return_ar = array(
		'components' => $selected_data,
		'total_price' =>  array(
			'formated' =>wc_price($total_price_re),
			'natural' => $total_price,
			'taxless' => $total_price - $taxes[1],
			'taxrate' => round($tax_rates[1]['rate'],0).'% '.$tax_rates[1]['label'] ,
			'tax' => array(
				'formated' => wc_price($taxes[1]),
				'natural' => $taxes[1]
			)
		)
	);
	  
	if($return == 'array'){
		return $return_ar;
	} else if($return == 'json'){
		header('Content-Type: application/json');
		#print_r($return_ar);
		echo json_encode($return_ar);
		exit;
	}
	
}

?>