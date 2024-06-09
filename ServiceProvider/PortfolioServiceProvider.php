<?php

namespace Plugin\Portfolio\ServiceProvider;

use Eccube\Application;
use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;

class PortfolioServiceProvider implements ServiceProviderInterface
{
    public function register(BaseApplication $app)
    {
//        $cd = 'portfolio';

	$app->match('/' .$app["config"]["admin_route"]. '/portfolio', '\\Plugin\\Portfolio\\Controller\PortfolioController::index')
            ->bind("admin_portfolio");

	$app->match('/' .$app["config"]["admin_route"]. '/portfolio/edit/{id}', '\\Plugin\\Portfolio\\Controller\PortfolioController::edit')
            ->value('id', null)->assert('id', '\d+|')
	    ->bind("admin_portfolio_edit");

	$app->match('/' .$app["config"]["admin_route"]. '/portfolio/regist', '\\Plugin\\Portfolio\\Controller\PortfolioController::regist2')
	    ->bind("admin_portfolio_regist");

	$app->match('/' .$app["config"]["admin_route"]. '/portfolio/commit', '\\Plugin\\Portfolio\\Controller\PortfolioController::commit')
	    ->bind("admin_portfolio_commit");

	$app->match('/' .$app["config"]["admin_route"]. '/portfolio/delete/{id}', '\\Plugin\\Portfolio\\Controller\PortfolioController::delete')
	    ->bind("admin_portfolio_delete");

	$app->match('/' .$app["config"]["admin_route"]. '/portfolio/rank', '\\Plugin\\Portfolio\\Controller\PortfolioController::rank')
	    ->bind("admin_portfolio_rank");

        $app->post('/' .$app["config"]["admin_route"]. '/portfolio/edit/addimg', '\\Plugin\\Portfolio\\Controller\PortfolioImageController::addImage')
            ->bind('admin_portfolio_addimg');

        $app->post('/' .$app["config"]["admin_route"]. '/portfolio/edit/addpimg', '\\Plugin\\Portfolio\\Controller\PortfolioImageController::addPimage')
            ->bind('admin_portfolio_addpimg');

        $app->post('/' .$app["config"]["admin_route"]. '/portfolio/edit/delpimg', '\\Plugin\\Portfolio\\Controller\PortfolioImageController::delImage')
            ->bind('admin_portfolio_delpimg');

        $app->match('/' .$app["config"]["admin_route"]. '/portfolio/pdf/{id}', '\\Plugin\\Portfolio\\Controller\PortfolioController::pdf')
            ->value('id', null)->assert('id', '\d+|')
            ->bind('admin_portfolio_pdf');

        $app->match('/' .$app["config"]["admin_route"]. '/portfolio/pdf2/{id}', '\\Plugin\\Portfolio\\Controller\PortfolioController::pdf2')
            ->value('id', null)->assert('id', '\d+|')
            ->bind('admin_portfolio_pdf2');

	$app->match('/portfolio/regist', '\\Plugin\\Portfolio\\Controller\PortfolioController::regist')
	    ->bind("portfolio_regist");

	$app->match('/canvas/post/{id}', '\\Plugin\\Portfolio\\Controller\PortfolioController::canvas')
	    ->bind("canvas_post")->assert('id', '\d+');

	$app->match('/canvas/redirect', '\\Plugin\\Portfolio\\Controller\PortfolioController::redirect')
	    ->bind("canvas_redirect");

	$app->match('/portfolio/publish', '\\Plugin\\Portfolio\\Controller\PortfolioFrontController::publish')
	    ->bind("portfolio_publish");

	$app->match('/gallery', '\\Plugin\\Portfolio\\Controller\PortfolioFrontController::index')
	    ->bind("gallery");

	$app->match('/readimage/{id}', '\\Plugin\\Portfolio\\Controller\PortfolioFrontController::readimage')
	    ->bind("readimage")->assert('id', '\d+');

	$app->match('/mypage/delete', '\\Plugin\\Portfolio\\Controller\PortfolioFrontController::delete')
	    ->bind("portfolio_delete");

	$app->match('/mypage/upload', '\\Plugin\\Portfolio\\Controller\PortfolioFrontController::upload')
	    ->bind("mypage_upload");

        $app->post('/mypage/upload/addimg', '\\Plugin\\Portfolio\\Controller\PortfolioFrontController::addImage')
            ->bind('portfolio_addimg');

        $app->post('/mypage/upload/delimg', '\\Plugin\\Portfolio\\Controller\PortfolioFrontController::delImage')
            ->bind('portfolio_delimg');

        $app->match('/mypage/review/{id}', '\\Plugin\\Portfolio\\Controller\PortfolioFrontController::review')
            ->bind('portfolio_reivew')->assert('id', '\d+');

        $app->match('/portfolio/getportfolio/{id}', '\\Plugin\\Portfolio\\Controller\PortfolioFrontController::getportfolio')
            ->bind('portfolio_down')->assert('id', '\d+');

        // -- Repositoy --
        $app['eccube.plugin.repository.portfolio_data'] = function () use ($app) {
            return $app['orm.em']->getRepository('\Plugin\Portfolio\Entity\PortfolioData');
        };

        $app['eccube.plugin.repository.portfolio_imagedata'] = function () use ($app) {
            return $app['orm.em']->getRepository('\Plugin\Portfolio\Entity\PortfolioImageData');
        };

        // FormType
        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new \Plugin\Portfolio\Form\Type\PortfolioEditType($app);
            return $types;
        }));

        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new \Plugin\Portfolio\Form\Type\SearchPortfolioType($app);
            return $types;
        }));

        // メニュー登録
/*
        $app['config'] = $app->share($app->extend('config', function ($config) {
            $addNavi = array(
                'id' => 'Portfolio',
                'name' => "スタンプ管理",
                'has_child' => true,
                'icon' => 'cb-comment',
                'child' => array(
                    array(
                        'id' => "Portfolio",
                        'name' => "スタンプ設定",
                        'url' => "admin_Portfolio",
                    ),
                ),
            );
            $nav = $config['nav'];
            foreach ($nav as $key => $val) {
                if ("setting" == $val['id']) {
                    array_splice($nav, $key, 0, array($addNavi));
                    break;
                }
            }
            $config['nav'] = $nav;
            return $config;
        }));
*/
    }

    public function boot(BaseApplication $app)
    {
    }
}