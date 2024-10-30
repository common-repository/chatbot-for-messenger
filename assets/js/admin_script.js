jQuery(document).ready(function($){
	$('#wpfb_reload_pages').on('click', function(e){
		e.preventDefault();
		$('.wpfb_pages_loading').html('Loading...');
		$('.wpfb_pages_loading').show();
		$.post(object.ajax_url, {
            action: 'qcwpfb_reload_pages_action', 
			security:object.ajax_nonce
        }, function(data) {
            var json = $.parseJSON(data);
            if(json.status=='success'){
				$('.wpfb_pages_loading').html('Completed successfully!');
				$('.wpfb_pages_content_area').html(json.content);
			}else{
				$('.wpfb_pages_loading').html('Failed - '+ json.reason);
			}
        });
	})
	
	$('#wpfb_reload_fb_post').on('click', function(e){
		e.preventDefault();
		
		$('.wpfb_posts_load').html('Loading...');
		$('.wpfb_posts_load').show();
		$.post(object.ajax_url, {
            action: 'qcwpfb_reload_posts_action', 
			security:object.ajax_nonce
        }, function(data) {
            var json = $.parseJSON(data);
            if(json.status=='success'){
				$('.wpfb_posts_load').html('Completed successfully!');
				setTimeout(function(){
					
					location.reload();
					
				},800)
			}else{
				$('.wpfb_posts_load').html('Failed - '+ json.reason);
			}
        });
	})
	
	$('#wpfb_condition_add').on('click', function(e){
		e.preventDefault();
		$('.wpfb_logical_container').append('<div class="wpfb_logic_elem"><span>Comment</span><select name="wpfb_condition[]"><option value="1">is equal to</option><option value="2">contains</option></select><input type="text" value="" name="wpfb_condition_value[]" /><a class="button button-secondary wpfb_logic_remove">Remove</a><br>Or</div>');
	})
	
	$('#wpfb_comment_condition_add').on('click', function(e){
		e.preventDefault();
		$('.wpfb_logical_container_comment').append('<div class="wpfb_logic_elem"><span>Comment</span><select name="wpfb_comment_condition[]"><option value="1">is equal to</option><option value="2">contains</option></select><input type="text" value="" name="wpfb_comment_condition_value[]" /><a class="button button-secondary wpfb_logic_remove">Remove</a><br>Or</div>');
	})
	
	$(document).on('click', '.wpfb_logic_remove', function(e){
		
		var obj = $(this);
		if($('.wpfb_logic_elem').length > 1){
			obj.closest('.wpfb_logic_elem').remove();
		}else{
			alert('Last element cannot be deleted!');
		}
		
	})
	
	258

	$('input:radio[name="wpfb_private_reply_condition"]').change(
    function(){
        if ($(this).is(':checked') && $(this).val() == '0') {
            $('.wpfb_logical_container').hide();
			$('#wpfb_condition_add').hide();
        }else{
			$('.wpfb_logical_container').show();
			$('#wpfb_condition_add').show();
		}
    });
	
	$('input:radio[name="wpfb_comment_reply_condition"]').change(
    function(){
        if ($(this).is(':checked') && $(this).val() == '0') {
            $('.wpfb_logical_container_comment').hide();
			$('#wpfb_comment_condition_add').hide();
        }else{
			$('.wpfb_logical_container_comment').show();
			$('#wpfb_comment_condition_add').show();
		}
    });
	

	$('#qc_fb_get_image_url').on('click', function(e){
		e.preventDefault();
		var title = 'Get BroadCast Image';
		
        var image = wp.media({ 
            title: title,
            // mutiple: true if you want to upload multiple files at once
            multiple: false
        }).open()
        .on('select', function(e){
            // This will return the selected image from the Media Uploader, the result is an object
            var uploaded_image = image.state().get('selection').first();
            var image_url = uploaded_image.toJSON().url;
            
			$('#qc_fb_bc_image').val(image_url);
        });
	})
	
	$('#wpfb_campaign_comment_add').on('click', function(e){
		e.preventDefault();
		var obj = $(this);
		obj.parent().find('.wpfb_comment_text_area').append(`<div class="wpfb_campaign_comment_repeatable">
					<textarea name="wpfb_campaign_comment_text[]"></textarea>
					<a class="button button-secondary" id="wpfb_campaign_comment_remove">remove</a>
				</div>`);
	})
	
	jQuery('.wpfb_campaign_start').datetimepicker();
	jQuery('.wpfb_campaign_end').datetimepicker();
	
	$(document).on('click', '#wpfb_campaign_comment_remove', function(e){
		e.preventDefault();
		var obj = $(this);
		obj.parent().remove();
	})
	
})