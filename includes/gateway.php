<?php
/**
 * WooCommerce Gateway Extension
 *
 * @author Justin Greer <justin@dash10.digital>
 * @package User Wallet Credit System
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Add the User Wallet Gateway to WooCommerce
 */
add_filter( 'woocommerce_payment_gateways', 'init_wpuw_gateway' );
function init_wpuw_gateway( $methods ) {
	$methods[] = 'WC_Gateway_WPUW';
	return $methods;
}

/**
 * Virual Wallet
 *
 * Provides payment using users virtual wallet balance
 *
 * @class        WC_Gateway_WPUW
 * @extends        WC_Payment_Gateway
 * @version        0.1
 * @author Justin Greer <justin@justin-greer.com>
 *
 * @link https://docs.woocommerce.com/document/payment-gateway-api/
 */
if ( class_exists( 'WC_Payment_Gateway' ) ):
	class WC_Gateway_WPUW extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			$this->id           = 'wpuw';
			$this->icon         = apply_filters( 'woocommerce_wpuw_icon', '' );
			$this->method_title = __( 'User Wallet', 'woocommerce' );

			if ( ! defined( 'UWCS_PRO' ) ) {
				$this->method_description = __( '<strong>&#9734;Want more features and support! <a href="https://dash10.digital/products/user-wallet-credit-system-pro/">Lean more</a> about User Wallet Credit System Pro.&#9734;</strong> ', 'woocommerce' );
			} else {
				$this->method_description = __( 'Have your customers pay with their user wallet balance.', 'woocommerce' );
			}

			$this->has_fields = false;

			/** load the settings */
			$this->init_form_fields();
			$this->init_settings();

			/** get the settings */
			$this->title              = $this->get_option( 'title' );
			$this->description        = $this->get_option( 'description' );
			$this->instructions       = $this->get_option( 'instructions', $this->description );
			$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
			$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes' ? true : false;

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
			add_action( 'woocommerce_thankyou_wpvw', array( $this, 'thankyou_page' ) );
			add_action( 'woocommerce_before_checkout_process', array( $this, 'check_balance' ) );

			/** setup custom email */
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 */
		public function init_form_fields() {

			$shipping_methods = array();
			if ( is_admin() ) {
				$methods_array = WC()->shipping()->get_shipping_methods();
				foreach ( $methods_array as $method ) {
					$shipping_methods[ $method->id ] = $method->get_method_title();
				}
			}

			$this->form_fields = array(
				'enabled'            => array(
					'title'       => __( 'Enable User Wallet', 'woocommerce' ),
					'label'       => __( 'Enable User Wallet', 'woocommerce' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title'              => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'User Wallet', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description'        => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
					'default'     => __( 'Pay using your Wallet.', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'instructions'       => array(
					'title'       => __( 'Instructions', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
					'default'     => __( 'Pay using your Wallet', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'enable_for_methods' => array(
					'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
					'type'              => 'multiselect',
					'class'             => 'chosen_select',
					'css'               => 'width: 450px;',
					'default'           => '',
					'description'       => __( 'If User Wallet is only available for certain shipping methods, set it up here. Leave blank to enable for all methods.', 'woocommerce' ),
					'options'           => $shipping_methods,
					'desc_tip'          => true,
					'custom_attributes' => array(
						'data-placeholder' => __( 'Select shipping methods', 'woocommerce' )
					)
				),
				'enable_for_virtual' => array(
					'title'   => __( 'Enable for virtual orders', 'woocommerce' ),
					'label'   => __( 'Enable User Wallet if the order is virtual', 'woocommerce' ),
					'type'    => 'checkbox',
					'default' => 'yes'
				)
			);
		}

		/**
		 * [is_available description]
		 * @return boolean [description]
		 */
		public function is_available() {
			$order = null;

			if ( ! $this->enable_for_virtual ) {

				if ( WC()->cart && ! WC()->cart->needs_shipping() ) {
					return false;
				}

				if ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
					$order_id = absint( get_query_var( 'order-pay' ) );
					$order    = wc_get_order( $order_id );

					$needs_shipping = false;
					if ( 0 < sizeof( $order->get_items() ) ) {
						foreach ( $order->get_items() as $item ) {

							// @todo Migrate Support to latest version
							$_product = $order->get_get_product_from_item( $item );

							if ( $_product->needs_shipping() ) {
								$needs_shipping = true;
								break;
							}
						}
					}

					$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );
					if ( $needs_shipping ) {
						return false;
					}

				}
			}

			if ( ! empty( $this->enable_for_methods ) ) {

				$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

				if ( isset( $chosen_shipping_methods_session ) ) {
					$chosen_shipping_methods = array_unique( $chosen_shipping_methods_session );
				} else {
					$chosen_shipping_methods = array();
				}

				$check_method = false;

				if ( is_object( $order ) ) {
					print_r($order->get_shipping_method());
					if ( $order->get_shipping_method() ) {
						$check_method = $order->get_shipping_method();
					}
				} elseif ( empty( $chosen_shipping_methods ) || sizeof( $chosen_shipping_methods ) > 1 ) {
					$check_method = false;
				} elseif ( sizeof( $chosen_shipping_methods ) == 1 ) {
					$check_method = $chosen_shipping_methods[0];
				}

				if ( ! $check_method ) {
					return false;
				}

				$found = false;

				foreach ( $this->enable_for_methods as $method_id ) {
					if ( strpos( $check_method, $method_id ) === 0 ) {
						$found = true;
						break;
					}
				}

				if ( ! $found ) {
					return false;
				}
			}

			if ( is_user_logged_in() && ! empty( get_user_meta( get_current_user_id(), '_uw_balance', true ) ) ) {
				$current_balance = floatval( get_user_meta( get_current_user_id(), '_uw_balance', true ) );
				if ( wc()->cart->get_total( '' ) > $current_balance ) {
					return false;
				}
			}

			/**
			 * Naturally the user would have to be logged in if they are to use their wallet funds.
			 */
			if ( ! is_user_logged_in() ) {
				return false;
			} /*else {
				if ( empty( get_user_meta( get_current_user_id(), '_uw_balance', true ) ) ) {
					return false;
				} else {
					if ( floatval( empty( get_user_meta( get_current_user_id(), '_uw_balance', true ) ) ) <= 0 ) {
						return false;
					}
				}
			}*/

			return parent::is_available();
		}


		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {

			/** check to make sure there is not credit item in the cart */
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				if ( has_term( 'credit', 'product_cat', $values['product_id'] ) ) {

					// @todo Make single check for option for better performance
					if ( defined( 'UWCS_PRO' ) && get_option( 'wc_settings_checkout_error_currency_purchase' ) ) {
						wc_add_notice( __( '<strong>Payment error:</strong>', 'woothemes' ) . ' ' . get_option( 'wc_settings_checkout_error_currency_purchase' ), 'error' );
					} else {
						wc_add_notice( __( '<strong>Payment error:</strong>', 'woothemes' ) . ' You can not purchase virtual money with virtual money. Please choose another payment method.', 'error' );
					}

					return;
				}
			}

			/** get the order informtion */
			$order   = wc_get_order( $order_id );
			$user_id = $order->get_user_id();

			/** get the users credit balance */
			$vw_balance = floatval( get_user_meta( $user_id, "_uw_balance", true ) );
			$cart_total = floatval( WC()->cart->total );

			/** check to make sure the user has enough credit to make the purchase */
			if ( $cart_total > $vw_balance ) {

				if ( defined( 'UWCS_PRO' ) && get_option( 'wc_settings_insufficient_funds' ) ) {
					wc_add_notice( __( '<strong>Payment error:</strong>', 'woothemes' ) . ' ' . get_option( 'wc_settings_insufficient_funds' ), 'error' );
				} else {
					wc_add_notice( __( '<strong>Payment error:</strong>', 'woothemes' ) . ' Insufficient funds. Please purchase more credits or use a different payment method.', 'error' );
				}

				return;
			}

			/** deduct funds from user wallet and continue */
			$new_user_vw_balance = $vw_balance - $cart_total;
			update_user_meta( $user_id, '_uw_balance', $new_user_vw_balance );

			/** redundancy check */
			if ( get_user_meta( $user_id, '_uw_balance', true ) != $new_user_vw_balance ) {
				wc_add_notice( __( '<strong>System error:</strong>', 'woothemes' ) . ' There was an error processing the payment. Please try another payment method.', 'error' );

				return;
			}

			$update_status = apply_filters( 'wpuw_update_status', 'completed' );
			$order->update_status( $update_status, __( 'Payment marked ' . $update_status . ' using Virtual Wallet', 'woocommerce' ) );

			/** reduce stock levels */
			wc_reduce_stock_levels( $order_id );

			/** empty the cart */
			WC()->cart->empty_cart();

			/** send to the thankyou page */
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		/**
		 * [get_icon description]
		 * @return [type] [description]
		 */
		public function get_icon() {
			$link = null;
			global $woocommerce;
			$vw_balance = wc_price( get_user_meta( get_current_user_id(), '_uw_balance', true ) );

			return apply_filters( 'woocommerce_gateway_icon', ' | Your Current Balance: <strong>' . $vw_balance . '</strong>', $this->id );
		}

		/**
		 * [thankyou_page description]
		 * @return [type] [description]
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}

		/**
		 * [email_instructions description]
		 *
		 * @param  [type]  $order         [description]
		 * @param  [type]  $sent_to_admin [description]
		 * @param  boolean $plain_text [description]
		 *
		 * @return [type]                 [description]
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			if ( $this->instructions && ! $sent_to_admin && 'vw' === $order->get_payment_method ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}

	}
endif;
