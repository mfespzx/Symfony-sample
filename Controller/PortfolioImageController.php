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

class PortfolioImageController
{

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


    /** サムネイル追加処理 */
    public function addImage(Application $app, Request $request)
    {
//rmdir('admin/portfolio_thumb/1');
        // 既存ファイル削除
        if (is_dir('upload/portfolio_thumb/' .$request->get('id'). '')) {
            $files = array('upload/portfolio_thumb/' .$request->get('id'). '/*.png', 'upload/portfolio_thumb/' .$request->get('id'). '/*.jpg', 'upload/portfolio_thumb/' .$request->get('id'). '/*.gif');
            foreach ($files as $file) {
                foreach (glob($file) as $val) {
                    unlink($val);
                }
            }
        }
        $image = $request->files->get('admin_portfolio_image');
        $mimeType = $image->getMimeType();
        if (0 !== strpos($mimeType, 'image')) {
            throw new UnsupportedMediaTypeHttpException();
        }
        $extension = $image->getClientOriginalExtension();
        $filename = date('mdHis') . uniqid('_') . '.' . $extension;

        $image->move('upload/portfolio_thumb/' .$request->get('id'). '/', $filename);
        return $app->json(array('files' => 'upload/portfolio_thumb/' .$request->get('id'). '/' .$filename), 200);
    }


    /** 画像追加処理 */
    public function addPimage(Application $app, Request $request)
    {
//unlink('upload/portfolio_image/4/1110065819_58239bfb7df9a.jpg');exit;
/*
        if (is_dir('upload/portfolio_image/' .$request->get('id'). '')) {
            $files = array('upload/portfolio_image/' .$request->get('id'). '/*.png', 'upload/portfolio_image/' .$request->get('id'). '/*.jpeg', 'upload/portfolio_image/' .$request->get('id'). '/*.png', 'upload/portfolio_image/' .$request->get('id'). '/*.jpg', 'upload/portfolio_image/' .$request->get('id'). '/*.gif');
            foreach ($files as $file) {
                foreach (glob($file) as $val) {
                    unlink($val);
                }
            }
        }
exit;
*/
        $currentId = $app['eccube.plugin.repository.portfolio_imagedata']->findCurrentId();
        $currentId = $currentId[0]['image_id'] + 1;

        $image = $request->files->get('admin_portfolio_image2');
        $mimeType = $image->getMimeType();
        if (0 !== strpos($mimeType, 'image')) {
            throw new UnsupportedMediaTypeHttpException();
        }
        $extension = $image->getClientOriginalExtension();
        $filename = date('mdHis') . uniqid('_') . '.' . $extension;
        $image->move('upload/portfolio_image/' .$request->get('id'). '/', $filename);

        $portfolioImage = new \Plugin\Portfolio\Entity\PortfolioImageData;
        $portfolioImage->setImageid((int)$currentId);
        $portfolioImage->setPortfolioid($request->get('id'));
        $portfolioImage->setFilename($filename);
        $portfolioImage->setRank(1);
        $status = $app['eccube.plugin.repository.portfolio_imagedata']->create($portfolioImage);

        return $app->json(array('files' => $filename), 200);
    }


    /** 画像削除処理 */
    public function delImage(Application $app, Request $request)
    {
        $portfolioImage = $app['eccube.plugin.repository.portfolio_imagedata']->findByName($request->get('filename'));
        $status = $app['eccube.plugin.repository.portfolio_imagedata']->delete($portfolioImage[0]);
        unlink('upload/portfolio_image/' .$request->get('id'). '/' .$request->get('filename'). '');
        return $app->json(array('files' => 'upload/portfolio_image/' .$request->get('id'). '/' .$request->get('filename')), 200);
    }



}