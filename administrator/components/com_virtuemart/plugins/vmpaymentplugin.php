<?php

/**
 * abstract class for payment plugins
 *
 * @package	VirtueMart
 * @subpackage Plugins
 * @author Max Milbers
 * @author Oscar van Eijk
 * @author Valérie Isaksen
 * @link http://www.virtuemart.net
 * @copyright Copyright (c) 2004 - 2011 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: vmpaymentplugin.php 4601 2011-11-03 15:50:01Z alatak $
 */
// Load the helper functions that are needed by all plugins
if (!class_exists('vmPSPlugin'))
require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

// Get the plugin library
jimport('joomla.plugin.plugin');

abstract class vmPaymentPlugin extends vmPSPlugin {

	private $_virtuemart_paymentmethod_id = 0;
	private $_payment_name = '';
	protected $payments;

	/**
	 * Method to create te plugin specific table; must be reimplemented.
	 * @example
	 * 	$_scheme = DbScheme::get_instance();
	 * 	$_scheme->create_scheme('#__vm_order_payment_'.$this->_name);
	 * 	$_schemeCols = array(
	 *  those fields are REQUIRED
	 * 		 'id' => array (
	 * 				 'type' => 'int'
	 * 				,'length' => 11
	 * 				,'auto_inc' => true
	 * 				,'null' => false
	 * 		)
	 * 		,'virtuemart_order_id' => array (
	 * 				 'type' => 'int'
	 * 				,'length' => 11
	 * 				,'null' => false
	 * 		)
	 * 		,'order_number' => array (
	 * 				 'type' => 'varchar'
	 * 				,'length' => 32
	 * 				,'null' => false
	 * 		)
	 * 		,'payment_method_id' => array (
	 * 				 'type' => 'text'
	 * 				,'null' => false
	 * 		)
	 *
	 * 	);
	 * 	$_schemeIdx = array(
	 * 		 'idx_order_payment' => array(
	 * 				 'columns' => array ('virtuemart_order_id')
	 * 				,'primary' => false
	 * 				,'unique' => false
	 * 				,'type' => null
	 * 		)
	 * 	);
	 * 	$_scheme->define_scheme($_schemeCols);
	 * 	$_scheme->define_index($_schemeIdx);
	 * 	if (!$_scheme->scheme()) {
	 * 		JError::raiseWarning(500, $_scheme->get_db_error());
	 * 	}
	 * 	$_scheme->reset();
	 * @author Oscar van Eijk
	 *
	 * (1) add some fields with specific values for the request
	 *  (2) add some fields for the response: to reuse the predefined functions create those row woth the follong convention:
	 *  'plugin_name'_'response'_'field_name' example: 'paypal_response_payment_status'
	 *  (3) create the language key following this convention
	 *      VMPAYMENT_'plugin_name'_RESPONSE_'field_name'
	 * 	    example:  VMPAYMENT_PAYPAL_RESPONSE_PAYMENT_STATUS="Payment_status"
	 *  (4) if the field is actually a code, and there is a string with this code, add a key following this convention
	 *       VMPAYMENT_PAYPAL_RESPONSE_PAYMENT_STATUS_'code number or letter'
	 *
	 * example:
	 * 'authorizenet_response_response_code' : entry in the table
	 * VMPAYMENT_AUTHORIZENET_RESPONSE_RESPONSE_CODE="Response Code" the language key
	 * VMPAYMENT_AUTHORIZENET_RESPONSE_RESPONSE_CODE_1="This transaction has been approved." : the language key decoded
	 *
	 * @author Valerie Isaksen
	 *
	 */
	abstract protected function _createTable();

