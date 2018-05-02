<?php

namespace ACFQuickEdit\Fields;

if ( ! defined( 'ABSPATH' ) )
	die('Nope.');

class CheckboxField extends ChoiceField {


	/**
	 *	@inheritdoc
	 */
	public function render_input( $input_atts, $is_quickedit = true ) {
		$output = '';

		$output .= sprintf( '<input %s />', acf_esc_attr( array(
			'type'					=> 'hidden',
			'name'					=> $input_atts['name'],
		) ) );

		$output .= sprintf( '<ul class="acf-checkbox-list" data-acf-field-key="%s">', $this->acf_field['key'] );

		$input_atts		+= array(
			'class'	=> 'acf-quick-edit',
			'id'	=> $this->core->prefix( $this->acf_field['key'] ),
		);
		$this->acf_field['value']	= acf_get_array( $this->acf_field['value'], false );

		$field_name = sprintf( 'acf[%s][]', $this->acf_field['key'] );

		foreach ( $this->acf_field['choices'] as $value => $label ) {
			$atts = array(
				'data-acf-field-key'	=> $this->acf_field['key'],
				'type'					=> 'checkbox',
				'value'					=> $value,
				'name'					=> $field_name,
				'id'					=> $this->core->prefix( $this->acf_field['key'] . '-'.$value ),
			);
			if ( ! $is_quickedit ) {
				$atts['disabled'] = 'disabled';
			}

			if ( in_array( $value, $this->acf_field['value'] ) ) {
				$atts['checked'] = 'checked';
			}
			$output .= sprintf( '<li><label><input %s/>%s</label></li>', acf_esc_attr( $atts ), acf_esc_html( $label ) );
		}

		$output .= '</ul>';
		if ( $this->acf_field['allow_custom'] ) {
			$output .= '<button class="button button-seconday add-choice">' . __('Add Choice','acf-quick-edit-fields') . '</button>';
			$output .= sprintf( '<script type="text/html" id="tmpl-acf-qef-custom-choice-%s">', $this->acf_field['key'] );

			$id = $this->core->prefix( $this->acf_field['key'] . '-other' );

			$output .= '<li><label>';
			$output .= sprintf( '<input %s />', acf_esc_attr( array(
				'type'					=> 'checkbox',
				'class'					=> 'acf-quick-edit custom',
				'checked'				=> 'checked',
			) ) );
			$output .= sprintf( '<input %s />', acf_esc_attr( array(
				'type'					=> 'text',
				'class'					=> 'acf-quick-edit',
				'data-acf-field-key'	=> $this->acf_field['key'],
				'name'					=> $field_name,
				'style'					=> 'width:initial',
			) ) );

			$output .= '</label></li>';

			$output .= '</script>';
		}

		return $output;
	}


	/**
	 *	@inheritdoc
	 */
	public function update( $post_id ) {

		$param_name = $this->acf_field['key'];

		if ( isset( $_REQUEST['acf'][ $param_name ] ) ) {
			$value = $_REQUEST['acf'][ $param_name ];
		} else {
			$value = null;
		}

		if ( in_array( $this->dont_change_value, (array) $value ) ) {
			return;
		}

		update_field( $this->acf_field['key'], $value, $post_id );

	}


}
