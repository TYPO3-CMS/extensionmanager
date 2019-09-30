<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Extensionmanager\Tests\Unit\Utility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Prophecy\Argument;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\NullFrontend;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extensionmanager\Utility\DependencyUtility;
use TYPO3\CMS\Extensionmanager\Utility\InstallUtility;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class InstallUtilityTest extends UnitTestCase
{
    /**
     * @var string
     */
    protected $extensionKey;

    /**
     * @var array
     */
    protected $extensionData = [];

    /**
     * @var array List of created fake extensions to be deleted in tearDown() again
     */
    protected $fakedExtensions = [];

    protected $backupEnvironment = true;

    protected $resetSingletonInstances = true;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|InstallUtility|\TYPO3\TestingFramework\Core\AccessibleObjectInterface
     */
    protected $installMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extensionKey = 'dummy';
        $this->extensionData = [
            'key' => $this->extensionKey,
            'siteRelPath' => '',
        ];
        $this->installMock = $this->getAccessibleMock(
            InstallUtility::class,
            [
                'isLoaded',
                'loadExtension',
                'unloadExtension',
                'updateDatabase',
                'importStaticSqlFile',
                'importT3DFile',
                'reloadCaches',
                'processCachingFrameworkUpdates',
                'saveDefaultConfiguration',
                'getExtensionArray',
                'enrichExtensionWithDetails',
                'importInitialFiles',
                'emitAfterExtensionInstallSignal',
            ],
            [],
            '',
            false
        );
        $dependencyUtility = $this->getMockBuilder(DependencyUtility::class)->getMock();
        $this->installMock->_set('dependencyUtility', $dependencyUtility);
        $this->installMock->expects($this->any())
            ->method('getExtensionArray')
            ->with($this->extensionKey)
            ->will($this->returnCallback([$this, 'getExtensionData']));
        $this->installMock->expects($this->any())
            ->method('enrichExtensionWithDetails')
            ->with($this->extensionKey)
            ->will($this->returnCallback([$this, 'getExtensionData']));

        $cacheManagerProphecy = $this->prophesize(CacheManager::class);
        $cacheManagerProphecy->getCache('core')->willReturn(new NullFrontend('core'));
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerProphecy->reveal());
    }

    protected function tearDown(): void
    {
        foreach ($this->fakedExtensions as $fakeExtkey => $fakeExtension) {
            $this->testFilesToDelete[] = Environment::getVarPath() . '/tests/' . $fakeExtkey;
        }
        parent::tearDown();
    }

    /**
     * @return array
     */
    public function getExtensionData(): array
    {
        return $this->extensionData;
    }

    /**
     * Creates a fake extension inside typo3temp/. No configuration is created,
     * just the folder
     *
     * @return string The extension key
     */
    protected function createFakeExtension(): string
    {
        $extKey = strtolower($this->getUniqueId('testing'));
        $absExtPath = Environment::getVarPath() . '/tests/' . $extKey;
        $relativeVarPath = ltrim(str_replace(Environment::getProjectPath(), '', Environment::getVarPath()), '/');
        $relPath = $relativeVarPath . '/tests/' . $extKey . '/';
        GeneralUtility::mkdir($absExtPath);
        $this->fakedExtensions[$extKey] = [
            'siteRelPath' => $relPath
        ];
        return $extKey;
    }

    /**
     * @test
     */
    public function installCallsUpdateDatabase()
    {
        $this->installMock->expects($this->once())
            ->method('updateDatabase')
            ->with([$this->extensionKey]);

        $cacheManagerMock = $this->getMockBuilder(CacheManager::class)->getMock();
        $cacheManagerMock->expects($this->once())->method('flushCachesInGroup');
        $this->installMock->_set('cacheManager', $cacheManagerMock);
        $this->installMock->install($this->extensionKey);
    }

    /**
     * @test
     */
    public function installCallsLoadExtension()
    {
        $cacheManagerMock = $this->getMockBuilder(CacheManager::class)->getMock();
        $cacheManagerMock->expects($this->once())->method('flushCachesInGroup');
        $this->installMock->_set('cacheManager', $cacheManagerMock);
        $this->installMock->expects($this->once())->method('loadExtension');
        $this->installMock->install($this->extensionKey);
    }

    /**
     * @test
     */
    public function installCallsFlushCachesIfClearCacheOnLoadIsSet()
    {
        $this->extensionData['clearcacheonload'] = true;
        $cacheManagerMock = $this->getMockBuilder(CacheManager::class)->getMock();
        $cacheManagerMock->expects($this->once())->method('flushCaches');
        $this->installMock->_set('cacheManager', $cacheManagerMock);
        $this->installMock->install($this->extensionKey);
    }

    /**
     * @test
     */
    public function installCallsFlushCachesIfClearCacheOnLoadCamelCasedIsSet()
    {
        $this->extensionData['clearCacheOnLoad'] = true;
        $cacheManagerMock = $this->getMockBuilder(CacheManager::class)->getMock();
        $cacheManagerMock->expects($this->once())->method('flushCaches');
        $this->installMock->_set('cacheManager', $cacheManagerMock);
        $this->installMock->install($this->extensionKey);
    }

    /**
     * @test
     */
    public function installCallsReloadCaches()
    {
        $cacheManagerMock = $this->getMockBuilder(CacheManager::class)->getMock();
        $cacheManagerMock->expects($this->once())->method('flushCachesInGroup');
        $this->installMock->_set('cacheManager', $cacheManagerMock);
        $this->installMock->expects($this->once())->method('reloadCaches');
        $this->installMock->install($this->extensionKey);
    }

    /**
     * @test
     */
    public function installCallsSaveDefaultConfigurationWithExtensionKey()
    {
        $cacheManagerMock = $this->getMockBuilder(CacheManager::class)->getMock();
        $cacheManagerMock->expects($this->once())->method('flushCachesInGroup');
        $this->installMock->_set('cacheManager', $cacheManagerMock);
        $this->installMock->expects($this->once())->method('saveDefaultConfiguration')->with($this->extensionKey);
        $this->installMock->install($this->extensionKey);
    }

    /**
     * @test
     */
    public function uninstallCallsUnloadExtension()
    {
        $this->installMock->expects($this->once())->method('unloadExtension');
        $this->installMock->uninstall($this->extensionKey);
    }

    /**
     * @return array
     */
    public function importT3DFileDoesNotImportFileIfAlreadyImportedDataProvider(): array
    {
        return [
            'Import T3D file when T3D was imported before extension to XML' => [
                'data.t3d',
                'dataImported',
                'data.t3d',
            ],
            'Import T3D file when a file was imported after extension to XML' => [
                'data.t3d',
                'data.t3d',
                'dataImported'
            ],
            'Import XML file when T3D was imported before extension to XML' => [
                'data.xml',
                'dataImported',
                'data.t3d'
            ],
            'Import XML file when a file was imported after extension to XML' => [
                'data.xml',
                'data.t3d',
                'dataImported'
            ]
        ];
    }

    /**
     * @param string $fileName
     * @param string $registryNameReturnsFalse
     * @param string $registryNameReturnsTrue
     * @test
     * @dataProvider importT3DFileDoesNotImportFileIfAlreadyImportedDataProvider
     */
    public function importT3DFileDoesNotImportFileIfAlreadyImported($fileName, $registryNameReturnsFalse, $registryNameReturnsTrue)
    {
        $extKey = $this->createFakeExtension();
        $absPath = Environment::getProjectPath() . '/' . $this->fakedExtensions[$extKey]['siteRelPath'];
        GeneralUtility::mkdir($absPath . 'Initialisation');
        file_put_contents($absPath . 'Initialisation/' . $fileName, 'DUMMY');
        $registryMock = $this->getMockBuilder(Registry::class)
            ->setMethods(['get', 'set'])
            ->getMock();
        $registryMock
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap(
                [
                    ['extensionDataImport', $this->fakedExtensions[$extKey]['siteRelPath'] . 'Initialisation/' . $registryNameReturnsFalse, null, false],
                    ['extensionDataImport', $this->fakedExtensions[$extKey]['siteRelPath'] . 'Initialisation/' . $registryNameReturnsTrue, null, true],
                ]
            ));
        $installMock = $this->getAccessibleMock(
            InstallUtility::class,
            ['getRegistry', 'getImportExportUtility'],
            [],
            '',
            false
        );
        $dependencyUtility = $this->getMockBuilder(DependencyUtility::class)->getMock();
        $installMock->_set('dependencyUtility', $dependencyUtility);
        $installMock->_set('registry', $registryMock);
        $installMock->expects($this->never())->method('getImportExportUtility');
        $installMock->_call('importT3DFile', $this->fakedExtensions[$extKey]['siteRelPath']);
    }

    /**
     * @test
     */
    public function siteConfigGetsMovedIntoPlace()
    {
        // prepare an extension with a shipped site config
        $extKey = $this->createFakeExtension();
        $absPath = Environment::getProjectPath() . '/' . $this->fakedExtensions[$extKey]['siteRelPath'];
        $config = Yaml::dump(['dummy' => true]);
        $siteIdentifier = 'site_identifier';
        GeneralUtility::mkdir_deep($absPath . 'Initialisation/Site/' . $siteIdentifier);
        file_put_contents($absPath . 'Initialisation/Site/' . $siteIdentifier . '/config.yaml', $config);

        $subject = new InstallUtility();
        $listUtility = $this->prophesize(ListUtility::class);
        $subject->injectListUtility($listUtility->reveal());
        $logManagerProphecy = $this->prophesize(LogManager::class);
        $logManagerProphecy->getLogger(InstallUtility::class)->willReturn(new NullLogger());
        $objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $objectManagerProphecy->get(LogManager::class)->willReturn($logManagerProphecy->reveal());
        $subject->injectObjectManager($objectManagerProphecy->reveal());

        $availableExtensions = [
            $extKey => [
                'siteRelPath' => $this->fakedExtensions[$extKey]['siteRelPath'],
            ],
        ];
        $listUtility->enrichExtensionsWithEmConfInformation($availableExtensions)->willReturn($availableExtensions);
        $listUtility->getAvailableExtensions()->willReturn($availableExtensions);
        $registry = $this->prophesize(Registry::class);
        $registry->get('extensionDataImport', Argument::any())->willReturn('some folder name');
        $registry->get('siteConfigImport', Argument::any())->willReturn(null);
        $registry->set('siteConfigImport', Argument::cetera())->shouldBeCalled();
        $subject->injectRegistry($registry->reveal());

        // provide function result inside test output folder
        $environment = new Environment();
        $configDir = $absPath . 'Result/config';
        if (!file_exists($configDir)) {
            GeneralUtility::mkdir_deep($configDir);
        }
        $environment::initialize(
            Environment::getContext(),
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            $configDir,
            Environment::getCurrentScript(),
            'UNIX'
        );
        $subject->processExtensionSetup($extKey);

        $registry->set('siteConfigImport', $siteIdentifier, 1)->shouldHaveBeenCalled();
        $siteConfigFile = $configDir . '/sites/' . $siteIdentifier . '/config.yaml';
        self::assertFileExists($siteConfigFile);
        self::assertStringEqualsFile($siteConfigFile, $config);
    }

    /**
     * @test
     */
    public function siteConfigGetsNotOverriddenIfExistsAlready()
    {
        // prepare an extension with a shipped site config
        $extKey = $this->createFakeExtension();
        $absPath = Environment::getProjectPath() . '/' . $this->fakedExtensions[$extKey]['siteRelPath'];
        $siteIdentifier = 'site_identifier';
        GeneralUtility::mkdir_deep($absPath . 'Initialisation/Site/' . $siteIdentifier);
        file_put_contents($absPath . 'Initialisation/Site/' . $siteIdentifier . '/config.yaml', Yaml::dump(['dummy' => true]));

        // fake an already existing site config in test output folder
        $configDir = $absPath . 'Result/config';
        if (!file_exists($configDir)) {
            GeneralUtility::mkdir_deep($configDir);
        }
        $config = Yaml::dump(['foo' => 'bar']);
        $existingSiteConfig = 'sites/' . $siteIdentifier . '/config.yaml';
        GeneralUtility::mkdir_deep($configDir . '/sites/' . $siteIdentifier);
        file_put_contents($configDir . '/' . $existingSiteConfig, $config);

        $subject = new InstallUtility();
        $listUtility = $this->prophesize(ListUtility::class);
        $subject->injectListUtility($listUtility->reveal());
        $logManagerProphecy = $this->prophesize(LogManager::class);
        $logManagerProphecy->getLogger(InstallUtility::class)->willReturn(new NullLogger());
        $objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $objectManagerProphecy->get(LogManager::class)->willReturn($logManagerProphecy->reveal());
        $subject->injectObjectManager($objectManagerProphecy->reveal());

        $availableExtensions = [
            $extKey => [
                'siteRelPath' => $this->fakedExtensions[$extKey]['siteRelPath'],
            ],
        ];
        $listUtility->enrichExtensionsWithEmConfInformation($availableExtensions)->willReturn($availableExtensions);
        $listUtility->getAvailableExtensions()->willReturn($availableExtensions);
        $registry = $this->prophesize(Registry::class);
        $registry->get('extensionDataImport', Argument::any())->willReturn('some folder name');
        $registry->get('siteConfigImport', Argument::any())->willReturn(null);
        $registry->set('siteConfigImport', Argument::cetera())->shouldNotBeCalled();
        $subject->injectRegistry($registry->reveal());

        $environment = new Environment();

        $environment::initialize(
            Environment::getContext(),
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            $configDir,
            Environment::getCurrentScript(),
            'UNIX'
        );
        $subject->processExtensionSetup($extKey);

        $siteConfigFile = $configDir . '/sites/' . $siteIdentifier . '/config.yaml';
        self::assertFileExists($siteConfigFile);
        self::assertStringEqualsFile($siteConfigFile, $config);
    }
}
