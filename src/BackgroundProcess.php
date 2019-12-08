<?php
/**
 * Copyright (c) 2019 TASoft Applications, Th. Abplanalp <info@tasoft.ch>
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
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace TASoft\Util;

/**
 * The background process triggers a shell script in background without blocking the main script.
 * @package TASoft\Util
 */
class BackgroundProcess
{
    /** @var string */
    private $command;
    /** @var string */
    private $outputFile = '/dev/null';
    private $processID = 0;

    /**
     * BackgroundProcess constructor.
     * @param string $command
     */
    public function __construct(string $command)
    {
        $this->command = $command;
    }

    /**
     * Runs the process in background
     */
    public function run() {
        $tmp = tempnam("./", ".pid");
        $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $this->getCommand() , escapeshellarg($this->getOutputFile()), escapeshellarg( $tmp ));
        exec($cmd);
        $this->processID = trim(file_get_contents($tmp)) * 1;
        unlink($tmp);
    }

    /**
     * Returns true, if the background process is still running
     *
     * @return bool
     */
    public function isRunning(): bool {
        if($this->processID) {
            exec("ps $this->processID", $output);
            return count($output) > 1 ? true : false;
        }
        return false;
    }

    /**
     * Kills the background process
     *
     * @param int $signal
     */
    public function kill($signal = SIGINT) {
        if($this->isRunning()) {
            posix_kill( $this->getProcessID(), $signal );
        }
    }


    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @return string
     */
    public function getOutputFile(): string
    {
        return $this->outputFile;
    }

    /**
     * @param string|null $outputFile
     */
    public function setOutputFile(string $outputFile): void
    {
        $this->outputFile = $outputFile;
    }

    /**
     * @return int
     */
    public function getProcessID(): int
    {
        return $this->processID;
    }
}