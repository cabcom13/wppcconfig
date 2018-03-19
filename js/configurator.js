jQuery( document ).ready( function() {



jQuery('#save_configuration').submit(function( event ) {
	event.preventDefault();
			
	jQuery.ajax({
		type: 'POST',
		url: pc_config.ajax_url,
		data: {
			action: 'save_configuration',
			data: jQuery('#test').serialize(),
			name: jQuery(this).serialize()
			}, 
		beforeSend: function(){
			jQuery.LoadingOverlay("show",{
				image       : "",
				color       : "rgba(0, 0, 0, .75)",
				fontawesome : "fa text-white fa-spinner fa-spin fa-fw"
			});
		},
		success: function(data) { 
			jQuery.LoadingOverlay("hide");

			
			jQuery.each(data, function( index, value ) {
			  console.log( index + ": " + value );
			});

		},
		dataType: 'json',
		encode: true
	}); 

	
});



jQuery('.shopping-card').stick_in_parent();
jQuery('.component_image').stick_in_parent();


	jQuery('.show_component_details').on('click', function (event) {
		event.preventDefault();
		var button = jQuery(this) // Button that triggered the modal
		console.log(button.data('component-id'));
		var modal = jQuery('#exampleModal');
		
			jQuery.ajax({
				type: 'POST',
				url: pc_config.ajax_url,
				data: {
					action: 'get_component_detail',
					data: button.data('component-id')
					}, 
				beforeSend: function(){
					jQuery.LoadingOverlay("show",{
						image       : "",
						color       : "rgba(0, 0, 0, .75)",
						fontawesome : "fa text-white fa-spinner fa-spin fa-fw"
					});
				},
				success: function(data) { 
				
					modal.modal('show');
					modal.find('.modal-title').text('Details zu '+data.name);
					
					var body = modal.find('.modal-body');
					var img = jQuery('<img />').prop('src', data.image).appendTo(body);
					console.log(data.gallery);
					if(data.gallery.length != 0){
						body.empty();
						var gal = jQuery('<div />').addClass('owl-carousel owl-theme');
						jQuery.each(data.gallery, function(k,v){
							
							jQuery('<img />').prop({
								'src' : v
							})
							.addClass('img-thumbnail')
							.appendTo(gal);
					
						});
					
						gal.appendTo(body).owlCarousel({
							loop:true,
							items:2,
							margin:10,
							navText: ['<i class="fa fa-arrow-left"></i>','<i class="fa fa-arrow-right"></i>'],
							center: true,
							nav:true,
							onInitialize:function(){
								gal.hide();
							},
							onInitialized:function(){
								gal.show();
							},
							onRefreshed: function(){
								jQuery.LoadingOverlay("hide");
								
							}
						});
						
						jQuery('<div />').html(data.component_descripion).appendTo(body);
						 
						
					} else {
						jQuery.LoadingOverlay("hide");
						body.empty().html('<div class="row"><div class="col-4"><img class="img-thumbnail" src="'+data.image+'" /> </div><div class="col-8">'+data.component_descripion+'</div></div>');
					}

	
					
				
				
				
				},
				dataType: 'json',
				encode: true
			}); 
		
		
	});

	jQuery('#test input:radio').change(function () {
			$_this_radio = jQuery(this);

				jQuery.ajax({
					type: 'POST',
					url: pc_config.ajax_url,
					data: {
						action: 'change_pc_configuration',
						data: jQuery('#test').serialize()
						}, 
					beforeSend: function(){
						jQuery('#_total_price').addClass('loading_animation');
						jQuery.LoadingOverlay("show",{
							image       : "",
							color       : "rgba(0, 0, 0, .75)",
							fontawesome : "fa text-white fa-square fa-spin fa-fw"
						});
					},
					success: function(data) { 
						jQuery.LoadingOverlay("hide");
					
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
									jQuery('.config_thumbnail_'+value.cat_data.term_id).prop("src",img_src );
									
								}
								//console.log(v.is_selected);
								
								//config_thumbnail_
								//jQuery('.price_'+value.cat+'_'+v.id).html(v.price.formated);
							});

							jQuery('#heading_'+value.cat_data.term_id+ ' .selected_name').html(value.selected_component_name);
							if(value.selected_component_name == 'nichts Ausgewählt'){
								jQuery('#heading_'+value.cat_data.term_id+ ' .selected_name').addClass('nothing_selected');
							} else {
								jQuery('#heading_'+value.cat_data.term_id+ ' .selected_name').removeClass('nothing_selected');
							}
								//
						});
						jQuery('#_total_price').removeClass('loading_animation');
						jQuery('#_total_price').html(data.total_price.formated); 
						
					},
					dataType: 'json',
					encode: true
				}); 
				
			
			

	});
 
});