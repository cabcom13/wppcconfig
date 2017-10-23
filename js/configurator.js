jQuery( document ).ready( function() {

	jQuery('#test input:radio').change(function () {
			$_this_radio = jQuery(this);
			console.log($_this_radio.data('categorie-id'));
		
				console.log('submit');
				jQuery.ajax({
					type: 'POST',
					url: pc_config.ajax_url,
					data: {
						action: 'change_pc_configuration',
						data: jQuery('#test').serialize()
						}, 
					beforeSend: function(){
						jQuery('#_total_price').addClass('loading_animation');
						
					},
					success: function(data) { 
				
					
						jQuery('#components_overview').empty();
						jQuery.each( data.components, function( key, value ) {
							console.log(value);
							jQuery.each( value.data, function( k, v ) {
								
								
								// set Image
								if(v.is_selected){
									jQuery('<strong></strong>').text(value.cat_data.name).appendTo(jQuery('#components_overview'));
									jQuery('<div></div>').text(v.component_name).appendTo(jQuery('#components_overview'));
									
									if(v.component_image[0] != ''){
										var img_src = v.component_image[0];
									} else {
										var img_src = 'nix';
									}
									//console.log(img_src);
									jQuery('.config_thumbnail_'+value.cat).prop("src",img_src );
									
								}
								//console.log(v.is_selected);
								
								//config_thumbnail_
								//jQuery('.price_'+value.cat+'_'+v.id).html(v.price.formated);
							});
							console.log('.heading_'+value.cat);
							
							jQuery('#heading_'+value.cat+ ' .selected_name').html(value.selected_component_name);
							if(value.selected_component_name == 'nichts Ausgewählt'){
								jQuery('#heading_'+value.cat+ ' .selected_name').addClass('nothing_selected');
							} else {
								jQuery('#heading_'+value.cat+ ' .selected_name').removeClass('nothing_selected');
							}
								//
						});
						jQuery('#_total_price').removeClass('loading_animation');
						jQuery('#_total_price').html(data.total_price.formated +' incl. '+ data.total_price.taxrate); 
						
					},
					dataType: 'json',
					encode: true
				}); 
				
			
			

	});
 
});