<?php
/**
 * Offline gateway that pretends to take a payment. The outcome (paid, on hold,
 * failed) is picked in its settings so each order flow can be tested.
 */

defined( 'ABSPATH' ) || exit;

class SSPW_Test_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'sspw_test';
		$this->method_title       = __( 'Staging Test Gateway', 'staging-superpowers' );
		$this->method_description = __( 'Fake payments for staging sites. Only available while Staging Superpowers is active on this URL.', 'staging-superpowers' );
		$this->has_fields         = false;
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'staging-superpowers' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable the Staging Test Gateway', 'staging-superpowers' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Title', 'staging-superpowers' ),
				'type'    => 'text',
				'default' => __( 'Staging Test Gateway', 'staging-superpowers' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'staging-superpowers' ),
				'type'    => 'textarea',
				'default' => __( 'Test payment. No money will be taken.', 'staging-superpowers' ),
			),
			'outcome'     => array(
				'title'   => __( 'Payment result', 'staging-superpowers' ),
				'type'    => 'select',
				'default' => 'paid',
				'options' => array(
					'paid'    => __( 'Paid (order goes to Processing or Completed)', 'staging-superpowers' ),
					'on-hold' => __( 'On hold (awaiting payment)', 'staging-superpowers' ),
					'failed'  => __( 'Failed (payment declined)', 'staging-superpowers' ),
				),
			),
		);
	}

	/**
	 * When "hide every other gateway" is on, this is the only way to check out,
	 * so it stays available even if someone unticked Enable.
	 */
	public function is_available() {
		if ( 'yes' === sspw_get( 'sspw_gateways' ) ) {
			return true;
		}

		return parent::is_available();
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		/**
		 * The simulated payment result for this order: paid, on-hold or failed.
		 *
		 * @param string   $outcome Result picked in the gateway settings.
		 * @param WC_Order $order   The order being paid.
		 */
		switch ( apply_filters( 'sspw_test_gateway_outcome', $this->get_option( 'outcome', 'paid' ), $order ) ) {
			case 'failed':
				$order->update_status( 'failed', __( 'Staging Test Gateway: simulated declined payment.', 'staging-superpowers' ) );
				wc_add_notice( __( 'Staging Test Gateway: payment declined (simulated).', 'staging-superpowers' ), 'error' );

				return array( 'result' => 'failure' );

			case 'on-hold':
				$order->update_status( 'on-hold', __( 'Staging Test Gateway: simulated pending payment.', 'staging-superpowers' ) );
				wc_maybe_reduce_stock_levels( $order_id );
				break;

			default:
				$order->payment_complete( 'sspw-' . $order_id . '-' . time() );
		}

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return true;
	}
}
