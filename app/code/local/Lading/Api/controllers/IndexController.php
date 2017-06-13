<?php

/**
 * Class Lading_Api_IndexController
 */
class Lading_Api_IndexController extends Mage_Core_Controller_Front_Action {
    public function __construct(
      \Zend_Controller_Request_Abstract $request,
      \Zend_Controller_Response_Abstract $response,
      array $invokeArgs = array()
    ) {
        parent::__construct($request, $response, $invokeArgs);
        Mage::helper('mobileapi')->auth();
    }

    public function indexAction() {
		Mage::app ()->cleanCache ();
		$cmd = ($this->getRequest ()->getParam ( 'cmd' )) ? ($this->getRequest ()->getParam ( 'cmd' )) : 'daily_sale';
		switch ($cmd) {
			case 'menu' : // OK
				// ---------------------------------列出产品目录-BEGIN-------------------------------------//
                // TODO - cache result to improve speed
                /** @var Mage_Catalog_Model_Category $categoriesArray */
                $categoryModel = Mage::getModel('catalog/category');
                /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
                $collection = $categoryModel->getCollection();

                $categoriesArray = $collection
                  ->addAttributeToSelect('name')
                  ->addAttributeToSort('position', 'asc')
                  ->addAttributeToFilter('is_active', 1)
                  ->load()
                  ->toArray();

                $categories = array();
                // Level 1
                foreach ($categoriesArray as $categoryId => $category) {
                    if (isset($category['name']) && isset($category['level'])
                      && $category['level'] == 2) {
                        $categories[$category['entity_id']] = array(
                          'category_id'  => intval($category['entity_id']),
                          'name'  => $category['name'],
                          'child' => [],
                        );
                    }
                }
                $level2 = array();
                // Level 2
                foreach ($categoriesArray as $categoryId => $category) {
                    if (isset($category['name']) && isset($category['level'])
                      && $category['level'] == 3 && isset($category['parent_id']) && $category['parent_id'] > 0
                      && isset($categories[$category['parent_id']])) {
                        $categories[$category['parent_id']]['child'][$category['entity_id']] = array(
                          'category_id'  => intval($category['entity_id']),
                          'name'  => $category['name'],
                          'child' => [],
                        );
                        $level2[$category['entity_id']] = array(
                          'category_id' => $category['entity_id'],
                          'parent_id' => $category['parent_id'],
                        );
                    }
                }
                // Level 3
                foreach ($categoriesArray as $categoryId => $category) {
                    if (isset($category['name']) && isset($category['level'])
                      && $category['level'] == 4 && isset($category['parent_id']) && $category['parent_id'] > 0
                      && isset($level2[$category['parent_id']])) {
                        $categories[$level2[$category['parent_id']]['parent_id']]['child'][$level2[$category['parent_id']]['category_id']]['child'][] = array(
                          'category_id'  => intval($category['entity_id']),
                          'name'  => $category['name'],
                          'child' => [],
                        );
                    }
                }

                // Final Process
                $categories = array_values($categories);
                foreach ($categories as $id => $category) {
                    $categories[$id]['child'] = array_values($category['child']);
                }

				Mage::helper('mobileapi')->json (array('error'=>0, 'msg'=>'' ,'result'=>$categories));
				// ---------------------------------列出产品目录 END----------------------------------------//

                break;

			case 'catalog' :
//				Mage::app()->getStore()->setCurrentCurrencyCode('CNY');
				$category_id = $this->getRequest ()->getParam ( 'category_id' );
				$page = ($this->getRequest ()->getParam ( 'page' )) ? ($this->getRequest ()->getParam ( 'page' )) : 1;
				$limit = ($this->getRequest ()->getParam ( 'limit' )) ? ($this->getRequest ()->getParam ( 'limit' )) : 5;
				$order = ($this->getRequest ()->getParam ( 'order' )) ? ($this->getRequest ()->getParam ( 'order' )) : 'entity_id';
				$dir = ($this->getRequest ()->getParam ( 'dir' )) ? ($this->getRequest ()->getParam ( 'dir' )) : 'desc';
				// ----------------------------------取某个分类下的产品-BEGIN------------------------------//
				$category = Mage::getModel ( 'catalog/category' )->load ( $category_id );
				$collection = $category->getProductCollection ()->addAttributeToFilter ( 'status', 1 )->addAttributeToFilter ( 'visibility',array('neq' => 1))->addAttributeToSort ( $order, $dir );
				Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
				$pages = $collection->setPageSize ( $limit )->getLastPageNumber ();
				if ($page <= $pages) {
					$collection->setPage ( $page, $limit );
					$product_list = $this->getProductList ( $collection, 'catalog' );
				}else{
					$product_list = array();
				}
				Mage::helper('mobileapi')->json ( array('error'=>0, 'msg'=>'get '.count($product_list).' product success!', 'result'=>$product_list) );
				// ------------------------------取某个分类下的产品-END-----------------------------------//
				break;
			case 'coming_soon' : // 数据ok
				// ------------------------------首页 促销商品 BEGIN-------------------------------------//
				// 初始化产品 Collection 对象
				$page = ($this->getRequest ()->getParam ( 'page' )) ? ($this->getRequest ()->getParam ( 'page' )) : 1;
				$limit = ($this->getRequest ()->getParam ( 'limit' )) ? ($this->getRequest ()->getParam ( 'limit' )) : 5;
				// $todayDate = Mage::app ()->getLocale ()->date ()->toString ( Varien_Date::DATETIME_INTERNAL_FORMAT );
				$tomorrow = mktime ( 0, 0, 0, date ( 'm' ), date ( 'd' ) + 1, date ( 'y' ) );
				$dateTomorrow = date ( 'm/d/y', $tomorrow );
				$tdatomorrow = mktime ( 0, 0, 0, date ( 'm' ), date ( 'd' ) + 3, date ( 'y' ) );
				$tdaTomorrow = date ( 'm/d/y', $tdatomorrow );
				$_productCollection = Mage::getModel ( 'catalog/product' )->getCollection ();
				$_productCollection->addAttributeToSelect ( '*' )->addAttributeToFilter ( 'visibility', array (
					'neq' => 1
				) )->addAttributeToFilter ( 'status', 1 )->addAttributeToFilter ( 'special_price', array (
					'neq' => 0
				) )->addAttributeToFilter ( 'special_from_date', array (
					'date' => true,
					'to' => $dateTomorrow
				) )->addAttributeToFilter ( array (
					array (
						'attribute' => 'special_to_date',
						'date' => true,
						'from' => $tdaTomorrow
					),
					array (
						'attribute' => 'special_to_date',
						'null' => 1
					)
				) )/* ->setPage ( $page, $limit ) */;
				$pages = $_productCollection->setPageSize ( $limit )->getLastPageNumber ();
				// $count=$collection->getSize();
				if ($page <= $pages) {
					$_productCollection->setPage ( $page, $limit );
					$products = $_productCollection->getItems ();
					$productlist = $this->getProductList ( $products );
				}
				Mage::helper('mobileapi')->json ( array('error'=>0, 'msg'=>null, 'result'=>$productlist) );
				// ------------------------------首页 促销商品 END-------------------------------------//
				break;
			case 'best_seller' : // OK
				// ------------------------------首页 预特价商品 BEGIN------------------------------//
				$page = ($this->getRequest ()->getParam ( 'page' )) ? ($this->getRequest ()->getParam ( 'page' )) : 1;
				$limit = ($this->getRequest ()->getParam ( 'limit' )) ? ($this->getRequest ()->getParam ( 'limit' )) : 5;
				$todayDate = Mage::app ()->getLocale ()->date ()->toString ( Varien_Date::DATETIME_INTERNAL_FORMAT );
				$_products = Mage::getModel ( 'catalog/product' )->getCollection ()->addAttributeToSelect ( '*'
                    /*
                    array (
                        'name',
                        'special_price',
                        'news_from_date'
                    )
                    */
                )->addAttributeToFilter ( 'news_from_date', array (
					'or' => array (
						0 => array (
							'date' => true,
							'to' => $todayDate
						),
						1 => array (
							'is' => new Zend_Db_Expr ( 'null' )
						)
					)
				), 'left' )->addAttributeToFilter ( 'news_to_date', array (
					'or' => array (
						0 => array (
							'date' => true,
							'from' => $todayDate
						),
						1 => array (
							'is' => new Zend_Db_Expr ( 'null' )
						)
					)

				), 'left' )->addAttributeToFilter ( array (
					array (
						'attribute' => 'news_from_date',
						'is' => new Zend_Db_Expr ( 'not null' )
					),
					array (
						'attribute' => 'news_to_date',
						'is' => new Zend_Db_Expr ( 'not null' )
					)
				) )->addAttributeToFilter ( 'visibility', array (
					'in' => array (
						2,
						4
					)
				) )->addAttributeToSort ( 'news_from_date', 'desc' )/* ->setPage ( $page, $limit ) */;
				$pages = $_products->setPageSize ( $limit )->getLastPageNumber ();
				// $count=$collection->getSize();
				if ($page <= $pages) {
					$_products->setPage ( $page, $limit );
					$products = $_products->getItems ();
					$product_list = $this->getProductList ( $products );
				}else{
					$product_list = array();
				}
				Mage::helper('mobileapi')->json ( array('error'=>0, 'msg'=>null, 'result'=>$product_list) );
				// ------------------------------首页 预特价商品 END--------------------------------//
				break;
			case 'daily_sale' : // 数据OK
				// -------------------------------首页 特卖商品 BEGIN------------------------------//
				$page = ($this->getRequest ()->getParam ( 'page' )) ? ($this->getRequest ()->getParam ( 'page' )) : 1;
				$limit = ($this->getRequest ()->getParam ( 'limit' )) ? ($this->getRequest ()->getParam ( 'limit' )) : 5;
				$todayDate = Mage::app ()->getLocale ()->date ()->toString ( Varien_Date::DATETIME_INTERNAL_FORMAT );
				$tomorrow = mktime ( 0, 0, 0, date ( 'm' ), date ( 'd' ) + 1, date ( 'y' ) );
				$dateTomorrow = date ( 'm/d/y', $tomorrow );
				// $collection = Mage::getResourceModel ( 'catalog/product_collection' );
				$collection = Mage::getModel ( 'catalog/product' )->getCollection ();
				$collection->/* addStoreFilter ()-> */addAttributeToSelect ( '*' )->addAttributeToFilter ( 'special_price', array (
					'neq' => "0"
				) )->addAttributeToFilter ( 'special_from_date', array (
					'date' => true,
					'to' => $todayDate
				) )->addAttributeToFilter ( array (
					array (
						'attribute' => 'special_to_date',
						'date' => true,
						'from' => $dateTomorrow
					),
					array (
						'attribute' => 'special_to_date',
						'null' => 1
					)
				) );
				$pages = $collection->setPageSize ( $limit )->getLastPageNumber ();
				// $count=$collection->getSize();
				if ($page <= $pages) {
					$collection->setPage ( $page, $limit );
					$products = $collection->getItems ();
					$productlist = $this->getProductList ( $products );
				}
				Mage::helper('mobileapi')->json ( array('error'=>0, 'msg'=>null, 'result'=>$productlist) );
				// echo $count;

				// -------------------------------首页 特卖商品 END------------------------------//
				break;
			case 'new_products' : // 数据OK
				// -------------------------------首页 获取新品 BEGIN------------------------------//
				$page = ($this->getRequest ()->getParam ( 'page' )) ? ($this->getRequest ()->getParam ( 'page' )) : 1;
				$limit = ($this->getRequest ()->getParam ( 'limit' )) ? ($this->getRequest ()->getParam ( 'limit' )) : 5;
				$todayDate = Mage::app ()->getLocale ()->date ()->toString ( Varien_Date::DATETIME_INTERNAL_FORMAT );
//				$tomorrow = mktime ( 0, 0, 0, date ( 'm' ), date ( 'd' ) + 1, date ( 'y' ) );
//				$dateTomorrow = date ( 'm/d/y', $tomorrow );
				// $collection = Mage::getResourceModel ( 'catalog/product_collection' );
				$collection = Mage::getModel ( 'catalog/product' )->getCollection ();
				$collection->/* addStoreFilter ()-> */addAttributeToSelect ( '*' )->addAttributeToSort ( 'created_at', 'desc');
				$pages = $collection->setPageSize ( $limit )->getLastPageNumber ();
				// $count=$collection->getSize();
				if ($page <= $pages) {
					$collection->setPage ( $page, $limit );
					$products = $collection->getItems ();
					$productlist = $this->getProductList ( $products );
				}
				Mage::helper('mobileapi')->json ( array('error'=>0, 'msg'=>null, 'result'=>$productlist) );
				// echo $count;
				// -------------------------------首页 特卖商品 END------------------------------//
				break;
			default :
				// echo 'Your request was wrong.';
			Mage::helper('mobileapi')->json(array('error'=>1, 'msg'=>'Your request was wrong.', 'result'=>array()));
				// echo $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();
				// echo Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();
				break;
		}
	}

