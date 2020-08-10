<?php
/**
 * Plugin Name: WooCommerce Prevent Repeat Purchases
 * Plugin URI: https://github.com/Craftpeak/woocommerce-prevent-repeat-purchases
 * Description: Adds a checkbox to the WooCommerce product page restricting products to only be purchased once.
 * Version: 1.0.0
 * Author: Craftpeak
 * Author URI: https://craftpeak.com
 * Requires at least: 4.0
 * Tested up to: 4.9.8
 * Text Domain: woocommerce-prevent-repeat-purchases
 */

class WC_Prevent_Repeat_Purchases {
	public function __construct() {
		// Write the Admin Panel
		add_action( 'woocommerce_product_options_general_product_data', [ &$this, 'write_panel' ] );
		// Process the Admin Panel Saving
		add_action( 'woocommerce_process_product_meta', [ &$this, 'write_panel_save' ] );
		// Admin-side Scripts
		add_action( 'admin_enqueue_scripts', [ &$this, 'admin_scripts' ] );

		// See if product is purchasable only on the add_to_cart action
		add_filter( 'woocommerce_add_to_cart_validation', [ &$this, 'prevent_repeat_purchase' ], 10, 2 );

		// Purchase Disabled Messages
		add_action( 'woocommerce_single_product_summary', [ &$this, 'purchase_disabled_message' ], 31 );

		// Disable a false positive purchased alert
		add_action( 'wp', [ &$this, 'disable_order_received_product_removal_alert' ] );
	}

	/**
	 * Function to write the HTML/form fields for the product panel
	 */
	public function write_panel() {
		// Setup some globals
		global $post;
		$product = wc_get_product( $post->ID );

		// Exit if this is not a simple product
		if ( $product && ! $product->is_type( 'simple' ) ) {
			return;
		}

		// Open Options Group
		echo '<div class="options_group prevent-repeat-purchase-wrap">';

		// Write the checkbox for the product option
		woocommerce_wp_checkbox( [
			'id'            => 'prevent_repeat_purchase',
			'wrapper_class' => 'prevent-repeat-purchase',
			'label'         => __( 'Prevent Repeat Purchases?', 'woocommerce-prevent-repeat-purchases' ),
			'description'   => __( 'Prevent customers from purchasing this product more than once.', 'woocommerce-prevent-repeat-purchases' ),
		] );

		// Close Options Group
		echo '</div>';
	}

	/**
	 * Function to save our custom write panel values
	 *
	 * @param $post_id
	 */
	public function write_panel_save( $post_id ) {
		// Toggle the checkbox
		update_post_meta( $post_id, 'prevent_repeat_purchase', empty( $_POST['prevent_repeat_purchase'] ) ? 'no' : 'yes' );
	}

	/**
	 * Scripts for the Admin to hide checkbox for products that aren't simple
	 */
	public function admin_scripts() {
		// Get Screens
		$screen       = get_current_screen();
		$screen_id    = $screen ? $screen->id : '';

		// If this is the product edit screen
		if ( in_array( $screen_id, [ 'product', 'edit-product' ] ) ) {
			// JS to hide the option if it's not a simple product, just in case
			wc_enqueue_js( "
			jQuery( document.body ).on( 'woocommerce-product-type-change', function( event, value ) {
				if ( value !== 'simple' ) {
					// Uncheck Checkbox
					jQuery( '#prevent_repeat_purchase' ).prop( 'checked', false );
					// Hide
					jQuery( '.prevent-repeat-purchase-wrap' ).hide();
				}
			});
			" );
		};
	}

	/**
	 * The purchased message
	 */
	public function purchased_message() {
		$purchased_message = __( 'Looks like you\'ve already purchased this product! It can only be purchased once.', 'woocommerce-prevent-repeat-purchases' );

		return apply_filters('wc_prevent_repeat_purchase_message', $purchased_message);
	}

	/**
	 * Prevents repeat purchase for the product
	 *
	 * Based on code from SkyVerge
	 * @link https://www.skyverge.com/blog/prevent-repeat-purchase-with-woocommerce/
	 *
	 * @param bool $purchasable true if product can be purchased
	 * @param \WC_Product $product the WooCommerce product
	 * @return bool $purchasable the updated is_purchasable check
	 */
	public function prevent_repeat_purchase( $purchasable, $product_id ) {
		// Exit if this is the order received page.
		// This is to avoid showing the "%s has been removed from your cart because it can no longer be purchased."
		// warning message after the item has been purchased which could lead to confusion as to whether their purchase
		// was complete.
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			return true;
		}

		// Variable to check against
		$non_purchasable = 0;

		if ( get_post_meta( $product_id, 'prevent_repeat_purchase', true ) === 'yes' ) {
			$non_purchasable = $product_id;
		}

		// Bail unless the ID is equal to our desired non-purchasable product
		if ( $non_purchasable != $product_id ) {
			return $purchasable;
		}

		// return false if the customer has bought the product
		if ( wc_customer_bought_product( wp_get_current_user()->user_email, get_current_user_id(), $product_id ) ) {
			// show a notice
			wc_add_notice( $this->purchased_message(), 'error' );

			$purchasable = false;
		}

		return $purchasable;
	}

	/**
	 * Function to generate the disabled message
	 *
	 * @param $variation_id
	 *
	 * @return string
	 */
	public function generate_disabled_message() {
		// Generate the message
		ob_start();
		?>
		<div class="woocommerce">
			<div class="woocommerce-info wc-nonpurchasable-message">
				<?php echo esc_html( $this->purchased_message() ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shows a "purchase disabled" message to the customer
	 *
	 * Based on code from SkyVerge:
	 * @link https://www.skyverge.com/blog/prevent-repeat-purchase-with-woocommerce/
	 */
	public function purchase_disabled_message() {
		// Get the current product to check if purchasing should be disabled
		global $product;

		// Get the ID for the current product (passed in)
		$product_id = $product->get_id();
		$no_repeats_id = 0;

		// Enter the ID of the product that shouldn't be purchased again
		if ( get_post_meta( $product_id, 'prevent_repeat_purchase', true ) === 'yes' ) {
			$no_repeats_id = $product_id;
		}

		// Show the disabled message
		if ( $no_repeats_id === $product->get_id() ) {
			if ( wc_customer_bought_product( wp_get_current_user()->user_email, get_current_user_id(), $no_repeats_id ) ) {
				// Show the disabled message
				echo $this->generate_disabled_message();
			}
		}
	}

	/**
	 * Disable the a false positive alert on order_recieved
	 */
	public function disable_order_received_product_removal_alert() {
		// Check for the order-received endpoint (the point just after the checkout)
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			// Get all error notices
			$notices = wc_get_notices( 'error' );
			foreach ( $notices as $notice ) {
				// Check if notice text matches ours
				if ( strpos( $notice, 'has been removed from your cart because it can no longer be purchased') !== false ) {
					// Clear the notices
					wc_clear_notices();
				}
			}
		}
	}
}

// Fire it up!
add_action( 'plugins_loaded', function() {
	$WC_Prevent_Repeat_Purchases = new WC_Prevent_Repeat_Purchases();
} );
