<?php

namespace Plugin\Portfolio\Controller;

use Eccube\Application;
use Eccube\Common\Constant;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use Doctrine\ORM\EntityRepository;

class PortfolioController
{

    public function index(Application $app, Request $request, $page_no = null)
    {
//if($_SERVER['REMOTE_ADDR'] != '218.231.171.229') { echo "デバッグ中"; exit; }
        $session = $request->getSession();

        // form作成
        $ProductCategories  = $app['eccube.repository.category']->findAll();
	$options = array('1' => $ProductCategories[0], '2' => $ProductCategories[1], '3' => $ProductCategories[2]);
        $searchForm = $app['form.factory']
            ->createBuilder('admin_search_portfolio',$options)
            ->getForm();
        $searchForm->handleRequest($request);
        $searchData = array();
        if ($searchForm->isValid()) {
            $searchData = $searchForm->getData();
        }
        $pagination = array();
        $page_no = $request->query->get('page_no');

        // 検索時の処理
        if ('POST' === $request->getMethod()) {
            $qb = $app['eccube.plugin.repository.portfolio_data']
                ->getQueryBuilderBySearchData($searchData);

            $pagination = $app['paginator']()->paginate(
                $qb,
                empty($searchData['pageno']) ? 1 : $searchData['pageno'],
                empty($searchData['pagemax']) ? 20 : $searchData['pagemax']->getId()
            );

            // sessionのデータ保持
            $session->set('eccube.plugin.repository.portfolio_data', $searchData);
        } else {
            if (is_null($page_no)) {

                // sessionを削除
                $session->remove('eccube.plugin.repository.portfolio_data');
            } else {
                $searchData = $session->get('eccube.plugin.repository.portfolio_data');

                // 表示件数
                $pcount = $request->get('page_count');
                $page_count = empty($pcount) ? 20 : $pcount;
                $qb = $app['eccube.plugin.repository.portfolio_data']
                    ->getQueryBuilderBySearchData($searchData);
                $pagination = $app['paginator']()->paginate(
                    $qb,
                    $page_no,
                    $page_count
                );
            }
        }
        return $app->render('Portfolio/View/admin/index.twig', array(
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'category' => $options,
            //'contents' => $repository,
            //'cont' => count($repository)
        ));
    }


    /** Rank */
    public function rank(Application $app, Request $request)
    {
        $id = $request->get('thisid');
        $target = $request->get('targetid');

        $thisPortfolio = $app['eccube.plugin.repository.portfolio_data']->find($id);
        $targetPortfolio = $app['eccube.plugin.repository.portfolio_data']->find($target);
        $thisRank = $thisPortfolio['rank'];
        $targetRank = $targetPortfolio['rank'];

        $thisPortfolio->setId($id);
        $thisPortfolio->setRank($targetRank);
        $targetPortfolio->setId($target);
        $targetPortfolio->setRank($thisRank);

        $status = $app['eccube.plugin.repository.portfolio_data']->update($thisPortfolio);
        $status = $app['eccube.plugin.repository.portfolio_data']->update($targetPortfolio);

        if (!$status) {
            $app->addError('失敗しました', 'admin');
            return "失敗しました。";
        }
        return $id;
    }


