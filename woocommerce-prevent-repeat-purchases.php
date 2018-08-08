<?php
/**
 * Plugin Name: WooCommerce Prevent Repeat Purchases
 * Plugin URI: https://github.com/Craftpeak/woocommerce-prevent-repeat-purchases
 * Description: Adds a checkbox to the WooCommerce product page restricting products to only be purchased once.
 * Version: 0.0.1
 * Author: Craftpeak
 * Author URI: https://craftpeak.com
 * Requires at least: 4.0
 * Tested up to: 4.9.8
 */

class WC_Prevent_Repeat_Purchases {
	public function __construct() {
		// Write the Admin Panel
		add_action( 'woocommerce_product_options_general_product_data', [ &$this, 'write_panel' ] );
		// Process the Admin Panel Saving
//		add_action( 'woocommerce_process_product_meta', [ &$this, 'write_panel_save' ] );
	}

	/**
	 * Function to write the HTML/form fields for the product panel
	 */
	public function write_panel() {
		// Open Options Group
		echo '<div class="options_group">';

		// Close Options Group
		echo '</div>';
	}

	/**
	 * Function to save our custom write panel values
	 *
	 * @param $post_id
	 */
	public function write_panel_save( $post_id ) {

	}
}

add_action( 'plugins_loaded', function() {
	$WC_Prevent_Repeat_Purchases = new WC_Prevent_Repeat_Purchases();
} );

