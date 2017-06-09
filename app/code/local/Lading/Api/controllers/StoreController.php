<?php
class Lading_Api_StoreController extends Mage_Core_Controller_Front_Action {
    public function __construct(
      \Zend_Controller_Request_Abstract $request,
      \Zend_Controller_Response_Abstract $response,
      array $invokeArgs = array()
    ) {
        parent::__construct($request, $response, $invokeArgs);
        Mage::helper('mobileapi')->auth();
    }

    /**
     * get website info
     */
    public function websiteInfoAction() {
		// Mage::app ()->getWebsites ();
		// Mage::app ()->getStores ();
		$basicinfo = array ();
		foreach ( Mage::app ()->getWebsites () as $website ) {
			foreach ( $website->getGroups () as $key=> $group ) {
				$basicinfo [$key]['store']=$group->getName();
				$basicinfo [$key]['store_id']=$group->getGroupId ();
				$stores = $group->getStores ();
				foreach ( $stores as $_store ) {
					$basicinfo [$key]['view'][] = array (
							'name' => $_store->getName (),
							'store_id' => $_store->getStoreId (),
							'store_url' => $_store->getHomeUrl (),
							'store_code'=>$_store->getCode(),
							'is_active' =>$_store->getIsActive()
					);
				}
			}
		}
		Mage::helper('mobileapi')->json(array('error'=>0,'msg'=>'get websiteInfo success.', 'result'=>$basicinfo));
		// public function getStoresStructure($isAll = false, $storeIds = array(), $groupIds = array(), $websiteIds = array())
		// Mage::helper('mobileapi')->json ( Mage::getSingleton ( 'adminhtml/system_store' )->getStoresStructure (TRUE) );
		// Mage::helper('mobileapi')->json(Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true));
	}


    /**
     * get store info
     */
    public function storeInfoAction(){
        Mage::helper('mobileapi')->json(array('store_id'=>Mage::app()->getStore()->getStoreId(),
            'store_code'=>Mage::app()->getStore()->getCode(),
            'website_id'=>Mage::app()->getStore()->getWebsiteId(),
            'name'=>Mage::app()->getStore()->getName(),
            'status'=>Mage::app()->getStore()->getIsActive(),
            'url'=> Mage::helper('core/url')->getHomeUrl()
        ));
    }
}