    /** 編集画面表示 */
    public function edit(Application $app, Request $request, $id)
    {
//if($_SERVER['REMOTE_ADDR'] != '218.231.171.229') { echo "デバッグ中"; exit; }
        // 例外処理
        if(is_null($id) || strlen($id) == 0) {
            return $app->redirect($app->url('admin_portfolio'));
        }

        // 選択した作品を検索
        $portfolio = $app['eccube.plugin.repository.portfolio_data']->find($id);
        $portfolioImage = $app['eccube.plugin.repository.portfolio_imagedata']->findByPortfolioid($id);
        $customer = $app['eccube.repository.customer']->find($portfolio['customer_id']);

        // データが存在しない場合は一覧へリダイレクト
        if(is_null($portfolio)) {
            return $app->redirect($app->url('admin_portfolio'));
        }
        $ProductCategories  = $app['eccube.repository.category']->findAll();
        $portfolio->choice = array('1' => $ProductCategories[0], '2' => $ProductCategories[1], '3' => $ProductCategories[2]);

        // formの作成
        $form = $app['form.factory']
            ->createBuilder('admin_portfolio_edit', $portfolio)
            ->getForm();
	$typeform = array();
        $cont = 0;
        foreach ($portfolio->choice as $key => $value) {
            if ($portfolio['type'] == $value) {
                break;
            }
            $cont++;
        }
        return $app->render('Portfolio/View/admin/edit.twig', array(
            'id' => $id,
            'type' => $portfolio['type'],
            'typenum' => $cont,
            'img' => $portfolio['img'],
            'create_date' => $portfolio['create_date'],
            'update_date' => $portfolio['update_date'],
            'form' => $form->createView(),
            'images' => $portfolioImage,
            'customer' => $customer,
        ));
    }


    /** 編集確定処理 */
    public function commit(Application $app, Request $request)
    {
        $currentId = $app['eccube.plugin.repository.portfolio_data']->findCurrentId();
        $currentId = $currentId[0]['id'] + 1;

        // Formを取得
        $builder = $app['form.factory']->createBuilder('admin_portfolio_edit');
        $form = $builder->getForm();
        $form->handleRequest($request);
        $data = $form->getData();
        if ('POST' === $request->getMethod()) {

            // 登録処理
            if(is_null($data['id'])) {
                $portfolio = new \Plugin\Portfolio\Entity\PortfolioData;
                $portfolio->setId((int)$currentId);
                $portfolio->setName($data['name']);
                $portfolio->setType($data['type']);
                $portfolio->setImg($data['img']);
                $portfolio->setPublish($data['publish']);
                $portfolio->setComment($data['comment']);
                $status = $app['eccube.plugin.repository.portfolio_data']->create($portfolio);
                if (!$status) {
                    $app->addError('登録に失敗しました', 'admin');
	            return $app->redirect($app->url('admin_portfolio'));
                }

            // 更新処理
            } else {
                $id = $data['id'];

                // id の存在確認　nullであれば一覧に戻る
                if(!$id) {
                    $app->addError('admin.portfolio.edit.data.illegalaccess', 'admin');
	            return $app->redirect($app->url('admin_portfolio'));
                }

	        $portfolio = $app['eccube.plugin.repository.portfolio_data']->find($id);

                // データが存在しない場合は一覧へリダイレクト
                if(is_null($portfolio)) {
                    $app->addError('該当データが見つかりません', 'admin');
                    return $app->redirect($app->url('admin_portfolio'));
                }

                // 更新処理
                $portfolio->setId((int)$data['id']);
                $portfolio->setName($data['name']);
                $portfolio->setType($data['type']);
                $portfolio->setImg($data['img']);
                $portfolio->setPublish($data['publish']);
                $portfolio->setComment($data['comment']);

                $status = $app['eccube.plugin.repository.portfolio_data']->update($portfolio);
                if (!$status) {
                    $app->addError('更新に失敗しました', 'admin');
                    return $app->redirect($app->url('admin_portfolio'));
                }
            }

            // 成功時のメッセージを登録する
            $app->addSuccess('更新完了', 'admin');
        }
        return $app->redirect($app->url('admin_portfolio'));
    }


