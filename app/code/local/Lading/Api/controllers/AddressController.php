<?php
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 5/14/15
 * Time: 2:27 PM
 */


/**
 * Class Lading_Api_CustomerController
 */
class Lading_Api_AddressController extends Mage_Core_Controller_Front_Action
{
    public function __construct(
      \Zend_Controller_Request_Abstract $request,
      \Zend_Controller_Response_Abstract $response,
      array $invokeArgs = array()
    ) {
        parent::__construct($request, $response, $invokeArgs);
        Mage::helper('mobileapi')->auth();
    }

    /**
     * 获取用户地址列表
     */
    public function getAddressListAction(){
        $result = array (
            'error' => 0,
            'msg' => null,
            'result' => null
        );
        $session = Mage::getSingleton('customer/session');
        if (!$session->isLoggedIn()) {
            $result['error'] = 1;
            $result['msg'] = 'user is not login';
            Mage::helper('mobileapi')->json($result);
            return;
        }
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $addressList = Mage::getModel('mobile/address')->getCustomerAddressList($customer);
        Mage::helper('mobileapi')->json(
            array(
                'error' => 0,
                'msg' => 'get user address list success!',
                'result' => $addressList
            )
        );
    }



    /**
     * 获取用户地址
     */
    public function getAddressAction(){
        $result = array (
            'error' => 0,
            'msg' => null,
            'result' => null
        );
        $session = Mage::getSingleton('customer/session');
        if (!$session->isLoggedIn()) {
            $result['error'] = 1;
            $result['msg'] = 'user is not login';
            Mage::helper('mobileapi')->json($result);
            return;
        }
        $addressId = $this->getRequest()->getParam( 'address_id' );
        $return_address = Mage::getModel('mobile/address')->getAddressById($addressId);
        Mage::helper('mobileapi')->json(
            array(
                'error' => 0,
                'msg' => 'get user address success!',
                'result' => $return_address
            )
        );
    }




    /**
     * Delete address
     * @return boolean
     */
    public function deleteAction(){
        $addressId = $this->getRequest()->getParam ( 'address_id' );
        $result = array (
            'error' => 0,
            'msg' => null,
            'result' => true
        );
        $address = Mage::getModel('customer/address')
            ->load($addressId);
        if (!$address->getId()) {
            $result['msg'] = 'not_exists';
            $result['result'] = false;
        }
        try {
            $address->delete();
        } catch (Mage_Core_Exception $e) {
            $result['msg'] = $e->getMessage();
            $result['result'] = false;
        }
        Mage::helper('mobileapi')->json($result);
    }

    /**
     * Create new address for customer
     * @return mixed
     */
    public function createAction(){
        $session = Mage::getSingleton('customer/session');
        $result = array (
            'error' => 0,
            'msg' => null,
            'result' => null
        );
        if (!$session->isLoggedIn()) {
            $result['error'] = 1;
            $result['msg'] = 'user is not login';
            Mage::helper('mobileapi')->json($result);
            return;
        }
        $addressData = array();
        $addressData['address_book_id'] = $_REQUEST['address_book_id'];
        $addressData['address_type'] = $_REQUEST['address_type'];
        $addressData['lastname'] = $_REQUEST['lastname'];
        $addressData['firstname'] = $_REQUEST['firstname'];
        $addressData['suffix'] = $_REQUEST['suffix'];
        $addressData['telephone'] = $_REQUEST['telephone'];
        $addressData['company'] = $_REQUEST['company'];
        $addressData['fax'] = $_REQUEST['fax'];
        $addressData['postcode'] = $_REQUEST['postcode'];
        $addressData['city'] = $_REQUEST['city'];
        $addressData['address1'] = $_REQUEST['address1'];
        $addressData['address2'] = $_REQUEST['address2'];
        $addressData['country_name'] = $_REQUEST['country_name'];
        $addressData['country_id'] = $_REQUEST['country_id'];
        $addressData['state'] = $_REQUEST['state'];
        $addressData['zone_name'] = $_REQUEST['zone_name'];
        $addressData['zone_id'] = $_REQUEST['zone_id'];
        if (!is_null($addressData)) {
            $customer = $session->getCustomer();
            $address = Mage::getModel('customer/address');
            $addressId = $addressData['address_book_id'];
            if ($addressId) {
                $existsAddress = $customer->getAddressById($addressId);
                if ($existsAddress->getId() && $existsAddress->getCustomerId() == $customer->getId()) {
                    $address->setId($existsAddress->getId());
                }
            }
            $errors = array();
            try {
                $addressType = explode(',', $addressData['address_type']);
                $address->setCustomerId($customer->getId())
                    ->setIsDefaultBilling(strtolower($addressType[0]) == 'billing' || strtolower($addressType[1]) == 'billing')
                    ->setIsDefaultShipping(strtolower($addressType[0]) == 'shipping' || strtolower($addressType[1]) == 'shipping');
                $address->setLastname($addressData['lastname']);
                $address->setFirstname($addressData['firstname']);
                $address->setSuffix($addressData['suffix']);
                $address->setTelephone($addressData['telephone']);
                $address->setCompany($addressData['company']);
                $address->setFax($addressData['fax']);
                $address->setPostcode($addressData['postcode']);
                $address->setCity($addressData['city']);
                $address->setStreet(array($addressData['address1'], $addressData['address2']));
                $address->setCountry($addressData['country_name']);
                $address->setCountryId($addressData['country_id']);
                if (isset($addressData['state'])) {
                    $address->setRegion($addressData['state']);
                    $address->setRegionId(null);
                } else {
                    $address->setRegion($addressData['zone_name']);
                    $address->setRegionId($addressData['zone_id']);
                }
                $addressErrors = $address->validate();
                if ($addressErrors !== true) {
                    $errors = array_merge($errors, $addressErrors);
                }
                $addressValidation = count($errors) == 0;
                if (true === $addressValidation) {
                    $address->save();
                    $result['error'] = 0;
                    $result['msg'] = 'save or update user address success!';
                    Mage::helper('mobileapi')->json($result);
                    return;
                } else {
                    if (is_array($errors)) {
                        $result['error'] = 1;
                        $result['msg'] = $errors;
                    } else {
                        $result['error'] = 1;
                        $result['msg'] = 'Can\'t save or update address';
                    }
                    Mage::helper('mobileapi')->json($result);
                    return;
                }
            } catch (Mage_Core_Exception $e) {
                $result['error'] = 1;
                $result['msg'] = $e->getMessage();
                Mage::helper('mobileapi')->json($result);
                return;
            } catch (Exception $e) {
                $result['error'] = 1;
                $result['msg'] = $e->getMessage();
                Mage::helper('mobileapi')->json($result);
                return;
            }
        } else {
            $result['error'] = 1;
            $result['msg'] = 'address data is null!';
            Mage::helper('mobileapi')->json($result);
            return;
        }
    }


}

