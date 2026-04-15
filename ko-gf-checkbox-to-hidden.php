<?php
/**
 * Plugin Name: KO - GF Checkbox to Hidden
 * Description: Maps checked values from specified Gravity Forms checkbox fields to target hidden fields before submission. Defaults: Form 54 — field 12 → hidden 16, field 13 → hidden 17.
 * Version: 1.0.0
 * Author: KO
 * License: GPL-2.0+
 * Text Domain: ko-gf-checkbox-to-hidden
 * Requires PHP: 7.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KO_GF_Checkbox_To_Hidden' ) ) :

final class KO_GF_Checkbox_To_Hidden {

	/** @var KO_GF_Checkbox_To_Hidden */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return KO_GF_Checkbox_To_Hidden
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Admin notice if Gravity Forms is not available.
		add_action( 'admin_notices', array( $this, 'maybe_show_gf_missing_notice' ) );

		// Register our per-form submission hooks on init (after plugins_loaded for safety).
		add_action( 'plugins_loaded', array( $this, 'register_hooks' ) );
	}

	/**
	 * Default mappings.
	 * Each mapping is an associative array with:
	 * - form_id (int)
	 * - source (checkbox field id, int)
	 * - target (hidden field id, int)
	 * - use_labels (bool) If true, stores labels instead of values.
	 *
	 * @return array
	 */
	public function get_mappings() {
		$mappings = array(
			array( 'form_id' => 54, 'source' => 12, 'target' => 16, 'use_labels' => false ),
			array( 'form_id' => 54, 'source' => 13, 'target' => 17, 'use_labels' => false ),
		);

		/**
		 * Filter: ko_gf_checkbox_to_hidden_mappings
		 *
		 * Allow code (theme or another plugin) to override or extend the mappings.
		 * Return value should be an array of mapping arrays like above.
		 */
		return apply_filters( 'ko_gf_checkbox_to_hidden_mappings', $mappings );
	}

	/**
	 * Register gform_pre_submission_{form_id} hooks for all unique forms in our mappings.
	 */
	public function register_hooks() {
		// Only proceed if Gravity Forms functions exist.
		if ( ! function_exists( 'GFAPI' ) && ! class_exists( 'GFAPI' ) ) {
			return;
		}

		$mappings   = $this->sanitize_mappings( $this->get_mappings() );
		$form_ids   = array_unique( array_map( static function( $m ) { return (int) $m['form_id']; }, $mappings ) );

		foreach ( $form_ids as $form_id ) {
			add_action( "gform_pre_submission_{$form_id}", function( $form ) use ( $form_id, $mappings ) {
				$this->map_for_form( $form, $form_id, $mappings );
			}, 10, 1 );
		}
	}

	/**
	 * Perform mapping for a specific form.
	 *
	 * @param array $form
	 * @param int   $form_id
	 * @param array $mappings
	 * @return void
	 */
	private function map_for_form( $form, $form_id, $mappings ) {
		foreach ( $mappings as $map ) {
			if ( (int) $map['form_id'] !== (int) $form_id ) {
				continue;
			}

			$source_id  = (int) $map['source'];
			$target_id  = (int) $map['target'];
			$use_labels = ! empty( $map['use_labels'] );

			$checkbox_field = GFAPI::get_field( $form, $source_id );
			if ( ! $checkbox_field || $checkbox_field->get_input_type() !== 'checkbox' || empty( $checkbox_field->inputs ) ) {
				continue;
			}

			$values = array();

			foreach ( $checkbox_field->inputs as $input ) {
				if ( empty( $input['id'] ) ) {
					continue;
				}
				$input_name   = 'input_' . str_replace( '.', '_', $input['id'] ); // e.g., input_12_1
				$posted_value = rgpost( $input_name );

				if ( $posted_value !== null && $posted_value !== '' ) {
					$value = is_array( $posted_value )
						? implode( ', ', array_map( 'sanitize_text_field', $posted_value ) )
						: sanitize_text_field( $posted_value );

					if ( $use_labels ) {
						$label    = $this->value_to_label( $checkbox_field, $value );
						$values[] = '' !== $label ? $label : $value;
					} else {
						$values[] = $value;
					}
				}
			}

			$joined = implode( ', ', array_filter( $values, static function( $v ) { return $v !== ''; } ) );

			// Set the hidden field POST value (e.g., input_16).
			$_POST[ 'input_' . $target_id ] = $joined;
		}
	}

	/**
	 * Map choice value to label (choice text).
	 *
	 * @param GF_Field $field
	 * @param string   $value
	 * @return string
	 */
	private function value_to_label( $field, $value ) {
		if ( empty( $field->choices ) ) {
			return '';
		}
		foreach ( $field->choices as $choice ) {
			$choice_value = isset( $choice['value'] ) ? (string) $choice['value'] : '';
			if ( (string) $value === $choice_value ) {
				return isset( $choice['text'] ) ? sanitize_text_field( $choice['text'] ) : $choice_value;
			}
		}
		return '';
	}

	/**
	 * Ensure mappings are well-formed.
	 *
	 * @param array $mappings
	 * @return array
	 */
	private function sanitize_mappings( $mappings ) {
		$clean = array();

		if ( ! is_array( $mappings ) ) {
			return $clean;
		}

		foreach ( $mappings as $m ) {
			$form_id    = isset( $m['form_id'] ) ? absint( $m['form_id'] ) : 0;
			$source     = isset( $m['source'] ) ? absint( $m['source'] ) : 0;
			$target     = isset( $m['target'] ) ? absint( $m['target'] ) : 0;
			$use_labels = ! empty( $m['use_labels'] );

			if ( $form_id && $source && $target ) {
				$clean[] = array(
					'form_id'    => $form_id,
					'source'     => $source,
					'target'     => $target,
					'use_labels' => $use_labels,
				);
			}
		}

		return $clean;
	}

	/**
	 * Admin notice if Gravity Forms is missing.
	 */
	public function maybe_show_gf_missing_notice() {
		if ( class_exists( 'GFAPI' ) ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>KO - GF Checkbox to Hidden</strong> requires <a href="https://www.gravityforms.com/" target="_blank" rel="noopener noreferrer">Gravity Forms</a> to be installed and active.</p></div>';
	}
}

endif;

// Bootstrap plugin.
add_action( 'plugins_loaded', array( 'KO_GF_Checkbox_To_Hidden', 'instance' ) );