    /** CANVASへのPOST時(作るボタン)の処理 */
    public function canvas(Application $app, Request $request, $id)
    {
//if(!$_SERVER['REMOTE_ADDR'] == '218.231.171.229') { echo "デバッグ中現在使用不可触らないでください"; }
        $session = $request->getSession();
        if ($request->get('quantity')) {
            $session->remove('quantity');
            $session->set('quantity', $request->get('quantity'));
        }
        if ($request->get('material')) {
            $session->remove('material');
            $session->set('material', $request->get('material'));
        }

        if(empty($request->get('unqid'))) {
            $unqid = $app['eccube.plugin.repository.portfolio_data']->findCurrentId();
            $unqid = ($unqid[0]['id'] + 1);
            $type = 0;
        } else {
            $unqid = $request->get('unqid');
            $type = 1;
        }
       $customer = $app->user();
        if (empty($customer['id'])){
            $customer = null;
        }
        if ($session->get('portfolio_unqid') != null){
            $login = 1;
        } else {
            $login = $request->get('login');
        }
        if ($request->get('pid') != null) {
            $pid = str_replace(array("\r", "\r\n", "\n"), "", $request->get('pid'));
        } else {
            $pid = $session->get('portfolio_pid');
        }

        // 規格取得
        $Product = $app['eccube.repository.product']->find($id);
        $ProductClasses = $Product->getProductClasses();
        $classCategories = array();
        foreach ($ProductClasses as $class) {
            preg_match("/^([0-9]{1,3})/", $class['ClassCategory1'], $match);
            if ($request->get('material')) {
                if ($class['ClassCategory2']['id'] == $request->get('material')) {
                    $classCategories['pricelist['.$match[0].']'] = $class['price02'];
                }
            } else {
                $classCategories['pricelist['.$match[0].']'] = $class['price02'];
            }
            if(preg_match("/" .$request->get('pgno'). "/", $class['ClassCategory1']['name'])) {
                if ($request->get('material')) {
                    if ($class['ClassCategory2']['id'] == $request->get('material')) {
                        $product_class_id = $class['id'];
                    }
                } else {
                    $product_class_id = $class['id'];
                }
            }
        }
	$pricelist = json_encode($classCategories);

        // sessionを削除
        $session->remove('portfolio_unqid');
        $session->remove('portfolio_id');
        $session->remove('portfolio_pid');
        $session->remove('portfolio_redi');

        // 未ログイン時sessionのデータ格納
        if ($type == 0){ 
            $session->set('portfolio_unqid', $unqid);
            $session->set('portfolio_id', $id);
            $session->set('portfolio_pid', $pid);
            $session->set('portfolio_redi', '1');
        }

        $rank = $app['eccube.plugin.repository.portfolio_data']->findCurrentRank();
        $rank = ($rank[0]['rank'] + 1);

        // 一時登録処理
        if ($type == 0){
            $portfolio = new \Plugin\Portfolio\Entity\PortfolioData;
            $portfolio->setId((int)$unqid);
            $portfolio->setOrderid((int)$request->get('order_id'));
            $portfolio->setCustomerid((int)$customer['id']);
            $portfolio->setName(null);
            $portfolio->setProductid((int)$id);
            $portfolio->setProductClassid((int)$product_class_id);
            $portfolio->setQuantity((int)$request->get('quantity'));
            $portfolio->setPageno((int)$request->get('pgno'));
            $portfolio->setPublish(1);
            $portfolio->setRank($rank);
            $portfolio->setDelflg(0);
            $status = $app['eccube.plugin.repository.portfolio_data']->create($portfolio);

        // 更新処理
        } elseif(isset($customer['id'])) {
            $portfolio = $app['eccube.plugin.repository.portfolio_data']->find($unqid);
            $portfolio->setId((int)$unqid);
            $portfolio->setCustomerid((int)$customer['id']);
            $portfolio->setName(null);
            $portfolio->setProductid((int)$id);
            $portfolio->setProductClassid((int)$product_class_id);
            $portfolio->setQuantity((int)$request->get('quantity'));
            $portfolio->setPageno((int)$request->get('pgno'));
            $portfolio->setPublish(1);
            $portfolio->setRank($rank);
            $portfolio->setDelflg(0);
            $status = $app['eccube.plugin.repository.portfolio_data']->update($portfolio);
        }

       return $app->render('Portfolio/View/index.twig', array(
            'customer' => $customer['id'],
            'unqid' => $unqid,
            'type' => $type,
            'login' => $login,
            'pr_id' => $id,
            'pid' => $pid,
            'pgno' => $request->get('pgno'),
            'pricelist' => $pricelist,
        ));
    }


