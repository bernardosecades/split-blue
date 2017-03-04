<?php

namespace Tests\BernardoSecades\Accommodation\Mocks\Command;

use BernardoSecades\SplitBlue\Command\CompileCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class CompileCommandTest extends \PHPUnit_Framework_TestCase
{
    /** @var  CommandTester $commandTester */
    protected $commandTester;

    protected function setUp()
    {
        $command = new CompileCommand();
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown()
    {
        $fileSystem = new FileSystem();
        $fileSystem->remove($this->getPathFixtures() . '/Success/Compile/Build');
    }

    /**
     * @test
     */
    public function generateMockAndDocFiles()
    {
        $this->commandTester->execute($this->getSuccessArgumentsCommand());
        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $this->assertContains('Compiling "apib" files to mock', $this->commandTester->getDisplay());
        $this->assertContains('Creating documentation from mock files compiled', $this->commandTester->getDisplay());
        $this->assertFileExists($this->getPathFixtures() . '/Success/Compile/Build/out.apib');
        $this->assertFileExists($this->getPathFixtures() . '/Success/Compile/Build/out.html');

        $this->assertContains(
            $this->getStringJSONFileIncluded(),
            file_get_contents($this->getPathFixtures() . '/Success/Compile/Build/out.apib')
        );

        $this->assertContains(
            $this->getStringXMLFileIncluded(),
            file_get_contents($this->getPathFixtures() . '/Success/Compile/Build/out.apib')
        );
    }

    /**
     * @test
     */
    public function errorGenerateMockAndDocFiles()
    {
        $this->commandTester->execute($this->getErrorArgumentsCommand());
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $this->assertContains('Compiling "apib" files to mock', $this->commandTester->getDisplay());
        $this->assertContains('Creating documentation from mock files compiled', $this->commandTester->getDisplay());
        $this->assertDirectoryNotExists($this->getPathErrorFixtures() . 'Build');
    }

    /**
     * @return array
     */
    protected function getSuccessArgumentsCommand()
    {
        return [
            'path' => $this->getPathSuccessFixtures(),
        ];
    }

    /**
     * @return array
     */
    protected function getErrorArgumentsCommand()
    {
        return [
            'path' => $this->getPathErrorFixtures(),
        ];
    }

    /**
     * @return string
     */
    protected function getPathFixtures()
    {
        return dirname(__DIR__) . '/Fixtures';
    }

    /**
     * @return string
     */
    protected function getPathSuccessFixtures()
    {
        return $this->getPathFixtures() . '/Success';
    }

    /**
     * @return string
     */
    protected function getPathErrorFixtures()
    {
        return $this->getPathFixtures() . '/Error';
    }

    /**
     * @return string
     */
    protected function getStringJSONFileIncluded()
    {
        $content = <<<EOF
{
    "rate_provider": [
        {
            "provider_cancel_policies": "xxx",
            "deatiledRate": "xxx",
            "remarks": "xxx"
        }
    ],
    "ticket": [
        {
            "a": "xxx",
            "b": "xxx"
        }
    ],
    "ttl": 3600
}
EOF;
        return $content;
    }

    /**
     * @return string
     */
    protected function getStringXMLFileIncluded()
    {
        $content = <<<EOF
<csw:GetRecordsResponse xmlns:csw="http://www.opengis.net/cat/csw" xmlns:dc="http://www.purl.org/dc/elements/1.1/" xmlns:dct="http://www.purl.org/dc/terms/" xsi:schemaLocation="http://www.opengis.net/cat/csw http://localhost:8888/SpatialWS-SpatialWS-context-root/cswservlet?recordTypeId=1 " version="2.0.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
   <csw:RequestId>4</csw:RequestId>
   <csw:SearchStatus status="complete"/>
   <csw:SearchResults recordSchema="http://www.opengis.net/cat/csw" numberOfRecordsMatched="1" numberOfRecordsReturned="1" nextRecord="0" expires="2007-02-09T16:32:35.29Z">
      <csw:Record xmlns:dc="http://www.purl.org/dc/elements/1.1/" xmlns:ows="http://www.opengis.net/ows" xmlns:dct="http://www.purl.org/dc/terms/">
         <dc:contributor xmlns:dc="http://www.purl.org/dc/elements/1.1/" scheme="http://www.example.com">Raja</dc:contributor>
         <dc:identifier xmlns:dc="http://www.purl.org/dc/elements/1.1/">REC-1</dc:identifier>
      </csw:Record>
   </csw:SearchResults>
</csw:GetRecordsResponse>
EOF;

        return $content;
    }
}
