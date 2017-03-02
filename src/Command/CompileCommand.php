<?php

/**
 * MIT License
 *
 * Copyright (c) 2017 ATRAPALO
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Atrapalo\SplitBlue\Command;

use Atrapalo\SplitBlue\Exception\DocException;
use Atrapalo\SplitBlue\Exception\MockException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use SplFileInfo;

class CompileCommand extends Command
{
    /** @var array */
    protected $compiledFiles = [];
    /** @var array */
    protected $docCreatedFiles = [];
    /** @var OutputInterface */
    protected $output;
    /** @var string */
    protected $mdPath;

    protected function configure()
    {
        $this
            ->setName('atrapalo:mocks-compile')
            ->setAliases(['c'])
            ->setDescription('Compile .apib files to generate documentation and unique files to load in mock server')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Base folder to start to search files index.apib',
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR .'md'
            );
    }
    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (function_exists('xdebug_disable')) {
            xdebug_disable();
        }

        $this->mdPath = $input->getArgument('path');

        $this->createMockFiles();
        $output->writeln('');
        $this->createDocFiles();
        $output->writeln('');
        $this->renderFilesInfo();

        if ($this->hasGeneratedFiles()) {
            return 0;
        }

        return 1;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
    }

    protected function createMockFiles()
    {
        $files = $this->getFilesToMock();
        $this->output->writeln('<info>Compiling "apib" files to mock</info>');
        $progress = new ProgressBar($this->output, count($files));
        $progress->setBarCharacter('<fg=magenta>=</>');
        $progress->setProgressCharacter("\xF0\x9F\x8D\xBA");

        foreach ($files as $file) {
            $this->compileToMock($file->getRealPath());
            $progress->advance();
        }

        $progress->finish();
    }

    protected function createDocFiles()
    {
        $files = $this->getFilesToDoc();
        $this->output->writeln('<info>Creating documentation from mock files compiled</info>');
        $progress = new ProgressBar($this->output, count($files));
        $progress->setBarCharacter('<fg=magenta>=</>');
        $progress->setProgressCharacter("\xF0\x9F\x8D\xBA");

        foreach ($files as $file) {
            $this->compileToDoc($file->getRealPath());
            $progress->advance();
        }

        $progress->finish();
    }

    /**
     * @param string $fileName
     */
    protected function compileToMock($fileName)
    {
        $this->executeMarkdownPP($fileName);
    }

    /**
     * @param string $fileName
     */
    protected function compileToDoc($fileName)
    {
        $this->executeAglio($fileName);
    }

    /**
     * @param string $fileName
     * @throws DocException
     */
    protected function executeAglio($fileName)
    {
        $workingDirectory = dirname($fileName);

        if ('Build' != basename($workingDirectory)) {
            $pathBuild = $workingDirectory . DIRECTORY_SEPARATOR . 'Build';
            $fileSystem = new Filesystem();
            try {
                $fileSystem->mkdir($pathBuild);
            } catch (IOExceptionInterface $exception) {
                throw new DocException(
                    sprintf('Can not create folder %s', $pathBuild),
                    0,
                    $exception
                );
            }
        } else {
            $pathBuild = $workingDirectory;
        }

        $createdFile = sprintf('%s/out.html', $pathBuild);
        $commandLine = sprintf('aglio -i %s -o %s', $fileName, $createdFile);
        $process = new Process($commandLine);
        $process->setWorkingDirectory($workingDirectory);

        try {
            $process->mustRun();
            $this->docCreatedFiles[] = $createdFile;
        } catch (ProcessFailedException $e) {
            throw new DocException(
                sprintf(
                    'Error to generate mock file with markdown-pp: %s, maybe markdown-pp is not installed',
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * @param string $fileName
     * @throws MockException
     */
    protected function executeMarkdownPP($fileName)
    {
        $workingDirectory = dirname($fileName);

        if ('Build' != basename($workingDirectory)) {
            $pathBuild = $workingDirectory . DIRECTORY_SEPARATOR . 'Build';
            $fileSystem = new Filesystem();
            try {
                $fileSystem->mkdir($pathBuild);
            } catch (IOExceptionInterface $exception) {
                throw new MockException(
                    sprintf('Can not create folder %s', $pathBuild),
                    0,
                    $exception
                );
            }
        } else {
            $pathBuild = $workingDirectory;
        }

        $fileCreated = sprintf('%s/out.apib', $pathBuild);
        $commandLine = sprintf('markdown-pp %s -o %s', $fileName, $fileCreated);
        $process = new Process($commandLine);
        $process->setWorkingDirectory($workingDirectory);

        try {
            $process->mustRun();
            $this->compiledFiles[] = $fileCreated;
        } catch (ProcessFailedException $e) {
            throw new MockException(
                sprintf(
                    'Error to generate mock file with markdown-pp: %s, maybe markdown-pp is not installed',
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * @return Finder
     */
    protected function getFilesToMock()
    {
        return $this->getFiles($this->mdPath, 'index.apib');
    }

    /**
     * @return Finder
     */
    protected function getFilesToDoc()
    {
        return $this->getFiles($this->mdPath, 'out.apib');
    }

    /**
     * @param string $path
     * @param string $filter
     * @return Finder
     */
    protected function getFiles($path, $filter)
    {
        $finder = new Finder();
        $files = $finder
            ->files()
            ->ignoreDotFiles(true)
            ->filter(function (SplFileInfo $file) use ($filter) {
                if ($file->getFilename() != $filter) {
                    return false;
                }
                return true;
            })
            ->in($path)->files();

        return $files;
    }

    protected function renderFilesInfo()
    {
        $this->output->writeln('<info>Files generated:</info>');

        $this->output->writeln('<fg=black;bg=cyan>Mock Files:</>');
        foreach ($this->compiledFiles as $nameFile) {
            $this->output->writeln($nameFile);
        }

        $this->output->writeln('<fg=black;bg=cyan>Doc Files:</>');
        foreach ($this->docCreatedFiles as $nameFile) {
            $this->output->writeln($nameFile);
        }
    }

    /**
     * @return bool
     */
    protected function hasGeneratedFiles()
    {
        return !empty($this->docCreatedFiles) && !empty($this->compiledFiles);
    }
}
