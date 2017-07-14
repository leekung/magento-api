<?php
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 5/28/15
 * Time: 3:10 PM
 */


class Lading_Api_CheckoutController extends Mage_Core_Controller_Front_Action{
    public function __construct(
      \Zend_Controller_Request_Abstract $request,
      \Zend_Controller_Response_Abstract $response,
      array $invokeArgs = array()
    ) {
        parent::__construct($request, $response, $invokeArgs);
        Mage::helper('mobileapi')->auth();
    }


    /**
     * get use all address list
     */
    public function getAddressListAction(){
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quote_shipping_address_id = $quote->getShippingAddress()->getCustomerAddressId();
        $quote_billing_address_id = $quote->getBillingAddress()->getCustomerAddressId();
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $addressList = Mage::getModel('mobile/address')->getCustomerAddressList($customer);
        foreach($addressList as $key=>$address){
            if($address['entity_id'] ==  $quote_shipping_address_id){
                $addressList[$key]['is_quote_shipping'] = true;
            }
            if($address['entity_id'] ==  $quote_billing_address_id){
                $addressList[$key]['is_quote_billing'] = true;
            }
        }
        Mage::helper('mobileapi')->json(array(
            'error'=>0,
            'msg'=>'get order address success!',
            'result'=>$addressList
        ));
    }



    /**
     * get user address list by current quote
     */
    public function getAddressByQuoteAction(){
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if($quote->getId()){
            $address = Mage::getModel('mobile/checkout')->getAddressByQuote($quote);
            Mage::helper('mobileapi')->json(array(
                'error'=>0,
                'msg'=>' get quote address success!',
                'result'=>$address
            ));
        }else{
            Mage::helper('mobileapi')->json(array(
                'error'=>1,
                'msg'=>'can not get quote!',
                'result'=>null
            ));
        }
    }




    /**
     * Get list of available shipping methods
     * @return array
     */
    public function getShippingMethodsListAction(){
        $return_result = array (
            'error' => 0,
            'msg' => 'get shipping method list success!',
            'result' => null
        );
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quoteShippingAddress = $quote->getShippingAddress();
        if (is_null($quoteShippingAddress->getId())) {
            $return_result['msg'] = 'shipping_address_is_not_set';
            $return_result['error'] = 1;
            Mage::helper('mobileapi')->json($return_result);
            return;
        }
        $return_result['result'] = Mage::getModel('mobile/checkout')->getShippingMethodListByQuote($quote);
        Mage::helper('mobileapi')->json($return_result);

    }



    /**
     * Get list of available shipping methods
     * @return array
     */
    public function getPayMethodsListAction(){
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $payment_methods = Mage::getModel('mobile/checkout')->getActivePaymentMethods($quote);
        Mage::helper('mobileapi')->json(array(
            'error'=> 0,
            'msg'=> 'get payment methods success!',
            'result'=> $payment_methods
        ));
    }


