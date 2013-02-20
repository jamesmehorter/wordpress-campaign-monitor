<?php
	/*
		Plugin Name: Campaign Monitor
		Plugin URI: https://github.com/jamesmehorter/wordpress-campaign-monitor
		Description: Campaign Monitor Plugin for WordPress
		Version: 0.1 Beta
		Author: James Mehorter
		Author URI: http://www.jamesmehorter.com
		Copyright 2013 James Mehorter (email : jamesmehorter@gmail.com)

		USEAGE: [campaign-monitor list_id=f55fcf20b042842b92ab4c45a1b7456a]
	*/

	########################################
	# 
	#	SCRIPTS & STYLES
	#
	########################################

	//Load the theme styles and scripts scripts
	add_action('wp_enqueue_scripts', 'campaign_monitor_styles_and_scripts');
	function campaign_monitor_styles_and_scripts() {
		//Scripts
		wp_register_script('campaign-monitor', plugins_url('scripts/campaign-monitor.js', __FILE__), 'jquery', '', true);
		wp_enqueue_script('campaign-monitor');

		//Script Variables
		$campaign_monitor_js_settings = array('site_url' => get_bloginfo('url'));
		wp_localize_script('campaign-monitor', 'campaign_monitor', $campaign_monitor_js_settings );

		//Styles
		wp_register_style('campaign-monitor', plugins_url('stylesheets/campaign-monitor.css', __FILE__));
		wp_enqueue_style('campaign-monitor');
	}

	########################################
	# 
	#	SHORTCODES
	#
	########################################

	add_shortcode('campaign-monitor', 'campaign_monitor_shortcode');
	function campaign_monitor_shortcode ($atts) { 
		//We'll use Output buffering here to keep the code below cleaner and easier to maintain
		ob_start(); 
		?>
		<form class="campaign-monitor">
			<p>Fill out of the form below and click 'Subscribe' to begin receiving the InPulse newsletter.</p>
			<input type="text" value="Your Name" name="Name" /><br />
			<input type="email" value="Email Address" name="Email" /><br /><?php
				//Display any custom fields for the subscriber list
				//Load the Campaign Monitor API 
				include('api/csrest_lists.php');
				//474c5ff0c27ac3030c897b8e68e5efd3 is Pica's API Key
				//The subscriber list is passed in as an argument to the shortcode
				$subscriber_list = new CS_REST_Lists($atts['list_id'], '474c5ff0c27ac3030c897b8e68e5efd3');
				//Select the list custom fields
				$custom_fields = $subscriber_list->get_custom_fields();
				if (is_array($custom_fields->response) && !empty($custom_fields->response)) : 
					//The following dynamic field generation only works for text fields, we'll need to build it out for select or radio
					foreach ($custom_fields->response as &$custom_field) : 
						$custom_field->Key = str_replace('[', '', $custom_field->Key);
						$custom_field->Key = str_replace(']', '', $custom_field->Key); ?>

			<input type="<?php echo $custom_field->DataType ?>" name="CustomFields[<?php echo $custom_field->Key ?>]" value="<?php echo $custom_field->FieldName ?>" /><br /><?php 
					endforeach;
				endif; ?>

			<br />
			Please answer the following math question:<br />
			<small>(This helps to eliminate spam from our system)</small><br />
			3 + 4 = <input name="Captcha" class="captcha" type="text" />
			<br />
			<input type="hidden" name="Listid" value="<?php echo $atts['list_id'] ?>" />
			<input class="submit" type="submit" value="Subscribe" />
			<div class="subscription-loader"></div>
			<div class="clear"></div>
			<div class="subscription-output"></div>
		</form>
		<?php 
		//Grab the contents of the output buffer
		$html_output = ob_get_contents() ;
		//Clear the output buffer
		ob_end_clean();
		return $html_output;
	}//campaign_monitor_shortcode()

	########################################
	# 
	#	AJAX OPERATIONS
	#
	########################################

	//Process new Campaign Monitor Subscriptions - add_subscriber is the 'action' used in the $.ajax() call
	add_action('wp_ajax_nopriv_campaign_monitor_add_subscriber', 'campaign_monitor_add_subscriber');
	add_action('wp_ajax_campaign_monitor_add_subscriber', 'campaign_monitor_add_subscriber');

	//Add new Campaign Monitor subscribers
	//This function is triggered via ajax when users submit the subscription form on the blog sidebar
	function campaign_monitor_add_subscriber () {
		//Load the Campaign Monitor API 
		include('api/csrest_subscribers.php');
		//Connect to the CM API with the GO Logic subscriber list ID and API key
		/*
			Pica Design CM API Key: 474c5ff0c27ac3030c897b8e68e5efd3
			The List ID is sent through the ajax request from the shortcode, populated as an argument to the shortcode
		*/
		$wrap = new CS_REST_Subscribers($_REQUEST['Listid'], '474c5ff0c27ac3030c897b8e68e5efd3');
		//Check if the user is already subscribed to this list
		$check_already_subscribed = $wrap->get($_REQUEST['Email']);
		if ($check_already_subscribed->response->State == 'Active') : 
			//The user is already in the chosen list
			$status = 1;
			$message = "You are already subscribed to this newsletter.";
		else :
			//The user is not in the chosen list yet, let's add them..
			$CustomFields = array();
			//Build the CustomFields array if there are any
			foreach ($_REQUEST['CustomFields'] as $key => $value) :
				$CustomFields[] = array(
					'Key' => "$key",
					'Value' => $value
				);
			endforeach;

			$subscriber_arguments = array(
				'EmailAddress' => $_REQUEST['Email'],
				'Name' => $_REQUEST['Name'],
				'CustomFields' => $CustomFields,
				'Resubscribe' => true
			);

			//Attempt to add a new subscriber 
			$add_subscriber_result = $wrap->add($subscriber_arguments);

			//Acertain the status of the request
			if($add_subscriber_result->was_successful()) :
				$status = 2;
				$message = "Thanks for subscribing! You'll now begin to receive this newsletter.";
			else :
				$status = 0;
				$message = $add_subscriber_result->response->Message;
			endif;
		endif;
		//Return the data we need to display a message to users after signing up
		echo json_encode(array('status' => $status, 'message' => $message));
		die(); //This call ensures wp doesn't output any additional text to the ajax response
	}//END add_enews_subscriber
?>