    /** CANVASからの保存時の処理 */
    public function regist(Application $app, Request $request)
    {
//if($_SERVER['REMOTE_ADDR'] != '218.231.171.229') { echo "デバッグ中"; exit; }
        $session = $request->getSession();
        $session->set('portfolio_mode', $request->get('mode'));
        $session->set('portfolio_name', $request->get('title'));

        $portfolio = $app['eccube.plugin.repository.portfolio_data']->find($request->get('unqid'));
//暫定
if (($portfolio['product_id'] == 4 || $portfolio['product_id'] == 3) && $request->get('pgno') == null){
    $pgno = 24;
} else {
    $request->get('pgno') == null ? $pgno = 0 : $pgno = $request->get('pgno');
}

        $session->set('portfolio_pgno', $pgno);
        // 未ログインはリダイレクト
        $customer = $app->user();
        if (empty($customer['id'])){
            return $app->redirect($app->url('mypage_login'));
        }

        // 発注
        if ($request->get('mode') == 1) {

            // 規格取得
            $Product = $app['eccube.repository.product']->find($portfolio['product_id']);
            $ProductClasses = $Product->getProductClasses();
            $classCategories = array();
            $product_class_id = null;

            foreach ($ProductClasses as $class) {
                $class['ClassCategory1']['id'] != null ? $classcateName = $app['eccube.repository.class_category']->find($class['ClassCategory1']['id']) : $classcateName = '';
                if(preg_match("/" .$pgno. "/", $class['ClassCategory1']['name'])) {
                    if ($session->get('material')) {
                        if ($class['ClassCategory2']['id'] == $session->get('material')) {
                           $product_class_id = $class['id'];
                        }
                    } else {
                       $product_class_id = $class['id'];
                    }
                }
                if ($request->get('pgno') == 1) {
                    $product_class_id = $class['id'];
                }
            }

            if (!$product_class_id) {
                $product_class_id = 'fail';
	        $app->addError('データの保存に失敗しました。やり直してください。', 'admin');
                return $app->redirect($app->url('mypage'));
            } else {
	        $portfolio->setId((int)$request->get('unqid'));
	        $portfolio->setName($request->get('title'));
	        $portfolio->setPageno($pgno);
	        $portfolio->setProductClassid($product_class_id);
	        $portfolio->setPublish(1);
	        $portfolio->setDelflg(0);
	        $status = $app['eccube.plugin.repository.portfolio_data']->update($portfolio);

	        // 保存失敗時MYPAGEへリダイレクト
	        if (!$status) {
	            $app->addError('データの保存に失敗しました。やり直してください。', 'admin');
	            return $app->redirect($app->url('mypage'));
                }
            }

            // sessionを削除
            $session->remove('portfolio_redi');
            $session->remove('portfolio_mode');
            $session->remove('portfolio_name');
            $session->remove('portfolio_pgno');

            // この注文での全作品集ID格納
            $session->set('portfolios', $session->get('portfolios'). "," .$request->get('unqid'));

            return $app->render('Portfolio/View/cart.twig', array(
                 'quantity' => $portfolio['quantity'],
                 'pr_id' => $portfolio['product_id'],
                 'product_class_id' => $product_class_id,
             ));

        // 保存
        } else {
            return $app->redirect($app->url('mypage'));
        }
    }


