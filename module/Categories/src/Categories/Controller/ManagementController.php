<?php

namespace Categories\Controller;

use Starter\Mvc\Controller\AbstractCrudController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;
use Zend\Form\Annotation\AnnotationBuilder;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Categories\Form\Filter;
use DoctrineModule\Validator;
use Categories\Validators;
use Media\Form\ImageUpload;
use Media\Service\File;
use Categories\Entity\Categories;
use Media\Form\Filter\ImageUploadInputFilter;

class ManagementController extends AbstractCrudController implements \Media\Interfce\ImageUploaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function onDispatch(MvcEvent $e)
    {
        parent::onDispatch($e);
        $e->getApplication()
            ->getServiceManager()
            ->get('viewhelpermanager')
            ->get('headLink')
            ->appendStylesheet('module/categories/css/management.css');
    }

    /**
     * @return array|ViewModel
     */
    public function indexAction()
    {
        $entityManager = $this
            ->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager');
        $repository = $entityManager->getRepository('Categories\Entity\Categories');

        $currentRootCategory = null;
        $categories = null;
        if ($id = $this->params('id')) {
            $currentRootCategory = $entityManager->getRepository('Categories\Entity\Categories')->findOneBy(['parentId' => null, 'id' => $id]);
        }
        $rootCategories = $entityManager->getRepository('Categories\Entity\Categories')->findBy(['parentId' => null], ['id' => 'ASC']);

        if (!$currentRootCategory && !empty($rootCategories)) {
            $currentRootCategory = $rootCategories[0];
        }
        if ($currentRootCategory) {
            $categories = $repository->findBy(['parentId' => $currentRootCategory->getId()], ['order' => 'ASC']);
        }

        return new ViewModel(['categories' => $categories, 'rootTree' => $rootCategories, 'currentRoot' => $currentRootCategory]);
    }

    /**
     * @return ViewModel
     */
    public function createAction()
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $repository = $entityManager->getRepository('Categories\Entity\Categories');
        /** @var \Categories\Service\Categories $categoriesService */
        $categoriesService = $this->getServiceLocator()->get('Categories\Service\Categories');

        $form = $this->getCreateForm();

        if ($this->getRequest()->isPost()) {
            $form->setInputFilter(new Filter\CreateInputFilter($this->getServiceLocator()));
            $form->setData($this->getRequest()->getPost());

            if ($form->isValid()) {
                $parentId = !$this->params()->fromRoute('parentId')
                    ? null
                    : $this->params()->fromRoute('parentId');
                $aliasValid = new Validator\NoObjectExists(['object_repository' => $repository, 'fields' => ['alias', 'parentId']]);
                if ($aliasValid->isValid(
                    ['alias' => $form->get('alias')->getValue(),
                        'parentId' => $parentId]
                )
                ) {
                    $category = $this->getEntity();
                    $category->setParentId(!$parentId ? null : $repository->find($parentId));
                    $category->setOrder($this->getMaxOrder($parentId));

                    $hydrator = new DoctrineHydrator($entityManager);
                    $hydrator->hydrate($form->getData(), $category);
                    $entityManager->persist($category);
                    $entityManager->flush();

                    //Add image from session
                    if ($categoriesService->ifImagesExist()) {
                        $imageService = $this->getServiceLocator()->get('Media\Service\File');
                        foreach ($categoriesService->getSession()->ids as $imageId) {
                            $imageService->writeObjectFileEntity(
                                $this->getServiceLocator()
                                    ->get('Doctrine\ORM\EntityManager')
                                    ->getRepository('Media\Entity\File')->find($imageId),
                                $category
                            );
                        }
                    }

                    $this->flashMessenger()->addSuccessMessage('Category has been successfully added!');

                    return $this->redirect()->toRoute('categories/default', array('controller' => 'management', 'action' => 'index'));
                }
                $form->get('alias')->setMessages(
                    array(
                        'errorMessageKey' => 'Alias must be unique in it\'s category!'
                    )
                );
            }
        } else {
            $categoriesService->clearImages();
        }

        $imageUploadForm = new ImageUpload('upload-image');
        $imageService = new File($this->getServiceLocator());

        return $this->prepareViewModel(
            $form,
            null,
            null,
            [
                'imageUploadForm' => $imageUploadForm,
                'imageService' => $imageService,
                'module' => 'image-categories',
                'type' => \Media\Entity\File::IMAGE_FILETYPE,
                'id' => null
            ]
        );
    }

    /**
     * @return ViewModel
     */
    public function editAction()
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $repository = $entityManager->getRepository('Categories\Entity\Categories');

        $form = $this->getEditForm();

        if ($this->getRequest()->isPost()) {
            $form->setInputFilter(new Filter\CreateInputFilter($this->getServiceLocator()));
            $form->setData($this->getRequest()->getPost());
            $aliasValid = new Validators\NoObjectExists($entityManager->getRepository('Categories\Entity\Categories'));

            if ($form->isValid()) {
                $entity = $this->loadEntity();
                if ($aliasValid->isValid(
                    ['alias' => $form->get('alias')->getValue(), 'parentId' => $entity->getParentId()],
                    $this->params()->fromRoute('id')
                )
                ) {
                    $category = $form->getData();
                    $category->setParentId(!$entity->getParentId() ? null : $repository->find($entity->getParentId()));
                    $category->setOrder($entity->getOrder());
                    $entityManager->persist($form->getData());
                    $entityManager->flush();
                    $this->getServiceLocator()->get('Categories\Service\Categories')->updateChildrenPath($form->getData());
                    $this->flashMessenger()->addSuccessMessage('Category has been successfully edited!');

                    return $this->redirect()->toRoute('categories/default', array('controller' => 'management', 'action' => 'index'));
                }
                $form->get('alias')->setMessages(
                    array(
                        'errorMessageKey' => 'Alias must be unique in its category!'
                    )
                );
            }
        }

        $imageUploadForm = new ImageUpload('upload-image');
        $imageService = new File($this->getServiceLocator());

        return $this->prepareViewModel(
            $form,
            null,
            null,
            [
                'imageUploadForm' => $imageUploadForm,
                'imageService' => $imageService,
                'module' => 'image-categories',
                'type' => \Media\Entity\File::IMAGE_FILETYPE,
                'id' => $this->params()->fromRoute('id')
            ]
        );
    }

    /**
     * @return JsonModel
     */
    public function orderAction()
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $repository = $entityManager->getRepository('Categories\Entity\Categories');

        $entityManager->getConnection()->beginTransaction();

        if ($this->getRequest()->isPost()) {
            $tree = $this->getRequest()->getPost('tree');
            $treeParent = $this->getRequest()->getPost('treeParent');
            try {
                $categories = json_decode($tree);
                if (!$categories) {
                    throw new \Exception('Categories tree is broken');
                }
                foreach ($categories as $node) {
                    if (isset($node->item_id)) {
                        $dbNode = $repository->findOneBy(['id' => $node->item_id]);

                        if (!$node->parent_id) {
                            $node->parent_id = $treeParent;
                        }

                        if (!$dbNode->getParentId()) {
                            throw new \Exception();
                        }
                        $parentId = $dbNode->getParentId()->getId();
                        if ($parentId != $node->parent_id && $node->parent_id) {
                            $dbNode->setParentId($repository->findOneBy(['id' => $node->parent_id]));
                        }

                        if ($dbNode->getOrder() != $node->order && $node->order) {
                            $dbNode->setOrder($node->order);
                        }
                        $entityManager->persist($dbNode);
                        $entityManager->flush();

                        $aliasValid = new Validators\NoObjectExists($repository);
                        if (!$aliasValid->isValid(
                            ['alias' => $dbNode->getAlias(), 'parentId' => $dbNode->getParentId()],
                            $node->item_id
                        )
                        ) {
                            throw new \Exception('Order has been failed!');
                        }

                    }
                }
                $entityManager->getConnection()->commit();
                $returnJson = new JsonModel(['success' => true]);
            } catch (\Exception $e) {
                $entityManager->getConnection()->rollback();
                $returnJson = new JsonModel(['success' => false]);
            }
            return $returnJson;
        }
        return $this->redirect()->toRoute('categories/default', array('controller' => 'management', 'action' => 'index'));
    }

    /**
     * {@inheritDoc}
     */
    protected function getCreateForm()
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $builder = new AnnotationBuilder($entityManager);

        return $builder->createForm($this->getEntity());
    }

    /**
     * {@inheritDoc}
     */
    protected function getEditForm()
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $builder = new AnnotationBuilder($entityManager);
        $category = $this->loadEntity();

        if ($category->getParentId()) {
            $parentId = $category->getParentId()->getId();
            $category->setParentId($parentId);
        }

        $form = $builder->createForm($this->getEntity());
        $form->setHydrator(new DoctrineHydrator($entityManager));
        $form->bind($category);

        return $form;
    }

    /**
     * {@inheritDoc}
     */
    protected function getEntity()
    {
        return new Categories();
    }

    /**
     * Returns maximum order field value within category siblings.
     *
     * @param  $parentId
     * @return int|mixed
     */
    private function getMaxOrder($parentId)
    {
        $repository = $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager')
            ->getRepository('Categories\Entity\Categories');
        $orders = array();
        if (!$siblings = $repository->findBy(['parentId' => $parentId])) {
            $siblings = $repository->findBy(['parentId' => null]);
        }
        foreach ($siblings as $sibling) {
            $orders[] = $sibling->getOrder();
        }

        if (count($orders) > 0) {
            $order = max($orders) + 1;
        } else {
            $order = 1;
        }

        return $order;
    }

    /**
     * Advanced avatar uploader Blueimp UI
     */
    public function startImageUploadAction()
    {
        $repository = $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager')
            ->getRepository('Categories\Entity\Categories');
        /** @var \Categories\Service\Categories $categoriesService */
        $categoriesService = $this->getServiceLocator()->get('Categories\Service\Categories');

        $id = $this->params()->fromRoute('id');
        if ($id) {
            $category = $repository->find($id);
        }

        $imageService = $this->getServiceLocator()->get('Media\Service\File');
        $blueimpService = $this->getServiceLocator()->get('Media\Service\Blueimp');
        if ($this->getRequest()->isPost()) {
            $form = new ImageUpload('upload-image');
            $inputFilter = new ImageUploadInputFilter();
            $form->setInputFilter($inputFilter->getInputFilter());

            $request = $this->getRequest();
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );
            $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->getConnection()->beginTransaction();
            $form->setData($post);

            if ($form->isValid()) {
                $data = $form->getData();
                if (!$id) {
                    $image = $imageService->writeFileEntity($data);
                    $categoriesService->addImageToSession($image);
                } else {
                    $image = $imageService->createFile($data, $category);
                }
                $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->getConnection()->commit();
                $dataForJson = $blueimpService->displayUploadedFile($image, $this->getDeleteImageUrl($image));
            } else {
                $messages = $form->getMessages();
                $messages = array_shift($messages);
                $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->getConnection()->rollBack();
                $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->close();

                $dataForJson = ['files' => [
                    [
                        'name' => $form->get('image')->getValue()['name'],
                        'error' => array_shift($messages)
                    ]
                ]];
            }
        } else {
            $images = [];
            if ($id) {
                $images = $category->getImages();
            } else {
                if ($categoriesService->ifImagesExist()) {
                    foreach ($categoriesService->getSession()->ids as $imageId) {
                        array_push(
                            $images,
                            $this->getServiceLocator()
                                ->get('Doctrine\ORM\EntityManager')
                                ->getRepository('Media\Entity\File')->find($imageId)
                        );
                    }
                }
            }
            $dataForJson = $blueimpService->displayUploadedFiles(
                $images,
                $this->getDeleteImageUrls($images)
            );
        }

        return new JsonModel($dataForJson);
    }

    public function deleteImageAction()
    {
        /** @var \Categories\Service\Categories $categoriesService */
        $categoriesService = $this->getServiceLocator()->get('Categories\Service\Categories');

        $idImageSes = array_search(
            $this->getEvent()->getRouteMatch()->getParam('id'),
            $categoriesService->getSession()->ids
        );
        if (!is_null($idImageSes)) {
            $this->getServiceLocator()->get('Media\Service\File')
                ->deleteFile($this->getEvent()->getRouteMatch()->getParam('id'));
        } else {
            $this->getServiceLocator()->get('Media\Service\File')
                ->deleteFileEntity($this->getEvent()->getRouteMatch()->getParam('id'));
        }

        return $this->getServiceLocator()->get('Media\Service\Blueimp')
            ->deleteFileJson($this->getEvent()->getRouteMatch()->getParam('id'));
    }

    public function getDeleteImageUrl($image)
    {
        $url = $this->serviceLocator->get('ViewHelperManager')->get('url');
        $imageService = $this->getServiceLocator()->get('Media\Service\File');
        return $imageService->getFullUrl($url('categories/default', [
            'controller' => 'management',
            'action' => 'delete-image',
            'id' => $image->getId()
        ]));
    }

    public function getDeleteImageUrls($images)
    {
        $deleteUrls = [];
        foreach ($images as $image) {
            array_push($deleteUrls, [
                'id' => $image->getId(),
                'deleteUrl' => $this->getDeleteImageUrl($image)
            ]);
        }

        return $deleteUrls;
    }
}