    /**
     * Get list of available shipping methods
     * @return array
     */
    public function setBillingAction(){
        $return_result = array (
            'error' => 0,
            'msg' => 'set billing address success!',
            'result' => null,
        );
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('billing', array());
            $customerAddressId = $this->getRequest()->getPost('billing_address_id', false);
            if (isset($data['email'])) {
                $data['email'] = trim($data['email']);
            }
            $result = Mage::getSingleton('checkout/type_onepage')->saveBilling($data, $customerAddressId);
            if (!isset($result['error'])) {
                if (Mage::getSingleton('checkout/type_onepage')->getQuote()->isVirtual()) {
                    $result['goto_section'] = 'payment';
                } elseif (isset($data['use_for_shipping']) && $data['use_for_shipping'] == 1) {
                    $result['goto_section'] = 'shipping_method';
                    $result['allow_sections'] = array('shipping');
                    $result['duplicateBillingInfo'] = 'true';
                } else {
                    $result['goto_section'] = 'shipping';
                }
            }
            if (isset($result['error']) && !empty($result['error'])){
                $return_result['error'] = 1;
                $return_result['msg'] = 'set billing address fail! ' . $result['message'];
            }
            $return_result['result'] = $result;
        }
        Mage::helper('mobileapi')->json($return_result);
    }

    /**
     * Get list of available shipping methods
     * @return array
     */
    public function setShippingAction(){
        $return_result = array (
            'error' => 0,
            'msg' => 'save shipping address success!',
            'result' => null
        );
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shipping', array());
            $customerAddressId = $this->getRequest()->getPost('shipping_address_id', false);
            $result = Mage::getSingleton('checkout/type_onepage')->saveShipping($data, $customerAddressId);
            if (!isset($result['error'])) {
                $result['goto_section'] = 'shipping_method';
                $result['update_section'] = array(
                    'name' => 'shipping-method',
                );
            }
            $return_result['result'] = $result;
        }
        Mage::helper('mobileapi')->json($return_result);
    }



    /**
     * Get list of available shipping methods
     * @return array
     */
    public function setShippingMethodAction(){
        $return_result = array (
            'error' => 0,
            'msg' => 'save shipping method success!',
            'result' => null
        );
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shipping_method', '');
            $result = Mage::getSingleton('checkout/type_onepage')->saveShippingMethod($data);
            // $result will contain error data if shipping method is empty
            if (!$result) {
                Mage::getSingleton('checkout/type_onepage')->getQuote()->collectTotals();
            }
            if (isset($result['error']) && !empty($result['error'])){
                $return_result['msg'] = $result['message'];
            }
            $return_result['result'] = $result;
            Mage::getSingleton('checkout/type_onepage')->getQuote()->collectTotals()->save();

        }
        Mage::helper('mobileapi')->json($return_result);
    }



    /**
     * Get list of available shipping methods
     * @return array
     */
    public function setPayMethodAction(){
        $return_result = array (
            'error' => 0,
            'msg' => 'save payment method success!',
            'result' => null
        );
        try {
            if ($this->getRequest()->isPost()) {
                $data = $this->getRequest()->getPost('payment', array());
                $saveResult = Mage::getSingleton('checkout/type_onepage')->savePayment($data);
                if (isset($saveResult['error'])) {
                    $result['success'] = false;
                    $result['messages'][] = $saveResult['message'];
                }
                Mage::getSingleton('checkout/type_onepage')->getQuote()->collectTotals()->save();
            } else {
                $result['error'] = 1;
                $result['msg'][] = 'Please specify payment method.';
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $result['error'] = 1;
            $result['msg'][] = 'Unable to set Payment Method.';
        }
        Mage::helper('mobileapi')->json($return_result);
    }



    /**
     * 获取订单详细
     */
    public function getOrderReviewAction(){
        $orderReviewArr = array();
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $cartItemsArr = array();
        $virtual_flag = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            $renderer = new Mage_Checkout_Block_Cart_Item_Renderer();
            $renderer->setItem($item);
            $cartItemArr = array();
            $cartItemArr['item_id'] = $item->getProduct()->getId();
            $cartItemArr['cart_item_id'] = $item->getId();
            $cartItemArr['item_title'] = strip_tags($renderer->getProductName());
            $cartItemArr['qty'] = $renderer->getQty();
            $cartItemArr['product_type'] = $item->getProductType();
            if($item->getProductType()=='bundle'){
                $cartItemArr['bundle_option'] =  Mage::getModel('mobile/cart')->getProductBundleOptions($item);
            }
            if($item->getProductType()=='configurable'){
                $cartItemArr['custom_option'] =  Mage::getModel('mobile/cart')->getCustomOptions($item);
            }
            $virtual_flag = $item->getProduct()->isVirtual();
            $cartItemArr['thumbnail_pic_url'] = ( string ) Mage::helper('catalog/image')->init($item->getProduct(), 'thumbnail')->resize ( 250 );;
            $exclPrice = $inclPrice = 0.00;
            if (Mage::helper('tax')->displayCartPriceExclTax() || Mage::helper('tax')->displayCartBothPrices()) {
                if (Mage::helper('weee')->typeOfDisplay($item, array(0, 1, 4), 'sales') && $item->getWeeeTaxAppliedAmount()) {
                    $exclPrice = $item->getCalculationPrice() + $item->getWeeeTaxAppliedAmount() + $item->getWeeeTaxDisposition();
                } else {
                    $exclPrice = $item->getCalculationPrice();
                }
            }
            if (Mage::helper('tax')->displayCartPriceInclTax() || Mage::helper('tax')->displayCartBothPrices()) {
                $_incl = Mage::helper('checkout')->getPriceInclTax($item);
                if (Mage::helper('weee')->typeOfDisplay($item, array(0, 1, 4), 'sales') && $item->getWeeeTaxAppliedAmount()) {
                    $inclPrice = $_incl + $item->getWeeeTaxAppliedAmount();
                } else {
                    $inclPrice = $_incl - $item->getWeeeTaxDisposition();
                }
            }
            $exclPrice = Mage::helper('xmlConnect')->formatPriceForXml($exclPrice);
            $formatedExclPrice = $quote->getStore()->formatPrice($exclPrice, false);
            $inclPrice = Mage::helper('xmlConnect')->formatPriceForXml($inclPrice);
            $formatedInclPrice = $quote->getStore()->formatPrice($inclPrice, false);
            if (Mage::helper('tax')->displayCartBothPrices()) {
                $cartItemArr['price_excluding_tax'] = $exclPrice;
                $cartItemArr['price_including_tax'] = $inclPrice;
            } else {
                if (Mage::helper('tax')->displayCartPriceExclTax()) {
                    $cartItemArr['item_price'] = $exclPrice;
                }
                if (Mage::helper('tax')->displayCartPriceInclTax()) {
                    $cartItemArr['item_price'] = $inclPrice;
                }
            }
            if ($_options = $renderer->getOptionList()) {
                $optionsArr = array();
                foreach ($_options as $_option) {
                    $optionArr = array();
                    $_formatedOptionValue = $renderer->getFormatedOptionValue($_option);
                    $optionsArr = $optionsArr . strip_tags($_option['label']) . ':' . strip_tags($_formatedOptionValue['value']) . ',';
                }
                $optionsArr = substr($optionsArr, 0, strlen($optionsArr) - 1);
                $cartItemArr['skus'] = $optionsArr;
            }
            if ($messages = $renderer->getMessages()) {
                $itemMessagesArr = array();
                foreach ($messages as $message) {
                    $itemMessageArr = array();
                    $itemMessageArr['type'] = $message['type'];
                    $itemMessageArr['text'] = strip_tags($message['text']);
                    array_push($itemMessagesArr, $itemMessageArr);
                }
                $cartItemArr['item_messages'] = $itemMessagesArr;
            }
            array_push($cartItemsArr, $cartItemArr);
        }
        $orderReviewArr['items'] = $cartItemsArr;
//        $orderReviewArr['order_id'] = $this->getOnepage()->getLastOrderId();
        $orderReviewArr['coupon'] = Mage::getModel('mobile/checkout')->getCouponByQuote($quote);
        $orderReviewArr['pay_method'] = Mage::getModel('mobile/checkout')->getPaymentMethodByQuote($quote);
        $orderReviewArr['address'] = Mage::getModel('mobile/checkout')->getAddressByQuote($quote);
//        $orderReviewArr['selected_shipping_method_id'] = $quote->getShippingAddress()->getShippingMethod();
        $orderReviewArr['subtotal'] = $quote->getShippingAddress()->getSubtotal();
        $orderReviewArr['base_discount_amount'] = number_format(Mage::getModel('mobile/currency')->getCurrencyPrice($quote->getShippingAddress()->getBaseDiscountAmount()),2,'.','');
        $orderReviewArr['grand_total'] = $quote->getShippingAddress()->getGrandTotal();
        $orderReviewArr['shipping_method'] = Mage::getModel('mobile/checkout')->getShippingMethodByQuote($quote);
        $orderReviewArr['symbol'] = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();
        $orderReviewArr['is_virtual'] = $virtual_flag;
        Mage::helper('mobileapi')->json(array(
            'error'=> 0,
            'msg'=> 'get order review success!',
            'result'=> $orderReviewArr
        ));
    }


    /**
     * get form key
     */
    public function getFormKeyAction(){
        $form_key = Mage::getSingleton('core/session')->getFormKey();
        $return_result = array (
            'error' => 0,
            'msg' => 'get form key success!',
            'result' => $form_key
        );
        Mage::helper('mobileapi')->json($return_result);
    }


    /**
     * Order success action
     */
    public function successAction()
    {
        $return_result = array (
            'error' => 0,
            'msg' => 'get success info success!',
            'result' => null
        );
        $session = Mage::getSingleton('checkout/type_onepage')->getCheckout();
        if (!$session->getLastSuccessQuoteId()) {
            $this->_redirect('checkout/cart');
            return;
        }
        $lastQuoteId = $session->getLastQuoteId();
        $lastOrderId = $session->getLastOrderId();
        $lastRecurringProfiles = $session->getLastRecurringProfileIds();
        if (!$lastQuoteId || (!$lastOrderId && empty($lastRecurringProfiles))) {
            $this->_redirect('checkout/cart');
            $return_result['error'] = 1;
            $return_result['msg'] = 'already load success message!';
            Mage::helper('mobileapi')->json($return_result);
            return;
        }
        $order_info = Mage::getModel('mobile/order')->getOrderByEntityId($lastOrderId);
        //$session->clear();
        $return_result['result'] = array(
            'order_id' => $order_info['order_id']
        );
        Mage::helper('mobileapi')->json($return_result);
    }


}