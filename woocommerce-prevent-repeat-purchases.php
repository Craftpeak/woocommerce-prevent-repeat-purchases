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
 * Text Domain: woocommerce-prevent-repeat-purchases
 */

class WC_Prevent_Repeat_Purchases {
	public function __construct() {
		// Write the Admin Panel
		add_action( 'woocommerce_product_options_general_product_data', [ &$this, 'write_panel' ] );
		// Process the Admin Panel Saving
		add_action( 'woocommerce_process_product_meta', [ &$this, 'write_panel_save' ] );

		// Don't allow the product to be purchased more than once
		add_filter( 'woocommerce_variation_is_purchasable', [ &$this, 'prevent_repeat_purchase' ], 10, 2 );
		add_filter( 'woocommerce_is_purchasable', [ &$this, 'prevent_repeat_purchase' ], 10, 2 );

		// Purchase Disabled Messages
		add_action( 'woocommerce_single_product_summary', [ &$this, 'purchase_disabled_message' ], 31 );

		// Disable a false positive purchased alert
		add_action( 'wp', [ &$this, 'disable_order_received_product_removal_alert' ] );
	}

	/**
	 * Function to write the HTML/form fields for the product panel
	 */
	public function write_panel() {
		// Open Options Group
		echo '<div class="options_group">';

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

		// Get the ID for the current product (passed in)
		// $product_id = $product->is_type( 'variation' ) ? $product->variation_id : $product->id;
		$product_id = $product->get_id();

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
			$purchasable = false;
		}

		// Double-check for variations: if parent is not purchasable, then variation is not
		if ( $purchasable && $product->is_type( 'variation' ) ) {
			$purchasable = $product->parent->is_purchasable();
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
	public function generate_disabled_message( $variation_id = '' ) {
		// Determine if we need any special classes
		$classes = '';
		if ( $variation_id ) {
			$classes .= ' js-variation-' . sanitize_html_class( $variation_id );
		}

		// Generate the message
		ob_start();
		?>
		<div class="woocommerce">
			<div class="woocommerce-info wc-nonpurchasable-message <?php echo $classes ?>">
				Looks like you've already purchased this product! It can only be purchased once.
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
		$no_repeats_product = wc_get_product( $no_repeats_id );

		var_dump( $no_repeats_product->is_type( 'variation' ) );

		// ... if it's a variation
		if ( $no_repeats_product && $no_repeats_product->is_type( 'variation' ) ) {
			// Bail if we're not looking at the product page for the non-purchasable product
			if ( ! $no_repeats_product->parent->id === $product->get_id() ) {
				return;
			}
			// Render the purchase restricted message if we are
			if ( wc_customer_bought_product( wp_get_current_user()->user_email, get_current_user_id(), $no_repeats_id ) ) {
				$this->render_variation_non_purchasable_message( $product, $no_repeats_id );
			}
		}
		// ... if it's a normal product
		elseif ( $no_repeats_id === $product->get_id() ) {
			if ( wc_customer_bought_product( wp_get_current_user()->user_email, get_current_user_id(), $no_repeats_id ) ) {
				// Show the disabled message
				echo $this->generate_disabled_message();
			}
		}
	}

	/**
	 * Generates a "purchase disabled" message to the customer for specific variations
	 *
	 * @param \WC_Product $product the WooCommerce product
	 * @param int $no_repeats_id the id of the non-purchasable product
	 */
	public function render_variation_non_purchasable_message( $product, $no_repeats_id ) {
		// Double-check we're looking at a variable product
		if ( $product->is_type( 'variable' ) && $product->has_child() ) {
			$variation_purchasable = true;
			foreach ( $product->get_available_variations() as $variation ) {
				// only show this message for non-purchasable variations matching our ID
				if ( $no_repeats_id === $variation['variation_id'] ) {
					$variation_purchasable = false;

					// Show the disabled message
					echo $this->generate_disabled_message( $variation['variation_id'] );
				}
			}
		}
		// show / hide this message for the right variation with some jQuery magic
		if ( ! $variation_purchasable ) {
			wc_enqueue_js( "
			jQuery( '.variations_form' )
				.on( 'woocommerce_variation_select_change', function( event ) {
					jQuery( '.wc-nonpurchasable-message' ).hide();
				})
				.on( 'found_variation', function( event, variation ) {
					jQuery( '.wc-nonpurchasable-message' ).hide();
					if ( ! variation.is_purchasable ) {
						jQuery( '.wc-nonpurchasable-message.js-variation-' + variation.variation_id ).show();
					}
				})
				.find( '.variations select' ).change();
			" );
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

add_action( 'plugins_loaded', function() {
	$WC_Prevent_Repeat_Purchases = new WC_Prevent_Repeat_Purchases();
} );

