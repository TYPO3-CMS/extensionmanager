<?php

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

namespace TYPO3\CMS\Extensionmanager\Utility\Importer;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Platform\PlatformInformation;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extensionmanager\Domain\Model\Extension;
use TYPO3\CMS\Extensionmanager\Domain\Repository\ExtensionRepository;
use TYPO3\CMS\Extensionmanager\Domain\Repository\RepositoryRepository;
use TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException;
use TYPO3\CMS\Extensionmanager\Utility\Parser\AbstractExtensionXmlParser;
use TYPO3\CMS\Extensionmanager\Utility\Parser\XmlParserFactory;

/**
 * Importer object for extension list
 * @internal This class is a specific ExtensionManager implementation and is not part of the Public TYPO3 API.
 */
class ExtensionListUtility implements \SplObserver
{
    /**
     * Keeps instance of a XML parser.
     *
     * @var AbstractExtensionXmlParser
     */
    protected $parser;

    /**
     * Keeps number of processed version records.
     *
     * @var int
     */
    protected $sumRecords = 0;

    /**
     * Keeps record values to be inserted into database.
     *
     * @var array
     */
    protected $arrRows = [];

    /**
     * Keeps fieldnames of tx_extensionmanager_domain_model_extension table.
     *
     * @var array
     */
    protected static $fieldNames = [
        'extension_key',
        'version',
        'integer_version',
        'current_version',
        'alldownloadcounter',
        'downloadcounter',
        'title',
        'ownerusername',
        'author_name',
        'author_email',
        'authorcompany',
        'last_updated',
        'md5hash',
        'repository',
        'state',
        'review_state',
        'category',
        'description',
        'serialized_dependencies',
        'update_comment',
        'documentation_link'
    ];

    /**
     * Table name to be used to store extension models.
     *
     * @var string
     */
    protected static $tableName = 'tx_extensionmanager_domain_model_extension';

    /**
     * Maximum of rows that can be used in a bulk insert for the current
     * database platform.
     *
     * @var int
     */
    protected $maxRowsPerChunk = 50;

    /**
     * Keeps repository UID.
     *
     * The UID is necessary for inserting records.
     *
     * @var int
     */
    protected $repositoryUid = 1;

    /**
     * @var \TYPO3\CMS\Extensionmanager\Domain\Repository\RepositoryRepository
     */
    protected $repositoryRepository;

    /**
     * @var \TYPO3\CMS\Extensionmanager\Domain\Repository\ExtensionRepository
     */
    protected $extensionRepository;

    /**
     * @var \TYPO3\CMS\Extensionmanager\Domain\Model\Extension
     */
    protected $extensionModel;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * Only import extensions newer than this date (timestamp),
     * see constructor
     *
     * @var int
     */
    protected $minimumDateToImport;

    /**
     * Class constructor.
     *
     * Method retrieves and initializes extension XML parser instance.
     *
     * @throws \TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException
     */
    public function __construct()
    {
        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->repositoryRepository = $this->objectManager->get(RepositoryRepository::class);
        $this->extensionRepository = $this->objectManager->get(ExtensionRepository::class);
        $this->extensionModel = $this->objectManager->get(Extension::class);
        // @todo catch parser exception
        $this->parser = XmlParserFactory::getParserInstance('extension');
        if (is_object($this->parser)) {
            $this->parser->attach($this);
        } else {
            throw new ExtensionManagerException(
                static::class . ': No XML parser available.',
                1476108717
            );
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::$tableName);
        $maxBindParameters = PlatformInformation::getMaxBindParameters(
            $connection->getDatabasePlatform()
        );
        $countOfBindParamsPerRow = count(self::$fieldNames);
        // flush at least chunks of 50 elements - in case the currently used
        // database platform does not support that, the threshold is lowered
        $this->maxRowsPerChunk = min(
            $this->maxRowsPerChunk,
            floor($maxBindParameters / $countOfBindParamsPerRow)
        );
        // Only import extensions that are compatible with 7.6 or higher.
        // TER only allows to publish extensions with compatibility if the TYPO3 version has been released
        // And 7.6 was released on 10th of November 2015.
        // This effectively reduces the number of extensions imported into this TYPO3 installation
        // by more than 70%. As long as the extensions.xml from TER includes these files, we need to "hack" this
        // within TYPO3 Core.
        // For TYPO3 v11.0, this date could be set to 2018-10-02 (v9 LTS release).
        // Also see https://decisions.typo3.org/t/reduce-size-of-extension-manager-db-table/329/
        $this->minimumDateToImport = strtotime('2017-04-04T00:00:00+00:00');
    }

