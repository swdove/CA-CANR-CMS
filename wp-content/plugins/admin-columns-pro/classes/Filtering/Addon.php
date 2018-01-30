<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 4.0
 */
class ACP_Filtering_Addon extends AC_Addon {

	public function __construct() {
		AC()->autoloader()->register_prefix( 'ACP_Filtering', $this->get_plugin_dir() . 'classes' );

		new ACP_Filtering_TableScreen();

		add_action( 'ac/column/settings', array( $this, 'settings' ) );
		add_action( 'ac/settings/scripts', array( $this, 'settings_scripts' ) );
	}

	/**
	 * @return string
	 */
	protected function get_file() {
		return __FILE__;
	}

	/**
	 * @since 4.0
	 */
    public function get_version() {
		return ACP()->get_version();
	}

	/**
	 * @return ACP_Filtering_Helper
	 */
	public function helper() {
		return new ACP_Filtering_Helper();
	}

	/**
	 * @param AC_Column $column
	 *
	 * @return ACP_Filtering_Model|false
	 */
	public function get_filtering_model( $column ) {
		if ( ! $column instanceof ACP_Column_FilteringInterface ) {
			return false;
		}

		$list_screen = $column->get_list_screen();

		if ( ! $list_screen instanceof ACP_Filtering_ListScreen ) {
			return false;
		}

		$model = $column->filtering();

		return $model->set_strategy( $list_screen->filtering( $model ) );
	}

	public function settings_scripts() {
		wp_enqueue_script( 'acp-filtering-settings', $this->get_plugin_url() . 'assets/js/settings' . AC()->minified() . '.js', array( 'jquery' ), $this->get_version() );
	}

	/**
	 * Register field settings for filtering
	 *
	 * @param AC_Column $column
	 */
	public function settings( $column ) {
		if ( $model = $this->get_filtering_model( $column ) ) {
			$model->register_settings();
		}
	}

}
