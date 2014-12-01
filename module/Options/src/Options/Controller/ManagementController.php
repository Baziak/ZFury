<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Options\Controller;

use Options\Form\Edit;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Options\Form\Create;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;

/**
 * Class ManagementController
 * @package Options\Controller
 */
class ManagementController extends AbstractActionController
{
    /**
     * @return array|ViewModel
     */
    public function indexAction()
    {
        $objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $options = $objectManager->getRepository('Options\Entity\Options')->findAll();

        return new ViewModel(
            array(
                'options' => $options
            )
        );
    }

    /**
     * @return array|ViewModel
     */
    public function viewAction()
    {
        $namespace = $this->params()->fromRoute('namespace');
        $key = $this->params()->fromRoute('key');
        //        $namespace = $this->params()->fromQuery('namespace');
        //        $key = $this->params()->fromQuery('key');

        if (!$namespace || !$key) {
            return $this->notFoundAction();
        }

        $objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $option = $objectManager
            ->getRepository('Options\Entity\Options')
            ->find(array('namespace' => $namespace, 'key' => $key));

        return new ViewModel(
            array('option' => $option)
        );
    }

    /**
     * @return \Zend\Http\Response|ViewModel
     * @throws \Exception
     */
    public function createAction()
    {
        $form = new Create('create', ['serviceLocator' => $this->getServiceLocator()]);
        $form->get('namespace')->setValue(\Options\Entity\Options::NAMESPACE_DEFAULT);

        if ($this->getRequest()->isPost()) {
            $objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $data = $form->getData();
                /** @var \Options\Entity\Options $option */
                $option = $this->getServiceLocator()->get('Options\Entity\Options');
                $objectManager->getConnection()->beginTransaction();
                try {
                    $hydrator = new DoctrineHydrator($objectManager);

                    $hydrator->hydrate($form->getData(), $option);

                    $option->setCreated(new \DateTime(date('Y-m-d H:i:s')));
                    $option->setUpdated(new \DateTime(date('Y-m-d H:i:s')));

                    $form->bind($option);

                    $objectManager->persist($option);
                    $objectManager->flush();

                    $objectManager->getConnection()->commit();

                    $this->flashMessenger()->addSuccessMessage('Option was successfully created');

                    return $this->redirect()->toRoute('options');

                } catch (\Exception $e) {
                    $objectManager->getConnection()->rollback();
                    throw $e;
                }

            }
        }

        return new ViewModel(
            array(
                'form' => $form
            )
        );
    }

    /**
     * @return \Zend\Http\Response|ViewModel
     * @throws \Exception
     */
    public function editAction()
    {
        $namespace = $this->params()->fromRoute('namespace');
        $key = $this->params()->fromRoute('key');

        if (!$namespace || !$key) {
            return $this->notFoundAction();
        }

        $objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $option = $objectManager
            ->getRepository('Options\Entity\Options')
            ->find(array('namespace' => $namespace, 'key' => $key));

        if (!$option) {
            return $this->notFoundAction();
        }

        $form = new Create('edit', ['serviceLocator' => $this->getServiceLocator()]);
        $form->bind($option);
        $form->get('submit')->setValue('Save');

        if ($this->getRequest()->isPost()) {
            $objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $objectManager->getConnection()->beginTransaction();
                try {
                    $option->setUpdated(new \DateTime(date('Y-m-d H:i:s')));

                    $objectManager->persist($form->getData());
                    $objectManager->flush();

                    $objectManager->getConnection()->commit();

                    $this->flashMessenger()->addSuccessMessage('Option was successfully updated');

                    return $this->redirect()->toRoute('options');

                } catch (\Exception $e) {
                    $objectManager->getConnection()->rollback();
                    throw $e;
                }

            }
        }

        return new ViewModel(
            array(
                'form' => $form
            )
        );
    }

    /**
     * @return \Zend\Http\Response
     */
    public function deleteAction()
    {
        $namespace = $this->params()->fromRoute('namespace');
        $key = $this->params()->fromRoute('key');

        if (!$namespace || !$key) {
            return $this->redirect()->toRoute('options');
        }

        $objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $option = $objectManager
            ->getRepository('Options\Entity\Options')
            ->find(array('namespace' => $namespace, 'key' => $key));

        if (!$option) {
            return $this->redirect()->toRoute('options');
        }

        $objectManager->remove($option);
        $objectManager->flush($option);

        $this->flashMessenger()->addSuccessMessage('Option was successfully deleted');

        return $this->redirect()->toRoute('options');
    }
}
