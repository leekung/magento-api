<?php

class Lading_Api_Helper_Data extends Mage_Core_Helper_Abstract
{
    public $api_key = '38e610f8a15dfb13adb5d0929a7a3108';
    public $response = array(
      'error' => 0,
      'msg' => '',
      'result' => array(),
    );
    public $allow_get_routes = array(

    );

    /**
     * auth API key
     */
    public function auth()
    {
        if (Mage::app()->getRequest()->getParam('key') !== $this->api_key) {
            $currentUrl = Mage::helper('core/url')->getCurrentUrl();
            $url = Mage::getSingleton('core/url')->parseUrl($currentUrl);
            $uri = $url->getPath();
            foreach ($this->allow_get_routes as $route) {
                if (preg_match('#^'.preg_quote($route).'#', $uri)) {
                    return;
                }
            }
            $this->error('Key Incorrect, Please check your key').Mage::app()->getRequest()->getPost('key');
        }
    }

    /**
     * print error
     * @param $message
     */
    protected function error($message)
    {
        $output = array(
          'error' => 1,
          'msg' => $message,
          'result' => array(),
        );

        $this->json($output);
    }


    /**
     * @param $data
     */
    public function json($data) {
        // merge $output
        if (is_array($data)) {
            $data = array_merge($this->response, $data);
        }

        if (preg_match('/MSIE (9|8|7|6)/', Mage::helper('core/http')->getHttpUserAgent())) {
            header('Content-Type: text/plain; charset=utf-8');
        } else {
            header('Content-type: application/json; charset=utf-8');
        }
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo json_encode($data);
        }
        exit;
    }
}
