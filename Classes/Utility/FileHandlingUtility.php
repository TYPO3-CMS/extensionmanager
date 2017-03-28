<?php
namespace TYPO3\CMS\Extensionmanager\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extensionmanager\Domain\Model\Extension;
use TYPO3\CMS\Extensionmanager\Exception\ExtensionManagerException;

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

/**
 * Utility for dealing with files and folders
 */
class FileHandlingUtility implements \TYPO3\CMS\Core\SingletonInterface
{
    /**
     * @var \TYPO3\CMS\Extensionmanager\Utility\EmConfUtility
     */
    protected $emConfUtility;

    /**
     * @var \TYPO3\CMS\Extensionmanager\Utility\InstallUtility
     */
    protected $installUtility;

    /**
     * @var \TYPO3\CMS\Lang\LanguageService
     */
    protected $languageService;

    /**
     * @param \TYPO3\CMS\Extensionmanager\Utility\EmConfUtility $emConfUtility
     */
    public function injectEmConfUtility(\TYPO3\CMS\Extensionmanager\Utility\EmConfUtility $emConfUtility)
    {
        $this->emConfUtility = $emConfUtility;
    }

    /**
     * @param \TYPO3\CMS\Extensionmanager\Utility\InstallUtility $installUtility
     */
    public function injectInstallUtility(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility $installUtility)
    {
        $this->installUtility = $installUtility;
    }

    /**
     * @param \TYPO3\CMS\Lang\LanguageService $languageService
     */
    public function injectLanguageService(\TYPO3\CMS\Lang\LanguageService $languageService)
    {
        $this->languageService = $languageService;
    }

    /**
     * Initialize method - loads language file
     */
    public function initializeObject()
    {
        $this->languageService->includeLLFile('EXT:extensionmanager/Resources/Private/Language/locallang.xlf');
    }

    /**
     * Unpack an extension in t3x data format and write files
     *
     * @param array $extensionData
     * @param Extension $extension
     * @param string $pathType
     */
    public function unpackExtensionFromExtensionDataArray(array $extensionData, Extension $extension = null, $pathType = 'Local')
    {
        $extensionDir = $this->makeAndClearExtensionDir($extensionData['extKey'], $pathType);
        $files = $this->extractFilesArrayFromExtensionData($extensionData);
        $directories = $this->extractDirectoriesFromExtensionData($files);
        $files = array_diff_key($files, array_flip($directories));
        $this->createDirectoriesForExtensionFiles($directories, $extensionDir);
        $this->writeExtensionFiles($files, $extensionDir);
        $this->writeEmConfToFile($extensionData, $extensionDir, $extension);
        $this->reloadPackageInformation($extensionData['extKey']);
    }

    /**
     * Extract needed directories from given extensionDataFilesArray
     *
     * @param array $files
     * @return array
     */
    protected function extractDirectoriesFromExtensionData(array $files)
    {
        $directories = [];
        foreach ($files as $filePath => $file) {
            preg_match('/(.*)\\//', $filePath, $matches);
            if (!empty($matches[0])) {
                $directories[] = $matches[0];
            }
        }
        return array_unique($directories);
    }

    /**
     * Returns the "FILES" part from the data array
     *
     * @param array $extensionData
     * @return mixed
     */
    protected function extractFilesArrayFromExtensionData(array $extensionData)
    {
        return $extensionData['FILES'];
    }

    /**
     * Loops over an array of directories and creates them in the given root path
     * It also creates nested directory structures
     *
     * @param array $directories
     * @param string $rootPath
     */
    protected function createDirectoriesForExtensionFiles(array $directories, $rootPath)
    {
        foreach ($directories as $directory) {
            $this->createNestedDirectory($rootPath . $directory);
        }
    }

    /**
     * Wrapper for utility method to create directory recusively
     *
     * @param string $directory Absolute path
     * @throws ExtensionManagerException
     */
    protected function createNestedDirectory($directory)
    {
        try {
            GeneralUtility::mkdir_deep($directory);
        } catch (\RuntimeException $exception) {
            throw new ExtensionManagerException(
                sprintf($this->languageService->getLL('fileHandling.couldNotCreateDirectory'), $this->getRelativePath($directory)),
                1337280416
            );
        }
    }

