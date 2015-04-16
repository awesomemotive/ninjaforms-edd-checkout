<?php
/**
 * Plugin Name: Ninja Forms - EDD
 * Plugin URI: 
 * Description: 
 * Author: Andrew Munro
 * Author URI: 
 * Version: 1.0
 * Domain Path: languages
 */

/**
 * Redirect to EDD checkout after form submission
 *
 * @since 1.0
 */
function ninja_forms_edd_checkout_redirect() {

	global $ninja_forms_processing;
	
	// get settings
	$settings   = $ninja_forms_processing->get_all_form_settings();

	// is ajax enabled
	$ajax_enabled = isset( $ninja_forms_processing->data['form']['ajax'] ) ? $ninja_forms_processing->data['form']['ajax'] : '';
	
	// send this form to EDD checkout?
	$send_to_edd = isset( $settings['ninja_forms_send_to_edd'] ) ? $settings['ninja_forms_send_to_edd'] : '';

	// return if this form shouldn't send to EDD checkout
	if ( ! $send_to_edd ) {
		return;
	}

	if ( $ajax_enabled ) {
		ninja_forms_edd_process_form();
		return;
	}

	// process form
	ninja_forms_edd_process_form();

	// redirect non-ajax form
	wp_redirect( edd_get_checkout_uri() );
	exit;

}
add_action( 'nf_save_sub', 'ninja_forms_edd_checkout_redirect' );

/**
 * Process form
 *
 * @since 1.0
 */
function ninja_forms_edd_process_form() {
	
	global $ninja_forms_processing;

	$settings   = $ninja_forms_processing->get_all_form_settings();
	$form_title = isset( $ninja_forms_processing->data['form']['form_title'] ) ? $ninja_forms_processing->data['form']['form_title'] : '';

	// get basic user info if set for EDD checkout
	$user_info     = $ninja_forms_processing->get_user_info();
	$first_name    = isset( $user_info['first_name'] ) ? $user_info['first_name'] : '';
	$email_address = isset( $user_info['email'] ) ? $user_info['email'] : '';

	// use fee label from settings, or use form title as fallback
	$fee_label = ! empty( $settings['ninja_forms_edd_fee_label'] ) ? $settings['ninja_forms_edd_fee_label'] : $form_title;
	
	// get total
	$total = $ninja_forms_processing->get_calc_total();
	
	if ( is_array ( $total ) ) {
		// If this is an array, grab the string total.
		if ( isset ( $total['total'] ) ) {
			$purchase_total = $total['total'];
		} else {
			$purchase_total = '';
		}
	} else {
		// This isn't an array, so $purchase_total can just be set to the string value.
		if ( ! empty( $total ) ) {
			$purchase_total = $total;
		} else {
			$purchase_total = 0.00;
		}
		
	}

	// return if no total
	if ( 0.00 == $purchase_total ) {
		return;
	}

	// set total
	if ( $purchase_total ) {
		EDD()->session->set( 'ninja_forms_total', $purchase_total );
	}
	
	// set fee label
	if ( $fee_label ) {
		EDD()->session->set( 'ninja_forms_fee_label', $fee_label );
	}

	// set first name
	if ( $first_name ) {
		EDD()->session->set( 'ninja_forms_first_name', $first_name );
	}

	// set email address
	if ( $email_address ) {
		EDD()->session->set( 'ninja_forms_email_address', $email_address );
	}

}


/**
 * Add fee at EDD checkout
 *
 * @since 1.0
 */
function ninja_forms_edd_add_fee() {
	
	// make sure we're on EDD checkout
	if ( ! edd_is_checkout() ) {
		return;
	}

	$amount = EDD()->session->get( 'ninja_forms_total' );
	$label  = EDD()->session->get( 'ninja_forms_fee_label' );

	// there's an amount and label, add our fee
	if ( $amount ) {

		EDD()->fees->add_fee( 
			array(
				'amount' => $amount,
				'label'  => $label,
				'id'     => 'ninja_forms_edd_fee',
				'type'   => 'item'
			)
		);

	}	

	// remove fee from session
	EDD()->session->set( 'ninja_forms_total', '' );

	// remove fee label from session
	EDD()->session->set( 'ninja_forms_fee_label', '' );

}
add_action( 'template_redirect', 'ninja_forms_edd_add_fee' );


/**
 * Pre-populate EDD checkout fields with values from form
 *
 * @since       1.0
 * @return      void
 */
function ninja_forms_edd_pre_populate_checkout_fields() {
	// get user info
	$first_name    = EDD()->session->get( 'ninja_forms_first_name' );
	$email_address = EDD()->session->get( 'ninja_forms_email_address' );

	// return if either of these fields don't exist
	if ( ! ( $first_name || $email_address ) ) {
		return;
	}

	?>

	<script>
	jQuery(document).ready(function($) {

		<?php if ( $first_name ) : ?>
		$('input[name=edd_first]').val('<?php echo $first_name; ?>');
		<?php endif; ?>
		
		<?php if ( $email_address ) : ?>
		$('input[name=edd_email]').val('<?php echo $email_address; ?>');
		<?php endif; ?>
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ninja_forms_edd_pre_populate_checkout_fields' );

/**
 * Register the form-specific settings
 *
 * @since       1.0
 * @return      void
 */
function ninja_forms_edd_add_form_settings() {

	if ( ! function_exists( 'ninja_forms_register_tab_metabox_options' ) ) {
		return;
	}

	$args = array();
	$args['page'] = 'ninja-forms';
	$args['tab']  = 'form_settings';
	$args['slug'] = 'basic_settings';
	$args['settings'] = array(
		array(
			'name'      => 'ninja_forms_send_to_edd',
			'type'      => 'checkbox',
			'label'     => __( 'Send To Easy Digital Downloads', 'ninja-forms-edd' ),
			'desc'      => __( 'Send form to EDD checkout?', 'ninja-forms-edd' ),
			'help_text' => __( 'This will send the form and it\'s total to EDD checkout ', 'ninja-forms-edd' ),
		),
		array(
			'name'    => 'ninja_forms_edd_fee_label',
			'label'   => __( 'Fee Label', 'ninja-forms-edd' ),
			'desc'    => __( 'Enter the fee label that will be shown at checkout. If nothing is entered it will show the form\'s title as the fee', 'ninja-forms-edd' ),
			'type'    => 'text'
		)
	);

	ninja_forms_register_tab_metabox_options( $args );

}
add_action( 'admin_init', 'ninja_forms_edd_add_form_settings', 100 );