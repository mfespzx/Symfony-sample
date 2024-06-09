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

class PortfolioFrontController
{

    public function index(Application $app, Request $request, $page_no = null)
    {
        $portfolios = $app['eccube.plugin.repository.portfolio_data']->findAll();
/*
        $portfolios = array();
        $cont = 0;
        foreach ($portfolios as $portfolio) {
            $portfolios[$cont]['img'] = $portfolio['img'];
            $cont++;
        }
*/
        return $app->render('/gallery.twig', array(
            'portfolios' => $portfolios,
        ));
    }


    public function readimage(Application $app, Request $request, $id)
    {
        $pimages = $app['eccube.plugin.repository.portfolio_imagedata']->findAllSortbypid($id);
        $portfolio = $app['eccube.plugin.repository.portfolio_data']->find($id);

        return $app->render('Portfolio/View/readimage.twig', array(
            'name' => $portfolio['name'],
            'pid' => $id,
            'pimages' => $pimages,
        ));
    }


    public function delete(Application $app, Request $request)
    {
        $id = $request->get('thisid');

        if (!is_null($id)) {
            $TargetWork = $app['eccube.plugin.repository.portfolio_data']->find($id);
            if (!$TargetWork) {
                throw new NotFoundHttpException();
            }

            $status = $app['eccube.plugin.repository.portfolio_data']->delete($TargetWork);
            if ($status === true) {
                $message = '作品情報を削除しました。';
            } else {
                $message = '削除失敗。';
            }
        } else {
            $message = '削除失敗。';
        }
        return $message;
    }

    public function upload(Application $app, Request $request)
    {
        $customer = $app->user();

        $portfolios = $app['eccube.plugin.repository.portfolio_data']->findByCustomer($customer['id']);
//dump($portfolios);
        return $app->render('Mypage/upload.twig', array(
            'portfolios' => $portfolios,
        ));
    }


    /** サムネイル追加処理 */
    public function addImage(Application $app, Request $request)
    {
        // 既存ファイル削除
        if (is_dir('upload/portfolio_thumb/' .$request->get('id'). '')) {
            $files = array('upload/portfolio_thumb/' .$request->get('id'). '/*.png', 'upload/portfolio_thumb/' .$request->get('id'). '/*.jpg', 'upload/portfolio_thumb/' .$request->get('id'). '/*.JPG', 'upload/portfolio_thumb/' .$request->get('id'). '/*.gif');
            foreach ($files as $file) {
                foreach (glob($file) as $val) {
                    unlink($val);
                }
            }
        }
        $image = $request->files->get('portfolio_image');
        $mimeType = $image->getMimeType();
        if (0 !== strpos($mimeType, 'image')) {
            throw new UnsupportedMediaTypeHttpException();
        }
        $extension = $image->getClientOriginalExtension();
        $filename = date('mdHis') . uniqid('_') . '.' . $extension;

        $image->move('upload/portfolio_thumb/' .$request->get('id'). '/', $filename);

	$portfolio = $app['eccube.plugin.repository.portfolio_data']->find($request->get('id'));
        $portfolio->setId($request->get('id'));
        $portfolio->setImg('upload/portfolio_thumb/' .$request->get('id'). '/' .$filename);
        $status = $app['eccube.plugin.repository.portfolio_data']->update($portfolio);

        return $app->json(array('files' => 'upload/portfolio_thumb/' .$request->get('id'). '/' .$filename), 200);
    }


    /** 画像削除処理 */
    public function delImage(Application $app, Request $request)
    {
	$portfolio = $app['eccube.plugin.repository.portfolio_data']->find($request->get('id'));
        $portfolio->setId($portfolio['id']);
        $portfolio->setImg('');
        $status = $app['eccube.plugin.repository.portfolio_data']->update($portfolio);
 
        // 既存ファイル削除
        if (is_dir('upload/portfolio_thumb/' .$request->get('id'). '')) {
            $files = array('upload/portfolio_thumb/' .$request->get('id'). '/*.png', 'upload/portfolio_thumb/' .$request->get('id'). '/*.jpg', 'upload/portfolio_thumb/' .$request->get('id'). '/*.JPG', 'upload/portfolio_thumb/' .$request->get('id'). '/*.gif');
            foreach ($files as $file) {
                foreach (glob($file) as $val) {
                    unlink($val);
                }
            }
        }
        return $app->json(array('files' => null), 200);
    }


    public function publish(Application $app, Request $request)
    {
        $orderid = $request->get('order_id');
        $product = $request->get('pr_id');
        $portfolios = $app['eccube.plugin.repository.portfolio_data']->findOrder($orderid);
        foreach ($portfolios as $data) {
            if ($data['product_id'] == $product) {
                $portfolio = $data;
            }
        }

        // 保有ポイントの取得.
        $Customer = $app->user();
        $currentPoint = $app['eccube.plugin.point.repository.pointcustomer']->getLastPointById($Customer->getId());

        $status = $app['eccube.plugin.point.repository.pointcustomer']->savePoint($currentPoint + 100, $Customer);

        // データが存在しない場合は一覧へリダイレクト
        if (is_null($portfolio)) {
            $app->addError('該当データなし', 'admin');
            return $app->redirect($app->url('mypage'));
        }

        // 更新処理
        $portfolio->setPublish(0);
        $portfolio->setFirstFlg(1);
        $status = $app['eccube.plugin.repository.portfolio_data']->update($portfolio);
        if (!$status) {
            $app->addError('admin.portfolio.edit.save.failure', 'admin');
            return $app->redirect($app->url('mypage'));
        }

        return $app->redirect($app->url('mypage'));
     }


     public function review(Application $app, Request $request, $id)
     {
         $session = $request->getSession();

         // sessionのデータ保持
         $session->set('review_flg', $val = 1);
         return $app->redirect($app->url('products_detail_review', array('id' => $id)));
     }


     public function getportfolio(Application $app, Request $request, $id)
     {
         $session = $request->getSession();
         $session->remove('actvpf');

         // sessionのデータ保持
         $session->set('actvpf', $id);
         return 0;
     }


}