	/**
	 * This event is fired during the checkout process. It allows the shopper to select
	 * one of the available payment methods.
	 * It should display a radio button (name: virtuemart_paymentmethod_id) to select the payment method. Other
	 * information (like credit card info) might be selected as well.
	 * @author Max Milbers
	 * @param object  VirtueMartCart $cart The cart object
	 * @param integer $selectedPayment of an already selected payment method ID, if any
	 * @return array html to display the radio button
	 *
	 */
	public function plgVmOnSelectPayment(VirtueMartCart $cart, $selectedPayment=0) {

		if ($this->getPaymentMethods($cart->vendorId) === false) {
			if (empty($this->_name)) {
				$app = JFactory::getApplication();
				$app->enqueueMessage(JText::_('COM_VIRTUEMART_CART_NO_PAYMENT'));
				return;
			} else {
				return;
			}
		}
		$html = array();
		foreach ($this->payments as $payment) {
			if ($this->checkConditions($cart->pricesUnformatted, $payment)) {
				//vmdebug('plgVmOnSelectPayment', $payment->payment_name, $payment->payment_params);
				$params = new JParameter($payment->payment_params);
				$paymentSalesPrice = $this->calculateSalesPrice($this->getPaymentValue($params, $cart), $this->getPaymentTaxId($params, $cart));
				$payment->payment_name = $this->renderPluginName($payment);
				$html [] = $this->getPaymentHtml($payment, $selectedPayment, $paymentSalesPrice);
			}
		}

		return $html;
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *

	 */
	public function plgVmOnPaymentSelectCheck(VirtueMartCart $cart) {
		if (!$this->selectedThis($cart->virtuemart_paymentmethod_id)) {
			return null; // Another method was selected, do nothing
		}
		return true; // this payment was selected , and the data is valid by default
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * payment data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers
	 */
	public function plgVmOnCheckoutCheckPaymentData() {
		return null;
	}

	public function plgVmOnPaymentSelectedCalculatePrice(VirtueMartCart $cart, array &$cart_prices, $payment_name) {
		if (!$this->selectedThis($cart->virtuemart_paymentmethod_id)) {
			return null; // Another payment was selected, do nothing
		}

		if (!($payment = $this->getThisPaymentData($cart->virtuemart_paymentmethod_id) )) {
			return null;
		}

		$payment_name = '';
		$cart_prices['payment_tax_id'] = 0;
		$cart_prices['payment_value'] = 0;

		if (!$this->checkConditions($cart_prices, $payment)) {
			return false;
		}
		$params = new JParameter($payment->payment_params);
		$payment_name = $this->renderPluginName($payment);
		$payment_value = $this->getPaymentValue($params, $cart_prices);
		$payment_tax_id = $this->getPaymentTaxId($params);

		$this->setCartPrices($cart_prices, $payment_value, $payment_tax_id);

		return true;
	}

	/**
	 * plgVmOnPaymentResponseReceived
	 * This event is fired when the payment method returns to the shop after the transaction
	 *
	 *  the payment itself should send in the URL the parameters needed
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param int $virtuemart_order_id : should return the virtuemart_order_id
	 * @param text $html: the html to display
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	function plgVmOnPaymentResponseReceived(&$virtuemart_order_id, &$html) {
		return null;
	}

	/**
	 * This event is fired when  the user return to the shop without doing the transaction.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL.
	 * The order previously created is deleted.. the cart is not emptied, so the user can change the payment, and re-order.
	 * The payment itself should decide which parameter is necessary
	 *
	 * @param int $virtuemart_order_id : return virtuemart_order_id
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	function plgVmOnPaymentUserCancel(&$virtuemart_order_id) {

		return null;
	}

	/**
	 * This event is fired when the payment method notifies you when an event occurs that affects a transaction.
	 * Typically,  the events may also represent authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int $virtuemart_order_id : payment  order id
	 * @param char $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	function plgVmOnPaymentNotification(&$return_context, &$virtuemart_order_id, &$new_status) {
		if ($this->_name != $pelement) {
			return null;
		}
		return false;
	}

	/**
	 * This event is fired after the payment has been processed; it stores the payment method-
	 * specific data.
	 * All plugins *must* reimplement this method.
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *  If this plugin IS executed, it MUST return the order status code that the order should get. This triggers the stock updates if required
	 *
	 * @param int $virtuemart_order_id The order_id being processed
	 * @param VirtueMartCart $cart Data from the cart
	 * @param array $priceData Price information for this order
	 * @return mixed Null when this method was not selected, otherwise the new order status
	 * @author Max Milbers
	 * @author Oscar van Eijk
	 */
	abstract function plgVmOnConfirmedOrderStorePaymentData($virtuemart_order_id, VirtueMartCart $cart, $priceData);

	/**
	 * plgVmOnConfirmedOrderGetPaymentForm
	 * This event is fired after the order has been created
	 * All plugins *must* reimplement this method.
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 * @author Valérie Isaksen
	 * @param the actual order number. IT IS THE ORDER NUMBER THAT MuST BE SENT TO THE FORM. DONT PUT virtuemart_order_id which is a primary key for the order table.
	 * @param orderData
	 * @param contains the session id. Should be sent to the form. And the payment will sent it back.
	 *                  Will be used to empty the cart if necessary, and semnd the order email.
	 * @param the payment form to display. But in some case, the bank can be called directly.
	 * @param false if it should not be changed, otherwise new staus
	 * @return returns 1 if the Cart should be deleted, and order sent
	 */
	abstract function plgVmOnConfirmedOrderGetPaymentForm($order_number, $orderData, $return_context, &$html, &$new_status);

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the the payment method-specific data.
	 * All plugins *must* reimplement this method.
	 *
	 * @param integer $virtuemart_order_id The order ID
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Oscar van Eijk
	 */
	function plgVmOnShowOrderFE($virtuemart_order_id) {
		return parent::getOrderPluginNamebyOrderId($virtuemart_order_id);
	}


	/**
	 * This event is fired each time the status of an order is changed to Cancelled.
	 * It can be used to refund payments, void authorization etc.
	 * Return values are ignored.
	 *
	 * Note for plugin developers: you are not required to reimplement this method, but if you
	 * do so, it MUST start with this code:
	 *
	 * 	$_paymethodID = $this->getPaymentMethodForOrder($_orderID);
	 * 	if (!$this->selectedThisMethod($this->_name, $_paymethodID)) {
	 * 		return;
	 * 	}
	 *
	 * @author Oscar van Eijk
	 * @param int $_orderID
	 * @param char $_oldStat Previous order status
	 * @param char $_newStat New order status
	 */
	/*
	 function plgVmOnCancelPayment($_orderID, $_oldStat, $_newStat) {
	return;
	}
	*/

	/**
	 * plgVmOnShipOrderPayment
	 * This event is fired when the status of an order is changed to Shipped.
	 * It can be used to confirm or capture payments
	 *
	 * Note for plugin developers: you are not required to reimplement this method, but if you
	 * do so, it MUST start with this code:
	 *
	 * 	$_paymethodID = $this->getPaymentMethodForOrder($_orderID);
	 * 	if (!$this->selectedThisMethod($this->_name, $_paymethodID)) {
	 * 		return null;
	 * 	}
	 *
	 * @author Oscar van Eijk
	 * @param int $_orderID Order ID
	 * @return mixed True on success, False on failure, Null if this plugin was not activated
	 */
	public function plgVmOnShipOrderPayment($_orderID) {
		return null;
	}

	/**
	 * getPaymentMethodForOrder
	 * Get the order payment ID for a given order number
	 * @access protected
	 * @author Oscar van Eijk
	 * @param int $_id The order ID
	 * @return int The payment method ID, or -1 when not found
	 */
	protected function getPaymentMethodForOrder($_id) {
		$_db = JFactory::getDBO();
		$_q = 'SELECT `virtuemart_paymentmethod_id` FROM #__virtuemart_orders WHERE virtuemart_order_id = ' . (int) $_id;
		$_db->setQuery($_q);
		if (!($_r = $_db->loadAssoc())) {
			return -1;
		}
		return $_r['virtuemart_paymentmethod_id'];
	}

	/**
	 * get_passkey
	 * Retrieve the payment method-specific encryption key
	 *
	 * @author Oscar van Eijk
	 * @author Valerie Isaksen
	 * @return mixed
	 */
	function get_passkey() {
		return true;
		$_db = JFactory::getDBO();
		$_q = 'SELECT ' . VM_DECRYPT_FUNCTION . "(secret_key, '" . ENCODE_KEY . "') as passkey "
		. 'FROM #__virtuemart_paymentmethods '
		. "WHERE virtuemart_paymentmethod_id='" . (int) $this->_virtuemart_paymentmethod_id . "'";
		$_db->setQuery($_q);
		$_r = $_db->loadAssoc(); // TODO Error check
		return $_r['passkey'];
	}

	/**
	 * This method checks if the selected payment method matches the current plugin
	 * @param string $_name Element name, taken from the plugin filename
	 * @param int $_pid The payment method ID
	 * @author Oscar van Eijk
	 * @return True if the calling plugin has the given payment ID
	 */
	final protected function selectedThis($pid) {
		$db = JFactory::getDBO();

		if (VmConfig::isJ15()) {
			$q = 'SELECT count(*) AS c
            		FROM #__virtuemart_paymentmethods AS vm , #__plugins AS j
            		WHERE vm.virtuemart_paymentmethod_id="' . (int) $pid . '"
            		AND   vm.payment_jplugin_id = j.id
					AND   j.element = "' . $db->getEscaped($this->_name) . '"';
		} else {
			$q = 'SELECT count(*) AS c
            		FROM #__virtuemart_paymentmethods AS vm
            		, #__extensions AS j
            		WHERE vm.virtuemart_paymentmethod_id="' . (int) $pid . '"
            		AND   vm.payment_jplugin_id = j.extension_id
            		AND   j.element = "' . $db->getEscaped($this->_name) . '"';
		}

		$db->setQuery($q);
		return $db->loadResult(); // TODO Error check
	}

	/**
	 * This method checks if the selected payment method matches the current plugin
	 * @param string $_name Element name, taken from the plugin filename
	 * @param int $_pid The payment method ID
	 * @author Oscar van Eijk
	 * @return True if the calling plugin has the given payment ID
	 */
	final protected function getPaymentMethod($payment_id) {
		return parent::getPluginMethod($payment_id);
	}

	/**
	 * Fill the array with all payments found with this plugin for the current vendor
	 * @return True when payment(s) was (were) found for this vendor, false otherwise
	 * @author Oscar van Eijk
	 */
	protected function getPaymentMethods($vendorId) {

		$this->payments = parent::getPluginMethods($vendorId);
		return $this->payments;
	}

	/**
	 * This functions gets the used and configured payment method
	 * pelement of this class determines the used jplugin.
	 * The right payment method is determined by the vendor and the jplugin id.
	 *
	 * This function sets the used payment plugin as variable of this class
	 * @author Max Milbers
	 *
	 */
	protected function getVmPaymentParams($vendorId=0, $payment_id=0) {

		if (!$vendorId)
		$vendorId = 1;
		$db = JFactory::getDBO();

		$q = 'SELECT `payment_params` FROM #__virtuemart_paymentmethods
        		WHERE `virtuemart_paymentmethod_id`="' . $payment_id . '" ';
		$db->setQuery($q);
		return $db->loadResult();
	}

	protected function getPaymentHtml($payment, $selectedPayment, $paymentSalesPrice) {
		return parent::getPluginHtml($payment, $selectedPayment, $paymentSalesPrice);
	}

	/**
	 * Get the name of the payment method
	 * @param int $_pid The payment method ID
	 * @author Oscar van Eijk
	 * @return string Payment method name
	 */
	function getThisPaymentName($payment_id) {

		$db = JFactory::getDBO();

		$q = 'SELECT `payment_name` FROM #__virtuemart_paymentmethods WHERE `virtuemart_paymentmethod_id`="' . (int) $payment_id . '"';
		$db->setQuery($q);
		return $db->loadResult(); // TODO Error check
	}

	/**
	 * Get Payment Data for a go given Payment ID
	 * @author Valérie Isaksen
	 * @param int $virtuemart_payment_id The Payment ID
	 * @return  Payment data
	 */
	final protected function getThisPaymentData($virtuemart_payment_id) {
		return parent::getThisPluginData($virtuemart_payment_id);
	}

	/**
	 * Get Payment Data for a given Payment ID
	 * @author Valérie Isaksen
	 * @param int $virtuemart_payment_id The Payment ID

	 * @return  Payment data
	 */
	final protected function getPaymentDataByOrderId($virtuemart_order_id) {
		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
		. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;

		$db->setQuery($q);
		$payment = $db->loadObject();

		return $payment;
	}

	/*
	 * This method return the number of payment actually valid
	* @author Valerie Isaksen
	*
	* @param VirtueMartCart $cart: the actual cart
	* @param array $cart_prices: the all the prices of the cart
	* @param int $virtuemart_paymentmethod_id
	* @return int the number of valid payments
	*
	*/

/*	function getSelectable(VirtueMartCart $cart, $cart_prices, &$virtuemart_paymentmethod_id) {
		$nbPayments = 0;
		if ($this->getPaymentMethods($cart->vendorId) === false) {
			return false;
		}

		foreach ($this->payments as $payment) {
			if ($this->checkConditions($cart_prices, $payment)) {
				$nbPayments++;
				$virtuemart_paymentmethod_id = (int) $payment->virtuemart_paymentmethod_id;
			}
		}

		return $nbPayments;
	}*/

	/*
	 * Check if the payment conditions are fulfilled for this payment method
	* @author: Valerie Isaksen
	*
	* @param $cart_prices: cart prices
	* @param $payment
	* @return true: if the conditions are fulfilled, false otherwise
	*
	*/

	function checkConditions($cart_prices, $payment) {

		$params = new JParameter($payment->payment_params);
		$amount = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $params->get('min_amount', 0) AND $amount <= $params->get('max_amount', 0)
		OR
		($params->get('min_amount', 0) <= $amount AND ($params->get('max_amount', '') == '') ));

		return $amount_cond;
	}

	function getPaymentValue($params) {
		return $params->get('payment_value', 0);
	}

	function getPaymentTaxId($params) {
		return $params->get('payment_tax_id', 0);
	}

	function getPaymentCost($params, $cart) {
		return $params->get('payment_value', 0);
	}


	/*
	 * @deprecated
	*/
	function checkPaymentIsValid(VirtueMartCart $cart, array $cart_prices) {
		$payment = $this->getThisPaymentData($cart->virtuemart_paymentmethod_id);
		return $this->checkConditions($cart_prices, $payment);
	}



}
