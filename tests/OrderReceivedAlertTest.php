<?php

use PHPUnit\Framework\TestCase;

final class OrderReceivedAlertTest extends TestCase {
	private const REMOVED = '"KC s7 batch e" has been removed from your cart because it can no longer be purchased. Please contact us if you need assistance.';

	protected function setUp(): void {
		$GLOBALS['wc_test'] = [ 'endpoint' => 'order-received', 'notices' => [], 'cleared' => 0 ];
	}

	private function run_hook(): void {
		( new WC_Prevent_Repeat_Purchases() )->disable_order_received_product_removal_alert();
	}

	// WooCommerce 3.9+ notice shape: this crashed with a TypeError in 1.1.0.
	public function test_clears_removed_notice_in_array_shape(): void {
		$GLOBALS['wc_test']['notices'] = [ 'error' => [ [ 'notice' => self::REMOVED, 'data' => [] ] ] ];
		$this->run_hook();
		$this->assertSame( 1, $GLOBALS['wc_test']['cleared'] );
	}

	public function test_leaves_unrelated_array_notices(): void {
		$GLOBALS['wc_test']['notices'] = [ 'error' => [ [ 'notice' => 'Card declined.', 'data' => [] ] ] ];
		$this->run_hook();
		$this->assertSame( 0, $GLOBALS['wc_test']['cleared'] );
	}

	public function test_still_handles_legacy_string_notices(): void {
		$GLOBALS['wc_test']['notices'] = [ 'error' => [ 'Card declined.', self::REMOVED ] ];
		$this->run_hook();
		$this->assertSame( 1, $GLOBALS['wc_test']['cleared'] );
	}

	public function test_ignores_malformed_notices(): void {
		$GLOBALS['wc_test']['notices'] = [ 'error' => [ [ 'data' => [] ], [ 'notice' => [ 'x' ] ], null ] ];
		$this->run_hook();
		$this->assertSame( 0, $GLOBALS['wc_test']['cleared'] );
	}

	public function test_does_nothing_off_the_order_received_page(): void {
		$GLOBALS['wc_test']['endpoint'] = '';
		$GLOBALS['wc_test']['notices'] = [ 'error' => [ [ 'notice' => self::REMOVED, 'data' => [] ] ] ];
		$this->run_hook();
		$this->assertSame( 0, $GLOBALS['wc_test']['cleared'] );
	}
}
