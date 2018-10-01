<?php
namespace TYPO3\CMS\Extensionmanager\Controller;

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

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\CMS\Extensionmanager\Domain\Model\Extension;

/**
 * Controller for actions related to the TER download of an extension
 * @internal This class is a specific controller implementation and is not considered part of the Public TYPO3 API.
 */
class DownloadController extends AbstractController
{
    /**
     * @var \TYPO3\CMS\Extensionmanager\Domain\Repository\ExtensionRepository
     */
    protected $extensionRepository;

    /**
     * @var \TYPO3\CMS\Extensionmanager\Utility\FileHandlingUtility
     */
    protected $fileHandlingUtility;

    /**
     * @var \TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService
     */
    protected $managementService;

    /**
     * @var \TYPO3\CMS\Extensionmanager\Utility\InstallUtility
     */
    protected $installUtility;

    /**
     * @var \TYPO3\CMS\Extensionmanager\Utility\DownloadUtility
     */
    protected $downloadUtility;

    /**
     * @var JsonView
     */
    protected $defaultViewObjectName = JsonView::class;

    /**
     * @var JsonView
     */
    protected $view;

    /**
     * @param \TYPO3\CMS\Extensionmanager\Domain\Repository\ExtensionRepository $extensionRepository
     */
    public function injectExtensionRepository(\TYPO3\CMS\Extensionmanager\Domain\Repository\ExtensionRepository $extensionRepository)
    {
        $this->extensionRepository = $extensionRepository;
    }

    /**
     * @param \TYPO3\CMS\Extensionmanager\Utility\FileHandlingUtility $fileHandlingUtility
     */
    public function injectFileHandlingUtility(\TYPO3\CMS\Extensionmanager\Utility\FileHandlingUtility $fileHandlingUtility)
    {
        $this->fileHandlingUtility = $fileHandlingUtility;
    }

    /**
     * @param \TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService $managementService
     */
    public function injectManagementService(\TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService $managementService)
    {
        $this->managementService = $managementService;
    }

    /**
     * @param \TYPO3\CMS\Extensionmanager\Utility\InstallUtility $installUtility
     */
    public function injectInstallUtility(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility $installUtility)
    {
        $this->installUtility = $installUtility;
    }

    /**
     * @param \TYPO3\CMS\Extensionmanager\Utility\DownloadUtility $downloadUtility
     */
    public function injectDownloadUtility(\TYPO3\CMS\Extensionmanager\Utility\DownloadUtility $downloadUtility)
    {
        $this->downloadUtility = $downloadUtility;
    }

    /**
     * Defines which view object should be used for the installFromTer action
     */
    protected function initializeInstallFromTerAction()
    {
        $this->defaultViewObjectName = \TYPO3\CMS\Fluid\View\TemplateView::class;
    }

    /**
     * Check extension dependencies
     *
     * @param \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension
     * @throws \Exception
     */
    public function checkDependenciesAction(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension)
    {
        $message = '';
        $title = '';
        $hasDependencies = false;
        $hasErrors = false;
        $dependencyTypes = null;
        $configuration = [
            'value' => [
                'dependencies' => [],
            ],
        ];
        $isAutomaticInstallationEnabled = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('extensionmanager', 'automaticInstallation');
        if (!$isAutomaticInstallationEnabled) {
            // if automatic installation is deactivated, no dependency check is needed (download only)
            $action = 'installExtensionWithoutSystemDependencyCheck';
        } else {
            $action = 'installFromTer';
            try {
                $dependencyTypes = $this->managementService->getAndResolveDependencies($extension);
                if (!empty($dependencyTypes)) {
                    $hasDependencies = true;
                    $message = '<p>' . $this->translate('downloadExtension.dependencies.headline') . '</p>';
                    foreach ($dependencyTypes as $dependencyType => $dependencies) {
                        $extensions = '';
                        foreach ($dependencies as $extensionKey => $dependency) {
                            if (!isset($configuration['value']['dependencies'][$dependencyType])) {
                                $configuration['value']['dependencies'][$dependencyType] = [];
                            }
                            $configuration['value']['dependencies'][$dependencyType][$extensionKey] = [
                                '_exclude' => [
                                    'categoryIndexFromStringOrNumber',
                                ],
                            ];
                            $extensions .= $this->translate(
                                'downloadExtension.dependencies.extensionWithVersion',
                                [
                                    $extensionKey, $dependency->getVersion()
                                ]
                            ) . '<br />';
                        }
                        $message .= $this->translate(
                            'downloadExtension.dependencies.typeHeadline',
                            [
                                $this->translate('downloadExtension.dependencyType.' . $dependencyType),
                                $extensions
                            ]
                        );
                    }
                    $title = $this->translate('downloadExtension.dependencies.resolveAutomatically');
                }
            } catch (\Exception $e) {
                $hasErrors = true;
                $title = $this->translate('downloadExtension.dependencies.errorTitle');
                $message = $e->getMessage();
            }
        }

        $url = $this->uriBuilder->uriFor(
            $action,
            ['extension' => $extension->getUid(), 'format' => 'json'],
            'Download'
        );
        $this->view->setConfiguration($configuration);
        $this->view->assign('value', [
            'dependencies' => $dependencyTypes,
            'url' => $url,
            'message' => $message,
            'hasErrors' => $hasErrors,
            'hasDependencies' => $hasDependencies,
            'title' => $title
        ]);
    }

