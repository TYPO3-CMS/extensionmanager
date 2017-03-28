<?php
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

/**
 * List utility test
 */
class ListUtilityTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
{
    /**
     * @var \TYPO3\CMS\Extensionmanager\Utility\ListUtility
     */
    protected $subject;

    /**
     */
    protected function setUp()
    {
        $this->subject = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Utility\ListUtility::class)
            ->setMethods(['emitPackagesMayHaveChangedSignal'])
            ->getMock();
        $packageManagerMock = $this->getMockBuilder(\TYPO3\CMS\Core\Package\PackageManager::class)->getMock();
        $packageManagerMock
                ->expects($this->any())
                ->method('getActivePackages')
                ->will($this->returnValue([
                    'lang' => $this->getMockBuilder(\TYPO3\CMS\Core\Package::class)->disableOriginalConstructor()->getMock(),
                    'news' => $this->getMockBuilder(\TYPO3\CMS\Core\Package::class)->disableOriginalConstructor()->getMock(),
                    'saltedpasswords' => $this->getMockBuilder(\TYPO3\CMS\Core\Package::class)->disableOriginalConstructor()->getMock(),
                    'rsaauth' => $this->getMockBuilder(\TYPO3\CMS\Core\Package::class)->disableOriginalConstructor()->getMock(),
                ]));
        $this->inject($this->subject, 'packageManager', $packageManagerMock);
    }

    /**
     * @return array
     */
    public function getAvailableAndInstalledExtensionsDataProvider()
    {
        return [
            'same extension lists' => [
                [
                    'lang' => [],
                    'news' => [],
                    'saltedpasswords' => [],
                    'rsaauth' => []
                ],
                [
                    'lang' => ['installed' => true],
                    'news' => ['installed' => true],
                    'saltedpasswords' => ['installed' => true],
                    'rsaauth' => ['installed' => true]
                ]
            ],
            'different extension lists' => [
                [
                    'lang' => [],
                    'news' => [],
                    'saltedpasswords' => [],
                    'rsaauth' => []
                ],
                [
                    'lang' => ['installed' => true],
                    'news' => ['installed' => true],
                    'saltedpasswords' => ['installed' => true],
                    'rsaauth' => ['installed' => true]
                ]
            ],
            'different extension lists - set2' => [
                [
                    'lang' => [],
                    'news' => [],
                    'saltedpasswords' => [],
                    'rsaauth' => [],
                    'em' => []
                ],
                [
                    'lang' => ['installed' => true],
                    'news' => ['installed' => true],
                    'saltedpasswords' => ['installed' => true],
                    'rsaauth' => ['installed' => true],
                    'em' => []
                ]
            ],
            'different extension lists - set3' => [
                [
                    'lang' => [],
                    'fluid' => [],
                    'news' => [],
                    'saltedpasswords' => [],
                    'rsaauth' => [],
                    'em' => []
                ],
                [
                    'lang' => ['installed' => true],
                    'fluid' => [],
                    'news' => ['installed' => true],
                    'saltedpasswords' => ['installed' => true],
                    'rsaauth' => ['installed' => true],
                    'em' => []
                ]
            ]
        ];
    }

    /**
     * @test
     * @dataProvider getAvailableAndInstalledExtensionsDataProvider
     * @param $availableExtensions
     * @param $expectedResult
     */
    public function getAvailableAndInstalledExtensionsTest($availableExtensions, $expectedResult)
    {
        $this->assertEquals($expectedResult, $this->subject->getAvailableAndInstalledExtensions($availableExtensions));
    }

    /**
     * @return array
     */
    public function enrichExtensionsWithEmConfInformationDataProvider()
    {
        return [
            'simple key value array emconf' => [
                [
                    'lang' => ['property1' => 'oldvalue'],
                    'news' => [],
                    'saltedpasswords' => [],
                    'rsaauth' => []
                ],
                [
                    'property1' => 'property value1'
                ],
                [
                    'lang' => ['property1' => 'oldvalue'],
                    'news' => ['property1' => 'property value1'],
                    'saltedpasswords' => ['property1' => 'property value1'],
                    'rsaauth' => ['property1' => 'property value1']
                ]
            ]
        ];
    }

    /**
     * @test
     * @dataProvider enrichExtensionsWithEmConfInformationDataProvider
     * @param $extensions
     * @param $emConf
     * @param $expectedResult
     */
    public function enrichExtensionsWithEmConfInformation($extensions, $emConf, $expectedResult)
    {
        $this->inject($this->subject, 'extensionRepository', $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Repository\ExtensionRepository::class, ['findOneByExtensionKeyAndVersion', 'findHighestAvailableVersion'], [], '', false));
        $emConfUtilityMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Utility\EmConfUtility::class)->getMock();
        $emConfUtilityMock->expects($this->any())->method('includeEmConf')->will($this->returnValue($emConf));
        $this->inject($this->subject, 'emConfUtility', $emConfUtilityMock);
        $this->assertEquals($expectedResult, $this->subject->enrichExtensionsWithEmConfAndTerInformation($extensions));
    }
}