	/**
	 * @param $products
	 * @param string $mod
	 * @return array
	 *
	 *
	 */
	public function getProductList($products, $mod = 'product') {
		$baseCurrency = Mage::app ()->getStore ()->getBaseCurrency ()->getCode ();
		$currentCurrency = Mage::app ()->getStore ()->getCurrentCurrencyCode ();
		$store_id = Mage::app()->getStore()->getId();
		$product_list = array();
		foreach ( $products as $product ) {
			if ($mod == 'catalog') {
				$product = Mage::getModel ( 'catalog/product' )->load ( $product ['entity_id'] );
			}
			$summaryData = Mage::getModel('review/review_summary')->setStoreId($store_id)  ->load($product->getId());
			$price = ($product->getSpecialPrice()) == null ? ($product->getPrice()) : ($product->getSpecialPrice());
			$regular_price_with_tax = number_format ( Mage::helper ( 'directory' )->currencyConvert ( $product->getPrice (), $baseCurrency, $currentCurrency ), 2, '.', '' );
			$final_price_with_tax = number_format ( Mage::helper ( 'directory' )->currencyConvert ( $product->getSpecialPrice (), $baseCurrency, $currentCurrency ), 2, '.', '' );
			$temp_product = array(
				'entity_id' => $product->getId (),
				'sku' => $product->getSku (),
				'name' => $product->getName (),
				'rating_summary' => $summaryData->getRatingSummary(),
				'reviews_count' => $summaryData->getReviewsCount(),
				'news_from_date' => $product->getNewsFromDate (),
				'news_to_date' => $product->getNewsToDate (),
				'special_from_date' => $product->getSpecialFromDate (),
				'special_to_date' => $product->getSpecialToDate (),
				'image_url' => $product->getImageUrl (),
				'url_key' => $product->getProductUrl (),
				'price' => number_format(Mage::getModel('mobile/currency')->getCurrencyPrice($price),2,'.',''),
				'regular_price_with_tax' =>  number_format(Mage::getModel('mobile/currency')->getCurrencyPrice($regular_price_with_tax),2,'.',''),
				'final_price_with_tax' =>  number_format(Mage::getModel('mobile/currency')->getCurrencyPrice($final_price_with_tax),2,'.',''),
				'symbol'=> Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol(),
				'stock_level' => (int)Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getQty()
			);
			array_push($product_list,$temp_product);
		}
		return $product_list;
	}
}