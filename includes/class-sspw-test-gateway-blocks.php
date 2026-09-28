<?php
/**
 * Block checkout support for the Staging Test Gateway.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class SSPW_Test_Gateway_Blocks extends AbstractPaymentMethodType {

	protected $name = 'sspw_test';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_sspw_test_settings', array() );
	}

	/**
	 * Which gateways actually show is decided by woocommerce_available_payment_gateways,
	 * same as classic checkout; this only makes the script available.
	 */
	public function is_active() {
		return true;
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'sspw-test-gateway-blocks',
			SSPW_PLUGIN_URL . 'assets/js/test-gateway-blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			SSPW_VERSION,
			true
		);

		return array( 'sspw-test-gateway-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title', __( 'Staging Test Gateway', 'staging-superpowers-for-woocommerce' ) ),
			'description' => $this->get_setting( 'description', __( 'Test payment. No money will be taken.', 'staging-superpowers-for-woocommerce' ) ),
			'supports'    => array( 'products', 'refunds' ),
		);
	}
}