    /**
     * Loops over an array of files and writes them to the given rootPath
     *
     * @param array $files
     * @param string $rootPath
     */
    protected function writeExtensionFiles(array $files, $rootPath)
    {
        foreach ($files as $file) {
            GeneralUtility::writeFile($rootPath . $file['name'], $file['content']);
        }
    }

    /**
     * Removes the current extension of $type and creates the base folder for
     * the new one (which is going to be imported)
     *
     * @param string $extensionKey
     * @param string $pathType Extension installation scope (Local,Global,System)
     * @throws ExtensionManagerException
     * @return string
     */
    protected function makeAndClearExtensionDir($extensionKey, $pathType = 'Local')
    {
        $extDirPath = $this->getExtensionDir($extensionKey, $pathType);
        if (is_dir($extDirPath)) {
            $this->removeDirectory($extDirPath);
        }
        $this->addDirectory($extDirPath);

        return $extDirPath;
    }

    /**
     * Returns the installation directory for an extension depending on the installation scope
     *
     * @param string $extensionKey
     * @param string $pathType Extension installation scope (Local,Global,System)
     * @return string
     * @throws ExtensionManagerException
     */
    public function getExtensionDir($extensionKey, $pathType = 'Local')
    {
        $paths = Extension::returnInstallPaths();
        $path = $paths[$pathType];
        if (!$path || !is_dir($path) || !$extensionKey) {
            throw new ExtensionManagerException(
                sprintf($this->languageService->getLL('fileHandling.installPathWasNoDirectory'), $this->getRelativePath($path)),
                1337280417
            );
        }

        return $path . $extensionKey . '/';
    }

    /**
     * Add specified directory
     *
     * @param string $extDirPath
     * @throws ExtensionManagerException
     */
    protected function addDirectory($extDirPath)
    {
        GeneralUtility::mkdir($extDirPath);
        if (!is_dir($extDirPath)) {
            throw new ExtensionManagerException(
                sprintf($this->languageService->getLL('fileHandling.couldNotCreateDirectory'), $this->getRelativePath($extDirPath)),
                1337280418
            );
        }
    }

    /**
     * Creates directories configured in ext_emconf.php if not already present
     *
     * @param array $extension
     */
    public function ensureConfiguredDirectoriesExist(array $extension)
    {
        foreach ($this->getAbsolutePathsToConfiguredDirectories($extension) as $directory) {
            if (!$this->directoryExists($directory)) {
                $this->createNestedDirectory($directory);
            }
        }
    }

    /**
     * Wrapper method for directory existence check
     *
     * @param string $directory
     * @return bool
     */
    protected function directoryExists($directory)
    {
        return is_dir($directory);
    }

    /**
     * Checks configuration and returns an array of absolute paths that should be created
     *
     * @param array $extension
     * @return array
     */
    protected function getAbsolutePathsToConfiguredDirectories(array $extension)
    {
        $requestedDirectories = [];
        $requestUploadFolder = isset($extension['uploadfolder']) ? (bool)$extension['uploadfolder'] : false;
        if ($requestUploadFolder) {
            $requestedDirectories[] = $this->getAbsolutePath($this->getPathToUploadFolder($extension));
        }

        $requestCreateDirectories = empty($extension['createDirs']) ? false : (string)$extension['createDirs'];
        if ($requestCreateDirectories) {
            foreach (GeneralUtility::trimExplode(',', $extension['createDirs']) as $directoryToCreate) {
                $requestedDirectories[] = $this->getAbsolutePath($directoryToCreate);
            }
        }

        return $requestedDirectories;
    }

    /**
     * Upload folders always reside in “uploads/tx_[extKey-with-no-underscore]”
     *
     * @param array $extension
     * @return string
     */
    protected function getPathToUploadFolder($extension)
    {
        return 'uploads/tx_' . str_replace('_', '', $extension['key']) . '/';
    }