    /**
     * Method initializes parsing of extension.xml.gz file.
     *
     * @param string $localExtensionListFile absolute path to extension list xml.gz
     * @param int $repositoryUid UID of repository when inserting records into DB
     * @return int total number of imported extension versions
     */
    public function import($localExtensionListFile, $repositoryUid = null)
    {
        if ($repositoryUid !== null && is_int($repositoryUid)) {
            $this->repositoryUid = $repositoryUid;
        }
        $zlibStream = 'compress.zlib://';
        $this->sumRecords = 0;
        $this->parser->parseXml($zlibStream . $localExtensionListFile);
        // flush last rows to database if existing
        if (!empty($this->arrRows)) {
            GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tx_extensionmanager_domain_model_extension')
                ->bulkInsert(
                    'tx_extensionmanager_domain_model_extension',
                    $this->arrRows,
                    self::$fieldNames
                );
        }
        $extensions = $this->extensionRepository->insertLastVersion($this->repositoryUid);
        $this->repositoryRepository->updateRepositoryCount($extensions, $this->repositoryUid);
        return $this->sumRecords;
    }

    /**
     * Method collects and stores extension version details into the database.
     *
     * @param AbstractExtensionXmlParser $subject a subject notifying this observer
     */
    protected function loadIntoDatabase(AbstractExtensionXmlParser &$subject)
    {
        if ($this->sumRecords !== 0 && $this->sumRecords % $this->maxRowsPerChunk === 0) {
            GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable(self::$tableName)
                ->bulkInsert(
                    self::$tableName,
                    $this->arrRows,
                    self::$fieldNames
                );
            $this->arrRows = [];
        }
        $versionRepresentations = VersionNumberUtility::convertVersionStringToArray($subject->getVersion());
        // order must match that of self::$fieldNames!
        $this->arrRows[] = [
            $subject->getExtkey(),
            $subject->getVersion(),
            $versionRepresentations['version_int'],
            // initialize current_version, correct value computed later:
            0,
            (int)$subject->getAlldownloadcounter(),
            (int)$subject->getDownloadcounter(),
            $subject->getTitle() ?? '',
            $subject->getOwnerusername(),
            $subject->getAuthorname() ?? '',
            $subject->getAuthoremail() ?? '',
            $subject->getAuthorcompany() ?? '',
            (int)$subject->getLastuploaddate(),
            $subject->getT3xfilemd5(),
            $this->repositoryUid,
            $this->extensionModel->getDefaultState($subject->getState() ?: ''),
            (int)$subject->getReviewstate(),
            $this->extensionModel->getCategoryIndexFromStringOrNumber($subject->getCategory() ?: ''),
            $subject->getDescription() ?: '',
            $subject->getDependencies() ?: '',
            $subject->getUploadcomment() ?: '',
            $subject->getDocumentationLink() ?: '',
        ];
        ++$this->sumRecords;
    }

    /**
     * Method receives an update from a subject.
     *
     * @param \SplSubject $subject a subject notifying this observer
     */
    public function update(\SplSubject $subject)
    {
        if (is_subclass_of($subject, AbstractExtensionXmlParser::class)) {
            if ((int)$subject->getLastuploaddate() > $this->minimumDateToImport) {
                $this->loadIntoDatabase($subject);
            }
        }
    }
}
