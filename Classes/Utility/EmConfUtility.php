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

namespace TYPO3\CMS\Extensionmanager\Utility;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extensionmanager\Domain\Model\Extension;

/**
 * Utility for dealing with ext_emconf
 * @internal This class is a specific ExtensionManager implementation and is not part of the Public TYPO3 API.
 */
class EmConfUtility implements SingletonInterface
{
    /**
     * Returns the $EM_CONF array from an extensions ext_emconf.php file
     *
     * @param string $extensionKey the extension name
     * @param array $extension Extension information array
     * @return array|bool EMconf array values or false if no ext_emconf.php found.
     */
    public function includeEmConf(string $extensionKey, array $extension)
    {
        $_EXTKEY = $extensionKey;
        if (!empty($extension['packagePath'])) {
            $path = $extension['packagePath'] . 'ext_emconf.php';
        } else {
            $path = Environment::getPublicPath() . '/' . $extension['siteRelPath'] . 'ext_emconf.php';
        }
        $EM_CONF = null;
        if (file_exists($path)) {
            include $path;
            if (is_array($EM_CONF[$_EXTKEY])) {
                return $EM_CONF[$_EXTKEY];
            }
        }
        return false;
    }

    /**
     * Generates the content for the ext_emconf.php file
     * Sets dependencies from TER data if any
     *
     * @param array $extensionData
     * @param Extension $extension Extension object from TER data
     * @return string
     * @internal
     */
    public function constructEmConf(array $extensionData, Extension $extension = null)
    {
        if (is_object($extension) && empty($extensionData['EM_CONF']['constraints'])) {
            $extensionData['EM_CONF']['constraints'] = unserialize($extension->getSerializedDependencies(), ['allowed_classes' => false]);
        }
        $emConf = $this->fixEmConf($extensionData['EM_CONF']);
        $emConf = var_export($emConf, true);
        $code = '<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "' . $extensionData['extKey'] . '".
 *
 * Auto generated ' . date('d-m-Y H:i') . '
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = ' . $emConf . ';

';
        return $code;
    }

    /**
     * Fix the em conf - Converts old / ter em_conf format to new format
     *
     * @param array $emConf
     * @return array
     */
    protected function fixEmConf(array $emConf)
    {
        if (
            !isset($emConf['constraints']) || !isset($emConf['constraints']['depends'])
            || !isset($emConf['constraints']['conflicts']) || !isset($emConf['constraints']['suggests'])
        ) {
            if (!isset($emConf['constraints']) || !isset($emConf['constraints']['depends'])) {
                $emConf['constraints']['depends'] = $this->stringToDependency($emConf['dependencies'] ?? '');
                if (isset($emConf['PHP_version']) && (string)$emConf['PHP_version'] !== '') {
                    $emConf['constraints']['depends']['php'] = $emConf['PHP_version'];
                }
                if (isset($emConf['TYPO3_version']) && (string)$emConf['TYPO3_version'] !== '') {
                    $emConf['constraints']['depends']['typo3'] = $emConf['TYPO3_version'];
                }
            }
            if (!isset($emConf['constraints']) || !isset($emConf['constraints']['conflicts'])) {
                $emConf['constraints']['conflicts'] = $this->stringToDependency($emConf['conflicts'] ?? '');
            }
            if (!isset($emConf['constraints']) || !isset($emConf['constraints']['suggests'])) {
                $emConf['constraints']['suggests'] = [];
            }
        }

        // Remove TER v1-style entries
        unset($emConf['dependencies']);
        unset($emConf['conflicts']);
        unset($emConf['suggests']);
        unset($emConf['private']);
        unset($emConf['download_password']);
        unset($emConf['TYPO3_version']);
        unset($emConf['PHP_version']);
        unset($emConf['internal']);
        unset($emConf['module']);
        unset($emConf['loadOrder']);
        unset($emConf['lockType']);
        unset($emConf['createDirs']);
        unset($emConf['shy']);
        unset($emConf['priority']);
        unset($emConf['modify_tables']);
        unset($emConf['CGLcompliance']);
        unset($emConf['CGLcompliance_note']);

        return $emConf;
    }

    /**
     * Checks whether the passed dependency is TER-style (string)or
     * TER2-style (array)and returns a single string for displaying the
     * dependencies.
     *
     * It leaves out all version numbers and the "php" and "typo3" dependencies,
     * as they are implicit and of no interest without the version number.
     *
     * @param mixed $dependency Either a string or an array listing dependencies.
     * @return array A simple dependency list for display
     */
    protected function stringToDependency($dependency)
    {
        $constraint = [];
        if (is_string($dependency) && $dependency !== '') {
            $dependency = explode(',', $dependency);
            foreach ($dependency as $v) {
                $constraint[$v] = '';
            }
        }
        return $constraint;
    }
}
