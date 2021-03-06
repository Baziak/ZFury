<?php
/**
 * Created by PhpStorm.
 * User: alexander
 * Date: 1/14/15
 * Time: 12:52 PM
 */
namespace InstallTest\Controller;

use Install\Service\Install;
use Zend\Http\Request;
use Zend\Session\Container;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

class IndexControllerTest extends AbstractHttpControllerTestCase
{

    private $sessionProgress;

    /**
     *  Migration up
     */
    public static function setUpBeforeClass()
    {
        copy('config/application.config.php', 'config/application.config.php.back');
        $reading = file_get_contents('config/application.config.php');
        $replaced = preg_replace('#//[\s]*\'Install\'#', "'Install',\n", $reading);
        file_put_contents('config/application.config.php', $replaced);

        exec('vendor/bin/doctrine-module orm:schema-tool:update --force');
    }

    /**
     * Migration down
     */
    public static function tearDownAfterClass()
    {
        exec('vendor/bin/doctrine-module orm:schema-tool:drop --force');
        copy('config/application.config.php.back', 'config/application.config.php');
    }

    public function tearDown()
    {
        $this->sessionProgress->getManager()->getStorage()->clear('progress_tracker');
        $forms = new Container('forms');
        $forms->getManager()->getStorage()->clear('forms');
        parent::tearDown();
    }

    /**
     * Set up
     */
    public function setUp()
    {
        $this->setApplicationConfig(
            include 'config/application.config.php'
        );
        $this->setTraceError(true);
        $this->sessionProgress = new Container('progress_tracker');
        parent::setUp();
    }

    /**
     * Tests that global requirements action can be accessed
     */
    public function testGlobalRequirementsAction()
    {
        $this->dispatch('/install/index/global-requirements');
        $this->assertEquals('install', $this->getRouteMatch()->getParam('module'));
        $this->assertEquals('Install\Controller\Index', $this->getRouteMatch()->getParam('controller'));
        $this->assertActionName('global-requirements');
        $this->assertResponseStatusCode(200);
        //assert that action is globalRequirements

    }

    /**
     * Tests redirection to a current step
     */
    public function testRedirectionToCurrentStep()
    {
        $this->dispatch('/install/index/database');
        $this->assertEquals('install', $this->getRouteMatch()->getParam('module'));
        $this->assertEquals('Install\Controller\Index', $this->getRouteMatch()->getParam('controller'));
        $this->assertRedirectTo('/install/index/global-requirements');
        $this->assertResponseStatusCode(302);
    }

    /**
     * Tests redirection after submit of global requirements action
     */
    public function testSubmitGlobalRequirementsAction()
    {
        $this->dispatch('/install/index/global-requirements', Request::METHOD_POST);
        $this->assertRedirectTo('/install/index/database');
        $this->assertResponseStatusCode(302);
    }

    /**
     * Tests that database action can be accessed
     */
    public function testDatabaseAction()
    {
        $this->sessionProgress->offsetSet('global-requirements', Install::DONE);
        $this->dispatch('/install/index/database');
        $this->assertEquals('install', $this->getRouteMatch()->getParam('module'));
        $this->assertEquals('Install\Controller\Index', $this->getRouteMatch()->getParam('controller'));
        $this->assertActionName('database');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Tests that mail action can be accessed
     */
    public function testMailAction()
    {
        $this->sessionProgress->offsetSet('database', Install::DONE);
        $this->dispatch('/install/index/mail');
        $this->assertEquals('install', $this->getRouteMatch()->getParam('module'));
        $this->assertEquals('Install\Controller\Index', $this->getRouteMatch()->getParam('controller'));

        $this->assertActionName('mail');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Tests that modules action can be accessed
     */
    public function testModulesAction()
    {
        $this->sessionProgress->offsetSet('mail', Install::DONE);
        $this->dispatch('/install/index/modules');
        $this->assertEquals('install', $this->getRouteMatch()->getParam('module'));
        $this->assertEquals('Install\Controller\Index', $this->getRouteMatch()->getParam('controller'));
        $this->assertActionName('modules');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Tests redirection after submit of modules action
     */
    public function testSubmitModulesAction()
    {
        $this->sessionProgress->offsetSet('mail', Install::DONE);
        $this->dispatch('/install/index/modules', Request::METHOD_POST, $this->getModulesParams());
        $this->assertRedirectTo('/install/index/modules-requirements');
        $this->assertActionName('modules');
        $this->assertResponseStatusCode(302);
    }

    /**
     * Tests that modules requirements action can be accessed
     */
    public function testModulesRequirementsAction()
    {
        $this->sessionProgress->offsetSet('modules', Install::DONE);
        $this->dispatch('/install/index/modules-requirements');
        $this->assertEquals('install', $this->getRouteMatch()->getParam('module'));
        $this->assertEquals('Install\Controller\Index', $this->getRouteMatch()->getParam('controller'));
        $this->assertActionName('modules-requirements');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Tests redirection after submit of modules requirements action
     */
    public function testSubmitModulesRequirementsAction()
    {
        $this->sessionProgress->offsetSet('modules', Install::DONE);
        $this->dispatch('/install/index/modules-requirements', Request::METHOD_POST);
        $this->assertRedirectTo('/install/index/finish');
        $this->assertActionName('modules-requirements');
        $this->assertResponseStatusCode(302);
    }

    /**
     * Tests that finish action can be accessed
     */
    public function testFinishAction()
    {
        $this->sessionProgress->offsetSet('modules-requirements', Install::DONE);
        $this->dispatch('/install/index/finish');
        $this->assertEquals('install', $this->getRouteMatch()->getParam('module'));
        $this->assertEquals('Install\Controller\Index', $this->getRouteMatch()->getParam('controller'));
        $this->assertActionName('finish');
        $this->assertResponseStatusCode(200);
    }

    /**
     * @return \Zend\Mvc\Router\Http\RouteMatch
     */
    public function getRouteMatch()
    {
        return $this->getApplicationServiceLocator()->get('Application')->getMvcEvent()->getRouteMatch();
    }

    /**
     * @return array
     */
    public function getDbParams()
    {
        return [
          'host' => 'alpha-team.php.nixsolutions.com',
          'port' => '3306',
          'user' => 'p_zfs_test',
          'password' => 'p_zfs_test',
          'dbname' => 'p_zfs_tests'
        ];
    }

    /**
     * @return array
     */
    public function getMailParams()
    {
        return [
            'host' => '10.10.0.114',
            'port' => '2525',
            'header' => [
                [
                    'header-name' => 'TESTHEADER',
                    'header-value' => 'value1'
                ],
                [
                    'header-name' => 'TESTHEADER2',
                    'header-value' => 'value2'
                ]
            ],
            'emails' => [
                ['emails' => 'test3@test3.com'],
                ['emails' => 'test4@test4.com'],
                ['emails' => 'test5@test5.com']
            ],
            'from' => [
                ['from' => 'test@test.com'],
                ['from' => 'test2@test2.com']
            ]
        ];
    }

    /**
     * @return array
     */
    public function getModulesParams()
    {
        return [
            'Categories' => 'good',
            'Comment' => 'good',
            'Mail' => 'good',
            'Options' => 'good',
            'Pages' => 'good',
        ];
    }
}
