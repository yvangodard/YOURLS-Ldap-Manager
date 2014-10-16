$(function(){

/**
 * Ajout d'un serveur - Affichage du formulaire
 */
	$('#add-serveur-link').on('click', function(e){
		e.preventDefault();
		$('#ServeurAddForm').parent().slideToggle();
	});
	
/**
 * Ajout de serveur - Validation du formulaire
 */		
	$('#ServeurAddForm').validate({
		 submitHandler: function(form) {
			 $.ajax({
					type: "POST",
					url: ajaxurl,
					dataType:'json',
					success:function(json){
						if(json.result == true){
							$('#flash-notify-success').fadeIn().delay(3000).fadeOut();
							reload_table_serveurs();
							$('#ServeurAddForm').parent().slideToggle();
						} else {
							$('#flash-notify-error').fadeIn().delay(3000).fadeOut();
						}
					},
					data:$('#ServeurAddForm').serialize()
				});
		},
		errorPlacement: function(error, element) {}
	});

/**
* Modification d'un serveur - Link pour formulaire
*/
	$(document).on('click', '.edit-serveur-link', function(e){
		e.preventDefault();
		
		$('#table-serveurs a').removeClass('active');
		$(this).addClass('active');
		
		var id = $(this).parent().parent().attr('id').split('-')[1];
		
		$.ajax({
			type: "POST",
			url: ajaxurl,
			complete:function(request, json){
				$('#ajax-edit-serveur').hide().html(request.responseText).fadeIn('slow');
			},
			data:{id:id,action:'edit_serveur'}
		});
	});	
	
/**
 * Edition d'un serveur - Soumission du formulaire
 */	
	$(document).on('click', '#serveur-edit-submit', function(e){
		$('#ServeurEditForm').validate({
			 submitHandler: function(form) {
				 				 
				 $.ajax({
						type: "POST",
						url: ajaxurl,
						dataType:'json',
						success:function(json){
							if(json.result == true){
								$('#flash-notify-success').fadeIn().delay(3000).fadeOut();
								reload_table_serveurs();
							} else {
								$('#flash-notify-error').fadeIn().delay(3000).fadeOut();
							}
						},
						data:$('#ServeurEditForm').serialize()
					});
			},
			errorPlacement: function(error, element) {}
		});	
	});
	
	
/**
 * Suppression d'un serveur
 */
	$(document).on('click', '.delete-serveur-link', function(e){
		
		var id = $(this).parent().parent().attr('id').split('-')[1];
		
		if(confirm('Etes-vous sûr de vouloir supprimer ce serveur ?')){

			$.ajax({
				type: "POST",
				url: ajaxurl,
				dataType:'json',
				success:function(json){
					if(json.result == true){
						$('#flash-notify-success').fadeIn().delay(3000).fadeOut();
						reload_table_serveurs();
						$('#ajax-edit-serveur').fadeOut();
					} else {
						$('#flash-notify-error').fadeIn().delay(3000).fadeOut();
					}
				},
				data:{id:id,action:'delete_serveur'}
			});
		}
	});
	
	
/**
 * Rechargement du tableau des serveurs
 */	
	function reload_table_serveurs(){
		$('#ajax-loader').show();
		
		$.ajax({
			type: "POST",
			url: ajaxurl,
			complete:function(request, json){
				$('#ajax-serveurs').html(request.responseText);
			},
			data:{action:'load_table_serveurs', 'active_id' : $('#ServeurEditForm input[name="id"]').val()}
		});
	}
});