    /**
     * Remove specified directory
     *
     * @param string $extDirPath
     * @throws ExtensionManagerException
     */
    public function removeDirectory($extDirPath)
    {
        $extDirPath = GeneralUtility::fixWindowsFilePath($extDirPath);
        $extensionPathWithoutTrailingSlash = rtrim($extDirPath, '/');
        if (is_link($extensionPathWithoutTrailingSlash) && TYPO3_OS !== 'WIN') {
            $result = unlink($extensionPathWithoutTrailingSlash);
        } else {
            $result = GeneralUtility::rmdir($extDirPath, true);
        }
        if ($result === false) {
            throw new ExtensionManagerException(
                sprintf($this->languageService->getLL('fileHandling.couldNotRemoveDirectory'), $this->getRelativePath($extDirPath)),
                1337280415
            );
        }
    }

    /**
     * Constructs emConf and writes it to corresponding file
     * In case the file has been extracted already, the properties of the meta data take precedence but are merged with the present ext_emconf.php
     *
     * @param array $extensionData
     * @param string $rootPath
     * @param Extension $extension
     */
    protected function writeEmConfToFile(array $extensionData, $rootPath, Extension $extension = null)
    {
        $emConfFileData = [];
        if (file_exists($rootPath . 'ext_emconf.php')) {
            $emConfFileData = $this->emConfUtility->includeEmConf(
                [
                    'key' => $extensionData['extKey'],
                    'siteRelPath' => PathUtility::stripPathSitePrefix($rootPath)
                ]
            );
        }
        $extensionData['EM_CONF'] = array_replace_recursive($emConfFileData, $extensionData['EM_CONF']);
        $emConfContent = $this->emConfUtility->constructEmConf($extensionData, $extension);
        GeneralUtility::writeFile($rootPath . 'ext_emconf.php', $emConfContent);
    }

