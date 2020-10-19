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

		// Don't allow the product to be purchased more than once
		add_filter( 'woocommerce_is_purchasable', [ &$this, 'prevent_repeat_purchase' ], 10, 2 );

		// Make sure the customer hasn't purchased the product before on checkout
		add_action( 'woocommerce_check_cart_items', [ &$this, 'before_checkout_process' ], 10, 1 );

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
	 * Check to see if the product is purchasable
	 */
	public function is_product_repeat_purchasable( $product_id ) {
		// Check for a value
		$repeat_purchasable = get_transient( 'product_' . $product_id . '_repeat_purchaseable' );

		// If there is no value in the transient... go get it
		if ( $repeat_purchasable === false ) {
			$repeat_purchasable_value = get_post_meta( $product_id, 'prevent_repeat_purchase', true );

			// Set the transient for 5 minutes
			set_transient( 'product_' . $product_id . '_repeat_purchaseable', $repeat_purchasable_value, 300 );
		}

		// If the box is checked, return false
		if ( $repeat_purchasable === 'yes' ) {
			return false;
		}

		// Default, return true
		return true;
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
	public function prevent_repeat_purchase( $purchasable, $product ) {
		// Exit if this is the order received page.
		// This is to avoid showing the "%s has been removed from your cart because it can no longer be purchased."
		// warning message after the item has been purchased which could lead to confusion as to whether their purchase
		// was complete.
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			return true;
		}

		// If you CAN purchase this product more than once, move on
		if ( $this->is_product_repeat_purchasable( $product->get_id() ) ) {
			return $purchasable;
		}

		// Return false if the customer has bought the product
		if ( wc_customer_bought_product( wp_get_current_user()->user_email, get_current_user_id(), $product->get_id() ) ) {
			$purchasable = false;
		}

		return $purchasable;
	}

	/**
	 * Check if we can purchase this product before the checkout process...
	 */
	public function before_checkout_process() {
		$purchased_products = false;
		$cart_items = WC()->cart->get_cart();
		$billing_email = false;

		// Set the billing email as long as it exists... (it should)
		if ( ! isset( $_REQUEST['billing_email'] ) ) {
			return;
		}

		// Set the billing email
		// @todo: add logic to remove anything b/w a "+" in the root email and "@" to prevent spoofing
		$billing_email = $_REQUEST['billing_email'];

		if ( $cart_items ) {
			foreach ( $cart_items as $cart_item ) {
				$product_id = $cart_item['product_id'];
				// If the item can only be purchased once, check to see if this billing email has bought it
				if ( ! $this->is_product_repeat_purchasable( $product_id ) ) {
					if ( wc_customer_bought_product( $billing_email, get_current_user_id(), $product_id ) ) {
						$product = wc_get_product( $product_id );
						$product_name = $product->get_name();
						wc_add_notice( sprintf( __( 'It looks like you\'ve already purchased "%1$s", please remove it from your cart and try again.' , 'woocommerce-prevent-repeat-purchases' ), $product_name ), 'error');
					}
				}
			}
		}
	}

	/**
	 * Function to generate the disabled message
	 *
	 * @param $variation_id
	 *
	 * @return string
	 */
	public function generate_disabled_message() {
		// Message text
		$message = __( 'Looks like you\'ve already purchased this product! It can only be purchased once.', 'woocommerce-prevent-repeat-purchases' );

		// Generate the message
		ob_start();
		?>
		<div class="woocommerce">
			<div class="woocommerce-info wc-nonpurchasable-message">
				<?php echo esc_html( apply_filters( 'wc_repeat_nonpurchaseable_message', $message ) ); ?>
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