    public function redirect(Application $app, Request $request)
    {
        $session = $request->getSession();
        $customer = $app->user();
        // 発注モード
        if($session->get('portfolio_mode') == 1){
            $portfolio = $app['eccube.plugin.repository.portfolio_data']->find($session->get('portfolio_unqid'));
            $portfolio->setId($session->get('portfolio_unqid'));
            $portfolio->setCustomerid($customer['id']);
            $portfolio->setName($session->get('portfolio_name'));
            $portfolio->setPageno($session->get('portfolio_pgno'));
            $status = $app['eccube.plugin.repository.portfolio_data']->update($portfolio);

            // 規格取得
            $Product = $app['eccube.repository.product']->find($session->get('portfolio_id'));
            $ProductClasses = $Product->getProductClasses();
            $classCategories = array();
            $product_class_id = null;
            foreach ($ProductClasses as $class) {
                $class['ClassCategory1']['id'] != null ? $classcateName = $app['eccube.repository.class_category']->find($class['ClassCategory1']['id']) : $classcateName = '';
                if (preg_match("/" .$session->get('portfolio_pgno'). "/", $classcateName)) {
                    $product_class_id = $class['id'];
                }
            }
            if (!$classcateName) {
                $product_class_id = $class['id'];
            }
            if (!$product_class_id) {
                $product_class_id = 'fail';
            }

            // この注文での全作品集ID格納
            $session->set('portfolios', $session->get('portfolios'). "," .$session->get('portfolio_unqid'));

            // リダイレクト用sessionを削除
            $session->remove('portfolio_unqid');
            $session->remove('portfolio_id');
            $session->remove('portfolio_pid');
            $session->remove('portfolio_redi');
            $session->remove('portfolio_mode');
            $session->remove('portfolio_name');
            $session->remove('portfolio_pgno');

            return $app->render('Portfolio/View/cart.twig', array(
                'pr_id' => $session->get('portfolio_id'),
                'product_class_id' => $product_class_id,
            ));
        }

        // 保存モード(Canvasで再編集)
        return $app->render('Portfolio/View/redirect.twig', array(
            'pr_id' => $session->get('portfolio_id'),
            'unqid' => $session->get('portfolio_unqid'),
            'customer_id' => $customer['id'],
            'mode' => $session->get('portfolio_mode'),
        ));
    }


    /** 削除画面する */
    public function delete(Application $app, Request $request, $id)
    {
        if (!is_null($id)) {
            $TargetWork = $app['eccube.plugin.repository.portfolio_data']->find($id);

            if (!$TargetWork) {
                throw new NotFoundHttpException();
            }

            $status = $app['eccube.plugin.repository.portfolio_data']->delete($TargetWork);
            if ($status === true) {
                $app->addError('作品情報を削除しました。', 'admin');
            } else {
                $app->addError('削除失敗。', 'admin');
            }
        } else {
            $app->addError('削除失敗。', 'admin');
        }
        return $app->redirect($app->url('admin_portfolio'));
    }


    /** 登録画面を表示する */
    public function regist2(Application $app, Request $request)
    {
        $PageLayout = new \Plugin\Portfolio\Entity\PortfolioData();
	$PageLayout->choice = array('0' => 'グルメ自慢', '1' => '旅行記', '2' => '家族の想い出');
        // formの作成
        $form = $app['form.factory']
            ->createBuilder('admin_portfolio_edit', $PageLayout)
            ->getForm();
	$typeform = array();
        $cont = 0;
        foreach ($PageLayout->choice as $key => $value) {
            if ($PageLayout['type'] == $value) {
                break;
            }
            $cont++;
        }
        return $app->render('Portfolio/View/admin/edit.twig', array(
            'id' => null,
            'type' => 0,
            'typenum' => $cont,
            'img' => null,
            'form' => $form->createView()
        ));
    }


    /** PDFをダウンロードする */
    public function pdf(Application $app, Request $request, $id)
    {
        $file = 'pbs/webroot/pdf/' .$id. '(1).pdf';
        // PDFを出力する
        header("Content-Disposition: attachment; filename=" .$id. "(1).pdf");
        readfile($file);
    }


    /** PDF2をダウンロードする */
    public function pdf2(Application $app, Request $request, $id)
    {
        $file = 'pbs/webroot/pdf/' .$id. '(3).pdf';
        // PDFを出力する
        header("Content-Disposition: attachment; filename=" .$id. "(3).pdf");
        readfile($file);
    }


}