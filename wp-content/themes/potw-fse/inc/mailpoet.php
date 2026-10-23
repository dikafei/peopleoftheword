<?php
/**
 * MailPoet subscribe form for the footer.
 *
 * - Admin AJAX "potw_setup_mailpoet_form" creates (once) a simple email-only
 *   form that adds people to the "New lessons" list. Safe to run again.
 * - Shortcode [potw_subscribe] renders that form (nothing if MailPoet is off).
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'potw_subscribe',
	function () {
		$id = (int) get_option( 'potw_mailpoet_form_id' );
		if ( ! $id || ! shortcode_exists( 'mailpoet_form' ) ) {
			return '';
		}
		return '<div class="potw-subscribe">' . do_shortcode( '[mailpoet_form id="' . $id . '"]' ) . '</div>';
	}
);

add_action(
	'wp_ajax_potw_setup_mailpoet_form',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) ), 'wp_rest' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		if ( ! class_exists( '\MailPoet\API\API' ) ) {
			wp_send_json_error( 'MailPoet is not active' );
		}
		try {
			$api = \MailPoet\API\API::MP( 'v1' );

			// List: reuse "New lessons" or create it.
			$list_id = 0;
			foreach ( $api->getLists() as $list ) {
				if ( 'New lessons' === $list['name'] ) {
					$list_id = (int) $list['id'];
				}
			}
			if ( ! $list_id ) {
				$list    = $api->addList(
					array(
						'name'        => 'New lessons',
						'description' => 'People who asked to be emailed when a new lesson is published (footer form).',
					)
				);
				$list_id = (int) $list['id'];
			}

			$container = \MailPoet\DI\ContainerWrapper::getInstance();
			$forms     = $container->get( \MailPoet\Form\FormsRepository::class );
			$form      = null;
			$existing  = (int) get_option( 'potw_mailpoet_form_id' );
			if ( $existing ) {
				$form = $forms->findOneById( $existing );
			}
			if ( ! $form ) {
				$form = new \MailPoet\Entities\FormEntity( 'Footer – new lessons' );
			}

			$form->setBody(
				array(
					array(
						'type'   => 'text',
						'name'   => 'Email',
						'id'     => 'email',
						'unique' => '0',
						'static' => '1',
						'params' => array(
							'label'        => 'Email',
							'required'     => '1',
							'label_within' => '1',
						),
						'styles' => array( 'full_width' => '1' ),
					),
					array(
						'type'   => 'submit',
						'name'   => 'Submit',
						'id'     => 'submit',
						'unique' => '0',
						'static' => '1',
						'params' => array( 'label' => 'Subscribe' ),
						'styles' => array( 'full_width' => '1' ),
					),
				)
			);
			$form->setSettings(
				array(
					'segments'             => array( (string) $list_id ),
					'segments_selected_by' => 'admin',
					'on_success'           => 'message',
					'success_message'      => 'Thank you! Please check your inbox to confirm your subscription.',
					'alignment'            => 'left',
					'form_placement'       => array(
						'below_posts' => array( 'enabled' => '' ),
						'popup'       => array( 'enabled' => '' ),
						'fixed_bar'   => array( 'enabled' => '' ),
						'slide_in'    => array( 'enabled' => '' ),
						'others'      => array(),
					),
				)
			);
			$form->setStyles( '' );
			$form->setStatus( \MailPoet\Entities\FormEntity::STATUS_ENABLED );
			$forms->persist( $form );
			$forms->flush();

			update_option( 'potw_mailpoet_form_id', $form->getId() );
			wp_send_json_success(
				array(
					'form' => $form->getId(),
					'list' => $list_id,
				)
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error( get_class( $e ) . ': ' . $e->getMessage() );
		}
	}
);
