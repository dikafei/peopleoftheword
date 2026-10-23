<?php
/**
 * GiveWP: branding for the donation form (colours, fonts, wording).
 *
 * Admin-only AJAX action "potw_brand_give_form" (needs a wp_rest nonce),
 * e.g. admin-ajax.php?action=potw_brand_give_form&form=3382&_wpnonce=…
 * Safe to run again; it re-applies the same settings.
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_ajax_potw_brand_give_form',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) ), 'wp_rest' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		$id    = (int) ( $_REQUEST['form'] ?? 0 );
		$class = '\Give\DonationForms\Models\DonationForm';
		if ( ! class_exists( $class ) ) {
			wp_send_json_error( 'GiveWP v3 form model not found' );
		}
		$form = $class::find( $id );
		if ( ! $form ) {
			wp_send_json_error( 'Form not found' );
		}

		try {
			$s                     = $form->settings;
			$s->primaryColor       = '#556838'; // olive.
			$s->secondaryColor     = '#B37419'; // gold.
			// No intro screen: the Donate page has its own heading, so the form opens straight on the amounts.
			$s->showHeader         = false;
			$s->showHeading        = false;
			$s->showDescription    = false;
			// One page (amounts, details, payment), no "Donate now" first step.
			$s->designId           = 'classic';
			$s->multiStepFirstButtonText = 'Give now';
			$s->multiStepNextButtonText  = 'Continue';
			$s->donateButtonCaption      = 'Give now';
			$s->heading            = 'Give to People of the Word';
			$s->description        = 'Your gift keeps weekly Bible lessons, lectures and homework free for everyone who wants to study God’s Word.';
			$s->receiptHeading     = 'Thank you, {first_name}!';
			$s->receiptDescription = 'Your gift of {amount} helps people study and grow in God’s Word. A receipt has been sent to {email}.';
			$s->customCss          = potw_give_form_css();
			$form->settings        = $s;

			// Section titles and descriptions inside the form.
			$json = $form->blocks->toJson();
			$map  = array(
				'How much would you like to donate today?'                                        => 'How much would you like to give?',
				'All donations directly impact our organization and help us further our mission.' => 'Every gift, large or small, helps us share God’s Word each week.',
				'Who’s giving today?'                                                        => 'Who is giving today?',
				'Who’s giving today?'                                                             => 'Who is giving today?',
				"Who's giving today?"                                                             => 'Who is giving today?',
				'We’ll never share this information with anyone.'                            => 'We will never share your details with anyone.',
				'We’ll never share this information with anyone.'                                 => 'We will never share your details with anyone.',
				"We'll never share this information with anyone."                                 => 'We will never share your details with anyone.',
				'How would you like to pay?'                                                      => 'How would you like to give?',
				'Every donation is encrypted and secure.'                                         => 'Every gift is encrypted and secure.',
				'Donate now'                                                                      => 'Give now',
				'Donate Now'                                                                      => 'Give now',
				"Who's Giving Today?"                                                             => 'Who is giving today?',
				'How would you like to pay for your donation?'                                    => 'How would you like to give?',
				'Payment Details'                                                                 => 'Payment details',
			);
			$new          = strtr( $json, $map );
			$blocks_class = get_class( $form->blocks );
			$form->blocks = $blocks_class::fromJson( $new );

			$form->save();
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		// Show remaining section titles so they can be checked.
		preg_match_all( '/"(?:title|description|label)":"([^"]{3,120})"/', $new, $m );
		wp_send_json_success(
			array(
				'id'      => $form->id,
				'heading' => $form->settings->heading,
				'primary' => $form->settings->primaryColor,
				'changed' => $new !== $json,
				'saved'   => array(
					'design' => $class::find( $id )->settings->designId,
					'header' => $class::find( $id )->settings->showHeader,
				),
				'texts'   => array_values( array_unique( $m[1] ) ),
			)
		);
	}
);

/** CSS inside the GiveWP form iframe: brand fonts, colours and rounded shapes. */
function potw_give_form_css() {
	return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Epunda+Slab:wght@400;500;600;700&display=swap');
:root {
	--givewp-primary-color: #556838 !important;
	--givewp-secondary-color: #B37419 !important;
	--givewp-blue-500: #556838 !important;
	--givewp-blue-400: #6b7f4a !important;
	--givewp-blue-600: #394625 !important;
}
body, input, select, textarea, button, p, label, span { font-family: 'Epunda Slab', Georgia, serif !important; }
h1, h2, h3, legend, .givewp-layouts-section__fieldset__legend { font-family: 'DM Serif Display', Georgia, serif !important; font-weight: 400 !important; }
button, input, select, textarea { border-radius: 10px !important; }
/* Buttons: olive, darker on hover. */
button[type="submit"], button[type="button"].givewp-donation-form__steps-button-next, .givewp-donation-form__steps-button-next, .givewp-elements-donate__button {
	background-color: #556838 !important; border-color: #556838 !important; color: #fff !important;
}
button[type="submit"]:hover, .givewp-donation-form__steps-button-next:hover { background-color: #394625 !important; border-color: #394625 !important; }
/* Selected amount: olive. */
.givewp-fields-amount__level--selected, .givewp-fields-amount__level[aria-checked="true"], .givewp-fields-amount__level[aria-pressed="true"] {
	background-color: #556838 !important; border-color: #556838 !important; color: #fff !important;
}
.givewp-fields-amount__level:hover { border-color: #556838 !important; }
/* Step header bar. */
.givewp-donation-form__steps-header { border-bottom-color: #E4D3BF !important; }
.givewp-donation-form__steps-progress, .givewp-donation-form__steps-progress-bar { background-color: #B37419 !important; }
CSS;
}
