<?php
/**
 * Elementor Pro widget catalog data.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'form' => array(
		'tier'     => 'pro',
		'title'    => 'Form',
		'category' => 'pro',
		'requires' => 'elementor-pro',
		'use_case' => 'Contact and lead-capture forms with configurable fields, submit actions (email, redirect, webhook, integrations), and styling.',
		'keywords' => array( 'form', 'contact', 'lead', 'input', 'submit' ),
		'params'   => array(
			'form_name'   => array( 'type' => 'string', 'description' => 'Form name.' ),
			'button_text' => array( 'type' => 'string', 'description' => 'Submit button text.' ),
		),
		'required' => array( 'form_name' ),
		'defaults' => array( 'button_text' => 'Send', 'submit_actions' => array( 'email' ) ),
	),
);
