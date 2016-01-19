<?php

/**
 * BP_REST_Loader class.
 */
class BP_REST_LOADER {

		protected $plugin = null;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return \BP_REST_Loader
	 */
	public function __construct( $plugin ) {

		$this->plugin = $plugin;
		$this->actions();

	}


	/**
	 * actions function.
	 *
	 * @access private
	 * @return void
	 */
	public function actions() {
		add_action( 'bp_include', array( $this, 'includes' ) );
	}


	/**
	 * inc function.
	 *
	 * @access public
	 * @return void
	 */
	public function includes() {
		// to include a file place it in the inc directory
		foreach( glob(  plugin_dir_path(__FILE__) . '*/classes/*.php' ) as $filename ) {
			include $filename;
		}
	}

}
