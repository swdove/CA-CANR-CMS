<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACP_Settings_Column_User extends AC_Settings_Column_User {

	protected function get_display_options() {
		$options = parent::get_display_options();

		$options['custom_field'] = __( 'Custom Field', 'codepress-admin-columns' );

		return $options;
	}

	public function get_dependent_settings() {
		if ( $this->is_custom_field() ) {
			return array( new ACP_Settings_Column_UserCustomField( $this->column ) );
		}

		return parent::get_dependent_settings();
	}

	public function format( $user_id, $original_value ) {
		if ( $this->is_custom_field() ) {

			/** @var ACP_Settings_Column_UserCustomField $setting */
			$setting = $this->column->get_setting( 'custom_field' );

			return get_user_meta( $user_id, $setting->get_field(), true );
		}

		return parent::format( $user_id, $original_value );
	}

	public function is_custom_field(  ) {
		return 'custom_field' === $this->get_display_author_as();
	}

}
