<?php
class Lading_Api_WishlistController extends Mage_Core_Controller_Front_Action {
    public function __construct(
      \Zend_Controller_Request_Abstract $request,
      \Zend_Controller_Response_Abstract $response,
      array $invokeArgs = array()
    ) {
        parent::__construct($request, $response, $invokeArgs);
        Mage::helper('mobileapi')->auth();
    }

	/**
     * add item to user wish list
     */
	public function addAction() {
		$return_result = array(
			'error' => 0,
			'msg' => null,
			'result' => null
		);
		if (! Mage::getStoreConfigFlag('wishlist/general/active')) {
			$return_result ['error'] = 1;
			$return_result ['msg'] = 'Wishlist Has Been Disabled By Admin';
			Mage::helper('mobileapi')->json($return_result);
			return;
		}
		if (! Mage::getSingleton('customer/session')->isLoggedIn()) {
			$return_result ['error'] = 1;
			$return_result ['msg'] = 'Please Login First';
			Mage::helper('mobileapi')->json($return_result);
			return;
		}
		$customer_id = Mage::getSingleton('customer/session')->getId();
		$customer = Mage::getModel('customer/customer');
		$wishlist = Mage::getModel('wishlist/wishlist');
		$product  = Mage::getModel('catalog/product');
        $product_id = $this->getRequest ()->getParam ('product_id');
		$customer->load($customer_id);
		$wishlist->loadByCustomer($customer_id);
		if($customer_id && $product_id){
			$res = $wishlist->addNewItem($product->load($product_id));
			if($res){
				$return_result['error'] = 0;
				$return_result['msg'] = "your product has been added in wishlist";
				$return_result['result'] = $res;
			}
			Mage::helper('mobileapi')->json($return_result);
		}else{
			$return_result['error'] = 1;
			$return_result['msg'] = 'can not get customer info or product id';
			$return_result['result'] = null;
			Mage::helper('mobileapi')->json($return_result);
		}
	}



    /**
     * get user wish list action
     */
	public function getWishlistAction(){
		if (Mage::getSingleton('customer/session')->isLoggedIn()) {
			Mage::helper('mobileapi')->json(
				array(
					'error' => 0,
					'msg' => 'get user wish list success!',
					'result' => $this->_getWishlist()
				)
			);
		}else{
			Mage::helper('mobileapi')->json(array(
				'error' => 1,
				'msg' => 'not user login',
				'result'=>array ()
			));
		}
	}




	/**
	 * delete wish list action
	 */
	public function delAction(){
        $product_id = $this->getRequest ()->getParam ('product_id');
		if (Mage::getSingleton('customer/session')->isLoggedIn()) {
			$customer_id =  Mage::getSingleton ( 'customer/session' )->getCustomer ()->getId();
			$item_collection = Mage::getModel('wishlist/item')->getCollection()->addCustomerIdFilter($customer_id);
			foreach($item_collection as $item) {
				if($item->getProductId()==$product_id){
					$item->delete();
				}
			}
			Mage::helper('mobileapi')->json(
				array(
					'error' => 0,
					'msg' => 'delete wish list product '.$product_id.' success!',
					'result' => $this->_getWishlist()
				)
			);
		}else{
			Mage::helper('mobileapi')->json(array(
				'error' => 1,
				'msg' => 'not user login',
				'result'=>array ()
			));
		}
	}


    /**
     * get wish list method
     * @return array|bool
     */
	protected function _getWishlist() {
		$wishlist = Mage::registry ( 'wishlist' );
		$store_id = Mage::app()->getStore()->getId();
		$baseCurrency = Mage::app ()->getStore ()->getBaseCurrency ()->getCode ();
		$currentCurrency = Mage::app ()->getStore ()->getCurrentCurrencyCode ();
		if ($wishlist) {
			return $wishlist;
		}
		try {
			$wishlist = Mage::getModel ( 'wishlist/wishlist' )->loadByCustomer ( Mage::getSingleton ( 'customer/session' )->getCustomer (), true );
			Mage::register ( 'wishlist', $wishlist );
		} catch ( Mage_Core_Exception $e ) {
			Mage::getSingleton ( 'wishlist/session' )->addError ( $e->getMessage () );
		} catch ( Exception $e ) {
			Mage::getSingleton ( 'wishlist/session' )->addException ( $e, Mage::helper ( 'wishlist' )->__ ( 'Cannot create wishlist.' ) );
			return false;
		}
		$items = array ();
		foreach ( $wishlist->getItemCollection () as $item ) {
			$item = Mage::getModel ( 'catalog/product' )->setStoreId ( $item->getStoreId () )->load ( $item->getProductId () );
			$summaryData = Mage::getModel('review/review_summary')->setStoreId($store_id)  ->load($item->getId());
			if ($item->getId ()) {
				$price = Mage::getModel('mobile/currency')->getCurrencyPrice(($item->getSpecialPrice()) == null ? ($item->getPrice()) : ($item->getSpecialPrice()));
				$items [] = array (
					'name' => $item->getName (),
					'image_url' => $item->getImageUrl (),
					'url_key' => $item->getProductUrl (),
					'rating_summary' => $summaryData->getRatingSummary(),
					'reviews_count' => $summaryData->getReviewsCount(),
					'entity_id' => $item->getId (),
					'regular_price_with_tax' => number_format ( Mage::helper ( 'directory' )->currencyConvert ( $item->getPrice (), $baseCurrency, $currentCurrency ), 2, '.', '' ),
					'final_price_with_tax' => number_format ( Mage::helper ( 'directory' )->currencyConvert ( $item->getSpecialPrice (), $baseCurrency, $currentCurrency ), 2, '.', '' ),
					'price' => number_format($price, 2, '.', '' ),
					'sku' => $item->getSku(),
					'symbol' => Mage::app()->getLocale()->currency ( Mage::app ()->getStore ()->getCurrentCurrencyCode () )->getSymbol (),
					'stock_level' => (int)Mage::getModel('cataloginventory/stock_item')->loadByProduct($item)->getQty(),
					'short_description' => $item->getShortDescription()
				);
			}
		}
		return array (
			'wishlist' => $wishlist->getData (),
			'items' => $items
		);
	}
} 
