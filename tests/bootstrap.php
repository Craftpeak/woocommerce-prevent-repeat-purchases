<?php
// Minimal WordPress/WooCommerce stubs so the plugin class can be exercised without WordPress.

if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require __DIR__ . '/../vendor/autoload.php';
}

$GLOBALS['wc_test'] = [ 'endpoint' => '', 'notices' => [], 'cleared' => 0 ];

function add_action( ...$args ) {}
function add_filter( ...$args ) {}

function is_wc_endpoint_url( $endpoint = false ) {
	return $GLOBALS['wc_test']['endpoint'] === $endpoint;
}

function wc_get_notices( $notice_type = '' ) {
	$all = $GLOBALS['wc_test']['notices'];
	return $notice_type === '' ? $all : ( $all[ $notice_type ] ?? [] );
}

function wc_clear_notices() {
	$GLOBALS['wc_test']['notices'] = [];
	$GLOBALS['wc_test']['cleared']++;
}

require __DIR__ . '/../woocommerce-prevent-repeat-purchases.php';