    /**
     * Is the given path a valid path for extension installation
     *
     * @param string $path the absolute (!) path in question
     * @return bool
     */
    public function isValidExtensionPath($path)
    {
        $allowedPaths = Extension::returnAllowedInstallPaths();
        foreach ($allowedPaths as $allowedPath) {
            if (GeneralUtility::isFirstPartOfStr($path, $allowedPath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns absolute path
     *
     * @param string $relativePath
     * @throws ExtensionManagerException
     * @return string
     */
    protected function getAbsolutePath($relativePath)
    {
        $absolutePath = GeneralUtility::getFileAbsFileName(GeneralUtility::resolveBackPath(PATH_site . $relativePath));
        if (empty($absolutePath)) {
            throw new ExtensionManagerException('Illegal relative path given', 1350742864);
        }
        return $absolutePath;
    }

    /**
     * Returns relative path
     *
     * @param string $absolutePath
     * @return string
     */
    protected function getRelativePath($absolutePath)
    {
        return PathUtility::stripPathSitePrefix($absolutePath);
    }

    /**
     * Get extension path for an available or installed extension
     *
     * @param string $extension
     * @return string
     */
    public function getAbsoluteExtensionPath($extension)
    {
        $extension = $this->installUtility->enrichExtensionWithDetails($extension);
        $absolutePath = $this->getAbsolutePath($extension['siteRelPath']);
        return $absolutePath;
    }

    /**
     * Get version of an available or installed extension
     *
     * @param string $extension
     * @return string
     */
    public function getExtensionVersion($extension)
    {
        $extensionData = $this->installUtility->enrichExtensionWithDetails($extension);
        $version = $extensionData['version'];
        return $version;
    }

    /**
     * Create a zip file from an extension
     *
     * @param array $extension
     * @return string Name and path of create zip file
     */
    public function createZipFileFromExtension($extension)
    {
        $extensionPath = $this->getAbsoluteExtensionPath($extension);

        // Add trailing slash to the extension path, getAllFilesAndFoldersInPath explicitly requires that.
        $extensionPath = PathUtility::sanitizeTrailingSeparator($extensionPath);

        $version = $this->getExtensionVersion($extension);
        if (empty($version)) {
            $version =  '0.0.0';
        }

        if (!@is_dir(PATH_site . 'typo3temp/var/ExtensionManager/')) {
            GeneralUtility::mkdir(PATH_site . 'typo3temp/var/ExtensionManager/');
        }
        $fileName = $this->getAbsolutePath('typo3temp/var/ExtensionManager/' . $extension . '_' . $version . '_' . date('YmdHi', $GLOBALS['EXEC_TIME']) . '.zip');

        $zip = new \ZipArchive();
        $zip->open($fileName, \ZipArchive::CREATE);

        $excludePattern = $GLOBALS['TYPO3_CONF_VARS']['EXT']['excludeForPackaging'];

        // Get all the files of the extension, but exclude the ones specified in the excludePattern
        $files = GeneralUtility::getAllFilesAndFoldersInPath(
            [],            // No files pre-added
            $extensionPath,        // Start from here
            '',                    // Do not filter files by extension
            true,                // Include subdirectories
            PHP_INT_MAX,        // Recursion level
            $excludePattern        // Files and directories to exclude.
        );

        // Make paths relative to extension root directory.
        $files = GeneralUtility::removePrefixPathFromList($files, $extensionPath);

        // Remove the one empty path that is the extension dir itself.
        $files = array_filter($files);

        foreach ($files as $file) {
            $fullPath = $extensionPath . $file;
            // Distinguish between files and directories, as creation of the archive
            // fails on Windows when trying to add a directory with "addFile".
            if (is_dir($fullPath)) {
                $zip->addEmptyDir($file);
            } else {
                $zip->addFile($fullPath, $file);
            }
        }

        $zip->close();
        return $fileName;
    }

    /**
     * Unzip an extension.zip.
     *
     * @param string $file path to zip file
     * @param string $fileName file name
     * @param string $pathType path type (Local, Global, System)
     * @throws ExtensionManagerException
     */
    public function unzipExtensionFromFile($file, $fileName, $pathType = 'Local')
    {
        $extensionDir = $this->makeAndClearExtensionDir($fileName, $pathType);
        $zip = zip_open($file);
        if (is_resource($zip)) {
            while (($zipEntry = zip_read($zip)) !== false) {
                if (strpos(zip_entry_name($zipEntry), '/') !== false) {
                    $last = strrpos(zip_entry_name($zipEntry), '/');
                    $dir = substr(zip_entry_name($zipEntry), 0, $last);
                    $file = substr(zip_entry_name($zipEntry), strrpos(zip_entry_name($zipEntry), '/') + 1);
                    if (!is_dir($extensionDir . $dir)) {
                        GeneralUtility::mkdir_deep($extensionDir . $dir);
                    }
                    if (trim($file) !== '') {
                        $return = GeneralUtility::writeFile($extensionDir . $dir . '/' . $file, zip_entry_read($zipEntry, zip_entry_filesize($zipEntry)));
                        if ($return === false) {
                            throw new ExtensionManagerException('Could not write file ' . $this->getRelativePath($file), 1344691048);
                        }
                    }
                } else {
                    GeneralUtility::writeFile($extensionDir . zip_entry_name($zipEntry), zip_entry_read($zipEntry, zip_entry_filesize($zipEntry)));
                }
            }
        } else {
            throw new ExtensionManagerException('Unable to open zip file ' . $this->getRelativePath($file), 1344691049);
        }
    }

    /**
     * Sends a zip file to the browser and deletes it afterwards
     *
     * @param string $fileName
     * @param string $downloadName
     */
    public function sendZipFileToBrowserAndDelete($fileName, $downloadName = '')
    {
        if ($downloadName === '') {
            $downloadName = basename($fileName, '.zip');
        }
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($fileName));
        header('Content-Disposition: attachment; filename="' . $downloadName . '.zip"');
        readfile($fileName);
        unlink($fileName);
        die;
    }

    /**
     * Sends the sql dump file to the browser and deletes it afterwards
     *
     * @param string $fileName
     * @param string $downloadName
     */
    public function sendSqlDumpFileToBrowserAndDelete($fileName, $downloadName = '')
    {
        if ($downloadName === '') {
            $downloadName = basename($fileName, '.sql');
        } else {
            $downloadName = basename($downloadName, '.sql');
        }
        header('Content-Type: text');
        header('Content-Length: ' . filesize($fileName));
        header('Content-Disposition: attachment; filename="' . $downloadName . '.sql"');
        readfile($fileName);
        unlink($fileName);
        die;
    }

    /**
     * @param string $extensionKey
     */
    protected function reloadPackageInformation($extensionKey)
    {
        $this->installUtility->reloadPackageInformation($extensionKey);
    }
}
