<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
* http://www.lockon.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\PlgAddProductColumns\Form\Type\Admin;

use Plugin\PlgAddProductColumns\Form\EventListener\PlgAddProductColumnsValueSubscriber;
use \Symfony\Component\Form\AbstractType;
use \Symfony\Component\Form\Extension\Core\Type;
use \Symfony\Component\Form\FormBuilderInterface;
use \Symfony\Component\Validator\Constraints as Assert;
use \Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Silex\Application as BaseApplication;

/**
 * http://docs.symfony.gr.jp/symfony2/book/forms.html#book-forms-type-reference
 *
 * Class PlgAddProductColumnsType
 * @package Plugin\PlgAddProductColumns\Form\Type\Admin
 */
class PlgAddProductColumnsValueType extends AbstractType
{
    private $app;

    public function __construct(BaseApplication $app)
    {
        $this->app = $app;
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $subscriber = new PlgAddProductColumnsValueSubscriber($builder->getFormFactory(), $this->app);
        $builder->addEventSubscriber($subscriber);

    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Plugin\PlgAddProductColumns\Entity\PlgAddProductColumnsValue'
        ));

    }

    public function getName()
    {
        return 'admin_plg_add_product_columns_value';
    }

}
