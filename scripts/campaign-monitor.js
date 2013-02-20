(function(){
	//Loop through each input in the form
	$('form.campaign-monitor').find('input').each(function(){
		//Make sure we're not affecting hidden and submit inputs
		if ($(this).attr('type') != 'hidden' && $(this).attr('type') != 'submit') {
			//Store the input value in data for later use
			$(this).data('title', $(this).val())
			//Enable an auto clearing of the value on focus and a repopulation of the value on blur
			$(this).focus(function(){
				if ($(this).val() == $(this).data('title')) { $(this).val('') }
			}).blur(function(){
				if ($(this).val() == '') { $(this).val($(this).data('title')) }
			})
		}
		//For the submit input we want to bind a click event (we're using AJAX to POST not HTTP)
		if ($(this).attr('type') == 'submit') {
			$(this).click(function(event){
				event.preventDefault()

				//Form Field Validation Failure Flags
				var submitForm = true
				var error = ""
				var output_container = $('.subscription-output');

				//Reset any previous form field errors | They will be set again if the field still fails validation
				output_container.removeClass('').addClass('subscription-output').html('')
				$('input.invalid').removeClass('invalid')
				
				//Check for empty fields in the form
				$('.enews-subscription-form').find(':input').each(function(i){
					//First make sure all the fields have been filed out, and that they don't contain the blur / title text
					if ($(this).val() == "" || $(this).val() == $(this).data('title')) {
						$(this).addClass('invalid')
						submitForm = false
						error = "Please fill in all the fields<br />";
					} else {
						//The field does have a value

						//Next check that the email field contains a valid email address
						if ($(this).attr('name') == 'Email') {
							//Check that it is a valid email address
							if (!is_email_address($(this).val())) {
								$(this).addClass('invalid')
								submitForm = false
								error += "Please enter a valid email address<br />";
							}
						}

						//Lastly, check that the simple captcha has been entered correctly
						//NOTE: This is by no means secure!! It simply stops automated submission by bots and such (using js alone for submission helps, but this adds another layer)
						//If someone digs into this code and finds the secret.. good for you :) OR you could of just added 3 + 4 :\ 
						if ($(this).attr('name') == 'Captcha') {
							if ($(this).val() != 7) {
								submitForm = false
								$(this).addClass('invalid')
								error += "Please enter the sum of the math problem<br />";
							}
						}
					}
				})
				
				//Check if the validity loop failed
				if (!submitForm) {	
					output_container.addClass('fail').html(error)
				} else {
					//Display the loading animation
					$('.subscription-loader').fadeIn(500)

					//POST the AJAX request
					$.ajax({
						url: campaign_monitor.site_url + '/wp-admin/admin-ajax.php',
						type: 'POST',
						data: $('form.campaign-monitor').serialize() + '&action=campaign_monitor_add_subscriber', 
						dataType: 'json',
						success: function(response) {
							//hide the loading animation
							$('.subscription-loader').fadeOut(500)

							switch (response.status) {
								case 0: 
									//submission invalid
									output_container.removeClass('success').addClass('fail');
								break;
								case 1:
									//submission successful - already subscribed
									output_container.removeClass('fail').addClass('success');				
								break;
								case 2:
									//submission successful - added new subscriber
									output_container.removeClass('fail').addClass('success');				
								break;
							}
							output_container.html(response.message);
						}
					}) //END AJAX
				}//end if !submitForm
			})//end .click
		}
	})

	//Helper functions

	//Is Valid Email Address
	//supply email address, return BOOL
	function is_email_address(email) {
		var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
		return regex.test(email);
	}
})(jQuery)