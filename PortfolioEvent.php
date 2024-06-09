<?php
namespace Plugin\Portfolio;

use Eccube\Application;
use Eccube\Event\EventArgs;
use Eccube\Exception\CartException;
use Eccube\Exception\ShoppingException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Eccube\Common\Constant;
use Eccube\Entity\Customer;
use Eccube\Entity\Delivery;
use Eccube\Entity\MailHistory;
use Eccube\Entity\Order;
use Eccube\Entity\OrderDetail;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ShipmentItem;
use Eccube\Entity\Shipping;

class PortfolioEvent
{

    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }


    public function portfolio(FilterResponseEvent $event, $app)
    {

        $request = $event->getRequest();
        $response = $event->getResponse();

        $id = $request->get('id');

        $TargetWork = $this->app['eccube.plugin.repository.portfolio_data']->findOrder($id);
        $TargetWork[0]['name'];
        $addContent = $TargetWork[0]['name'];
        // 書き換えhtmlの初期化
        $html = $response->getContent();

        // 書き換え処理ここから
        $crawler = new Crawler($html);
        $oldElement = $crawler->filter('.item_detail');
        $oldHtml = $oldElement->html();
        $newHtml = $addContent . $oldHtml;
        $html = $crawler->html();
        $html = str_replace($oldHtml, $newHtml, $html);
        // 書き換え処理ここまて?

        $response->setContent($html);
        $event->setResponse($response);
    }


    public function afterLogin(FilterResponseEvent $event, $app)
    {
        $session = $event->getRequest()->getSession();
        if ($session->get('portfolio_redi') != null) {
            $session->remove('portfolio_redi');
            $response = $this->app->redirect($this->app->url('canvas_redirect'));
            $event->setResponse($response);
        } else {
            $response = $this->app->redirect($this->app->url('homepage'));
        }
    }


    public function beforeOrder($app)
    {
        $orderId = $this->app['session']->get('eccube.front.shopping.order.id');

        if (!preg_match("/\,/", $this->app['session']->get('portfolios'))) {
//            $unqid = str_replace(",", "", $this->app['session']->get('portfolios'));
	    $portfolio = $this->app['eccube.plugin.repository.portfolio_data']->find($this->app['session']->get('portfolios'));
	    $portfolio->setOrderid($orderId);
	    $status = $this->app['eccube.plugin.repository.portfolio_data']->update($portfolio);
            $this->app['session']->remove('portfolios');
        } else {
            $portfolios = explode(',', $this->app['session']->get('portfolios'));
	    foreach ($portfolios as $unqid) {
	        if($unqid == '') { continue; }
	        $portfolio = $this->app['eccube.plugin.repository.portfolio_data']->find($unqid);
	        $portfolio->setOrderid($orderId);
	        $status = $this->app['eccube.plugin.repository.portfolio_data']->update($portfolio);
	    }
            $this->app['session']->remove('portfolios');
        }
 
        // session削除(全作品ID)
        $this->app['session']->remove('portfolios');
    }


    // カートインした商品は作成不可
    public function afterCart($app)
    {
        $portfolios = $this->app['session']->get('portfolios');
        $portfolios = preg_replace('/^\,(.*?)/', '', $portfolios);
        $portfolios = explode(',', $portfolios);
        $products = array();
        $product_cls = array();
        foreach ($portfolios as $unqid){
	    $portfolio = $this->app['eccube.plugin.repository.portfolio_data']->find($unqid);
            $products[] = $portfolio['product_id'];
            $product_cls[] = $portfolio['product_class_id'];
        }
        $this->app['twig']->addGlobal('products', $products);
        $this->app['twig']->addGlobal('product_cls', $product_cls);
    }


    public function beforeCart($app)
    {
        $this->app['session']->set('cartref', 1);
        $Cart = $this->app['eccube.service.cart']->getCart();

        if ($this->app['session']->get('order')) {
            $buf = null;
            $portfolios = $this->app['eccube.plugin.repository.portfolio_data']->findOrder($this->app['session']->get('order'));
            foreach ($portfolios as $portfolio) {
		foreach($Cart['CartItems'] as $cartitem) {
		    if ($cartitem['class_id'] == $portfolio['product_class_id']) {
		        $buf .= "," .$portfolio['id'];
		    }
		}
            }
            $this->app['session']->set('portfolios', $buf);
        }
        $unqids = explode(',', $this->app['session']->get('portfolios'));
        $portfolios = array();
	foreach ($unqids as $unqid) {
	    if($unqid == '') { continue; }
	    $portfolios[] = $this->app['eccube.plugin.repository.portfolio_data']->find($unqid);
	}
        $this->app['twig']->addGlobal('portfolios', $portfolios);
    }


    public function beforeShopping($app)
    {
        $unqids = explode(',', $this->app['session']->get('portfolios'));
        $portfolios = array();
	foreach ($unqids as $unqid) {
	    if($unqid == '') { continue; }
	    $portfolios[] = $this->app['eccube.plugin.repository.portfolio_data']->find($unqid);
	}
        $this->app['twig']->addGlobal('portfolios', $portfolios);

/*
        $Order = $this->app['eccube.service.shopping']->getOrder($this->app['config']['order_processing']);
        $shipments = $this->app['eccube.repository.shipment_item']->findByOrder($Order['id']);
        $customer = $this->app->user();
        $this->app['eccube.service.shopping']->setDeliveryFreeShipping($Order);
        $Order->setTotal($Order['subtotal']);
        $Order->setPaymentTotal($Order['subtotal']);
$this->app['eccube.service.shopping']->setOrderUpdateData($Order);

dump($Order);
*/
   }


    public function mypageHistory($app)
    {
        $orderid = $this->app['request']->attributes->get('id');
        $portfolios = $this->app['eccube.plugin.repository.portfolio_data']->findOrder($orderid);
        if ($this->app['session']->get('portfolios')) {
            $btnflg = 1;
        } else {
            $btnflg = 0;
        }
//dump($this->app['session']->get('portfolios'));
        $this->app['session']->set('customer', $this->app->user());
        $this->app['session']->set('order', $orderid);
        $this->app['twig']->addGlobal('portfolios', $portfolios);
        $this->app['twig']->addGlobal('btnflg', $btnflg);
   }


    public function mypageFavorite($app)
    {
//if($_SERVER['REMOTE_ADDR'] != '218.231.171.229') { echo "デバッグ中"; exit; }
        $customer = $this->app->user();
        if (empty($customer['id'])){
            return $app->redirect($app->url('mypage_login'));
        }

        $bufpf = $this->app['eccube.plugin.repository.portfolio_data']->findByCustomer($customer['id']);
        $portfolios = array();
        foreach ($bufpf as $pf) {
            if ($pf['order_id'] != 0) {
                continue;
            } else {
                $portfolios[] = $pf;
            }
        }

        $cont = 0;
        $pricies = array();
        $products = array();
        $pricelists = array();
        foreach ($portfolios as $portfolio) {
            if(!$portfolio){ break; }
            // 規格取得
            $Product = $this->app['eccube.repository.product']->find($portfolio['product_id']);
            $products[] = $Product['name'];
            $ProductClasses = $Product->getProductClasses();
            $classCategories = array();
            foreach ($ProductClasses as $class) {
                preg_match("/^([0-9]{1,3})/", $class['ClassCategory1'], $match);
                $classCategories['pricelist['.$match[0].']'] = $class['price02'];
                if ($portfolio['product_class_id'] == $class['id']){
                    $price = $class['price02'];
                }
            }
	    $pricelists[$cont] = json_encode($classCategories);
            $cont++;
        }

        $cartportfolios = explode(',', $this->app['session']->get('portfolios'));
	foreach ($portfolios as $unqid) {
	    if($unqid == '') { continue; }
	}
        $this->app['twig']->addGlobal('prname', $products);
        $this->app['twig']->addGlobal('count', count($portfolios));
        $this->app['twig']->addGlobal('portfolios', $portfolios);
        $this->app['twig']->addGlobal('cartportfolios', $cartportfolios);
        $this->app['twig']->addGlobal('price', $price);
        $this->app['twig']->addGlobal('pricelists', $pricelists);
    }


    public function cartdown($app)
    {
        $portfolio = $this->app['eccube.plugin.repository.portfolio_data']->find($this->app['session']->get('actvpf'));
        $portfolio->setQuantity($portfolio['quantity'] - 1);
        if (($portfolio['quantity']) <= 0) {
            $portfolio->setQuantity(0);
        }
        $status = $this->app['eccube.plugin.repository.portfolio_data']->update($portfolio);
    }


    public function cartup($app)
    {
        $portfolio = $this->app['eccube.plugin.repository.portfolio_data']->find($this->app['session']->get('actvpf'));
        $portfolio->setQuantity($portfolio['quantity'] + 1);
        $status = $this->app['eccube.plugin.repository.portfolio_data']->update($portfolio);
    }


    public function cartremove($app)
    {

        $this->app['session']->remove('actvpf');
        $ids = explode(',', $this->app['session']->get('portfolios'));
        $this->app['session']->remove('portfolios');
        $pcid = $this->app['request']->attributes->get('productClassId');
        $sesbuf = null;
        foreach ($ids as $id){
            $portfolio = $this->app['eccube.plugin.repository.portfolio_data']->find($id);
            if ($pcid == $portfolio['product_class_id']){
                continue;
            } else {
               $sesbuf .= "," .$id;
            }
        }
        if ($sesbuf == ',') {
            $sesbuf = null;
        }
/*
        $portfolios = array();
        $sesbuf = null;
	foreach ($ids as $id) {
	    if($id == '') { continue; }
	    $portfolio = $this->app['eccube.plugin.repository.portfolio_data']->find($id);
            if ($pcid == $portfolio['product_class_id']) {
                $portfolio->setQuantity(1);
	        $status = $this->app['eccube.plugin.repository.portfolio_data']->update($portfolio);
                $id = null;
            }
            $sesbuf .= $id;
	}
*/
        $this->app['session']->set('portfolios', $sesbuf);
    }


    public function productdetail($app)
    {
        $id = $this->app['request']->get('id');
        $Product =$this->app['eccube.repository.product']->find($id);
        $classname1 = $Product['className1'];
        if ($Product['className2']) {
            $classname2 = $Product['className2'];
        } else {
            $classname2 = null;
        }
        $this->app['twig']->addGlobal('classname1', $classname1);
        $this->app['twig']->addGlobal('classname2', $classname2);
    }


    public function adminOrder($app)
    {
        $orderid = $this->app['request']->get('id');
        $portfolios = $this->app['eccube.plugin.repository.portfolio_data']->findOrder($orderid);
        $this->app['twig']->addGlobal('portfolios', $portfolios);
    }

}

