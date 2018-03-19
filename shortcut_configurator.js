jQuery( document ).ready( function( $ ) {
	
	jQuery('.mark_all_categories').on('click' , function(e){
		e.preventDefault();
		jQuery('input[name="components[available]['+jQuery(this).data('cat-id')+'][]"]').prop('checked', 'checked');
	});
	
	jQuery('.unmark_all_categories').on('click' , function(e){
		e.preventDefault();
		jQuery('input[name="components[available]['+jQuery(this).data('cat-id')+'][]"]').prop('checked', '');
	});
	
	jQuery('.mark_all').on('click' , function(e){
		e.preventDefault();
		jQuery('#items input[type="checkbox"]').prop('checked', 'checked');
	});	
	jQuery('.unmark_all').on('click' , function(e){
		e.preventDefault();
		jQuery('#items input[type="checkbox"]').prop('checked', '');
	});		
	
});