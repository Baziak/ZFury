<?php
/**
 * Created by PhpStorm.
 * User: alexander
 * Date: 12/3/14
 * Time: 11:09 AM
 */

namespace Test\Controller;

use Media\Service\Video;
use Media\Service\File;
use Zend\View\Model\JsonModel;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Media\Form\VideoUpload;
use Media\Form\Filter\VideoUploadInputFilter;
use Media\Interfce\VideoUploaderInterface;

class VideoController extends AbstractActionController implements VideoUploaderInterface
{
    public function uploadVideoAction()
    {
        $fileService = new File($this->getServiceLocator());
        return new ViewModel(['fileService' => $fileService, 'module'=> 'video', 'type' => \Media\Entity\File::VIDEO_FILETYPE]);
    }

    /**
     * Advanced avatar uploader Blueimp UI
     */
    public function startVideoUploadAction()
    {
        $user = $this->identity()->getUser();
        $fileService = $this->getServiceLocator()->get('Media\Service\File');
        $blueimpService = $this->getServiceLocator()->get('Media\Service\Blueimp');
        if ($this->getRequest()->isPost()) {
            $form = new VideoUpload('upload-video');
            $inputFilter = new VideoUploadInputFilter();
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
                $video = $fileService->createFile($data, $this->identity()->getUser());
                $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->getConnection()->commit();
                $dataForJson = $blueimpService->displayUploadedFile($video, $this->getDeleteVideoUrl($video));
            } else {
                $messages = $form->getMessages();
                $messages = array_shift($messages);
                $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->getConnection()->rollBack();
                $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->close();
                $dataForJson = [ 'files' => [
                        [
                            'name' => $form->get('video')->getValue()['name'],
                            'error' => array_shift($messages)
                        ]
                ]];
            }
        } else {
            $dataForJson = $blueimpService->displayUploadedFiles(
                $user->getVideos(),
                $this->getDeleteVideoUrls($user->getVideos())
            );
        }

        return new JsonModel($dataForJson);
    }

    public function deleteVideoAction()
    {
        $this->getServiceLocator()->get('Media\Service\File')
            ->deleteFile($this->getEvent()->getRouteMatch()->getParam('id'));
        return $this->getServiceLocator()->get('Media\Service\Blueimp')
            ->deleteFileJson($this->getEvent()->getRouteMatch()->getParam('id'));
    }

    public function getDeleteVideoUrl($video)
    {
        $url = $this->serviceLocator->get('ViewHelperManager')->get('url');
        $fileService = $this->getServiceLocator()->get('Media\Service\File');
        return $fileService->getFullUrl($url('test/default', [
            'controller' => 'video',
            'action' => 'delete-video',
            'id' => $video->getId()
        ]));
    }

    public function getDeleteVideoUrls($videos)
    {
        $deleteUrls = [];
        foreach ($videos as $video) {
            array_push($deleteUrls, [
                'id' => $video->getId(),
                'deleteUrl' => $this->getDeleteVideoUrl($video)
            ]);
        }

        return $deleteUrls;
    }
}