    /**
     * Install an extension from TER action
     *
     * @param \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension
     * @param string $downloadPath
     */
    public function installFromTerAction(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension, $downloadPath = 'Local')
    {
        list($result, $errorMessages) = $this->installFromTer($extension, $downloadPath);
        $isAutomaticInstallationEnabled = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('extensionmanager', 'automaticInstallation');
        $this->view
            ->assign('result', $result)
            ->assign('extension', $extension)
            ->assign('installationTypeLanguageKey', $isAutomaticInstallationEnabled ? '' : '.downloadOnly')
            ->assign('unresolvedDependencies', $errorMessages);
    }

    /**
     * Check extension dependencies with special dependencies
     *
     * @param \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension
     * @throws \Exception
     */
    public function installExtensionWithoutSystemDependencyCheckAction(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension)
    {
        $this->managementService->setSkipDependencyCheck(true);
        $this->forward('installFromTer', null, null, ['extension' => $extension, 'downloadPath' => 'Local']);
    }

    /**
     * Action for installing a distribution -
     * redirects directly to configuration after installing
     *
     * @param \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension
     */
    public function installDistributionAction(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension)
    {
        if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('impexp')) {
            $this->forward('distributions', 'List');
        }
        list($result, $errorMessages) = $this->installFromTer($extension);
        if ($errorMessages) {
            foreach ($errorMessages as $extensionKey => $messages) {
                foreach ($messages as $message) {
                    $this->addFlashMessage(
                        $message['message'],
                        \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate(
                            'distribution.error.headline',
                            'extensionmanager',
                            [$extensionKey]
                        ),
                        \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
                    );
                }
            }

            // Redirect back to distributions list action
            $this->redirect(
                'distributions',
                'List'
            );
        } else {
            // FlashMessage that extension is installed
            $this->addFlashMessage(
                \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate(
                    'distribution.welcome.message',
                    'extensionmanager',
                    [$extension->getExtensionKey()]
                ),
                \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('distribution.welcome.headline', 'extensionmanager')
            );

            // Redirect to show action
            $this->redirect(
                'show',
                'Distribution',
                null,
                ['extension' => $extension]
            );
        }
    }

    /**
     * Update an extension. Makes no sanity check but directly searches highest
     * available version from TER and updates. Update check is done by the list
     * already. This method should only be called if we are sure that there is
     * an update.
     *
     * @return string
     */
    protected function updateExtensionAction()
    {
        $extensionKey = $this->request->getArgument('extension');
        $version = $this->request->getArgument('version');
        $extension = $this->extensionRepository->findOneByExtensionKeyAndVersion($extensionKey, $version);
        if (!$extension instanceof Extension) {
            $extension = $this->extensionRepository->findHighestAvailableVersion($extensionKey);
        }
        $installedExtensions = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getLoadedExtensionListArray();
        try {
            if (in_array($extensionKey, $installedExtensions, true)) {
                // To resolve new dependencies the extension is installed again
                $this->managementService->installExtension($extension);
            } else {
                $this->managementService->downloadMainExtension($extension);
            }
            $this->addFlashMessage(
                $this->translate('extensionList.updateFlashMessage.body', [$extensionKey]),
                $this->translate('extensionList.updateFlashMessage.title')
            );
        } catch (\Exception $e) {
            $this->addFlashMessage($e->getMessage(), '', FlashMessage::ERROR);
        }

        return '';
    }

    /**
     * Show update comments for extensions that can be updated.
     * Fetches update comments for all versions between the current
     * installed and the highest version.
     */
    protected function updateCommentForUpdatableVersionsAction()
    {
        $extensionKey = $this->request->getArgument('extension');
        $versionStart = $this->request->getArgument('integerVersionStart');
        $versionStop = $this->request->getArgument('integerVersionStop');
        $updateComments = [];
        /** @var Extension[] $updatableVersions */
        $updatableVersions = $this->extensionRepository->findByVersionRangeAndExtensionKeyOrderedByVersion(
            $extensionKey,
            $versionStart,
            $versionStop,
            false
        );
        $highestPossibleVersion = false;

        foreach ($updatableVersions as $updatableVersion) {
            if ($highestPossibleVersion === false) {
                $highestPossibleVersion = $updatableVersion->getVersion();
            }
            $updateComments[$updatableVersion->getVersion()] = $updatableVersion->getUpdateComment();
        }

        $this->view->assign('value', [
            'updateComments' => $updateComments,
            'url' => $this->uriBuilder->uriFor(
                'updateExtension',
                ['extension' => $extensionKey, 'version' => $highestPossibleVersion]
            )
        ]);
    }

    /**
     * Install an extension from TER
     * Downloads the extension, resolves dependencies and installs it
     *
     * @param \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension
     * @param string $downloadPath
     * @return array
     */
    protected function installFromTer(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extension, $downloadPath = 'Local')
    {
        $result = false;
        $errorMessages = [];
        try {
            $this->downloadUtility->setDownloadPath($downloadPath);
            $isAutomaticInstallationEnabled = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('extensionmanager', 'automaticInstallation');
            $this->managementService->setAutomaticInstallationEnabled($isAutomaticInstallationEnabled);
            if (($result = $this->managementService->installExtension($extension)) === false) {
                $errorMessages = $this->managementService->getDependencyErrors();
            }
        } catch (\TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException $e) {
            $errorMessages = [
                $extension->getExtensionKey() => [
                    [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ]
                ],
            ];
        }

        return [$result, $errorMessages];
    }
}
