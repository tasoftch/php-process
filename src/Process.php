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


use TASoft\Util\Exception\ProcessException;
use TASoft\Util\Exception\SignalException;

class Process
{
    /** @var callable */
    private $callback;
    /** @var int */
    private $processID;
    /** @var int Only for main processes to know their child processes */
    private $_childProcessID = 0;

    /** @var bool */
    private $mainProcess = true;
    /** @var bool  */
    private $running = false;

    /** @var Pipe */
    private $toParentPipe;
    /** @var Pipe */
    private $toChildPipe;

    private $trappedSignals = [];

    /**
     * Process constructor.
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->processID = getmypid();
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * @return int
     */
    public function getProcessID(): int
    {
        return $this->processID;
    }

    /**
     * @return bool
     */
    public function isMainProcess(): bool
    {
        return $this->mainProcess;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * @return int
     */
    public function getChildProcessID(): int
    {
        return $this->_childProcessID;
    }

    /**
     * Runs the process.
     *
     * Calling this method forks the process and establish the communication tunnel between them.
     *
     * @param bool $processAsArgument
     * @param mixed ...$arguments
     */
    public function run(bool $processAsArgument = true, ...$arguments) {
        if($this->isMainProcess() && !$this->isRunning()) {
            // Starts the process
            $this->toParentPipe = new Pipe();
            $this->toChildPipe = new Pipe();

            $this->running = true;

            $pid = pcntl_fork();
            if($pid == -1)
                throw new ProcessException("Could not fork process", 300);

            if($pid) {
                $this->_childProcessID = $pid;
            } else {
                $this->mainProcess = false;
                $this->processID = getmypid();

                if($signals = $this->getTrappedSignals()) {
                    foreach($signals as $signal) {
                        pcntl_signal($signal, function($signo) {
                            $e = new SignalException("Signal triggered", -1);
                            $e->setSignal($signo);
                            throw $e;
                        });
                    }
                }
                try {
                    if($processAsArgument)
                        array_unshift($arguments, $this);
                    call_user_func_array($this->getCallback(), $arguments);
                } catch (SignalException $exception) {
                    switch ($exception->getSignal()) {
                        case SIGINT:
                        case SIGTERM:
                            exit( $exception->getSignal() );
                    }
                }

                exit();
            }
        }
    }

    /**
     * Kills the child process.
     * Its not important who (parent or child process) calls this method, it will only kill the child process.
     * @param int $signal
     */
    public function kill($signal = SIGINT) {
        if($this->isRunning()) {
            if($this->isMainProcess())
                posix_kill($this->_childProcessID, $signal);
            else
                posix_kill($this->processID, $signal);
        }
    }

    /**
     * Waits for the child process to be done.
     *
     * If the child process calls this method, nothing happens.
     */
    public function wait() {
        if($this->isMainProcess()) {
            pcntl_waitpid( $this->_childProcessID, $status );
            return $status;
        }
        return 0;
    }

    /**
     * Sends data to the other process.
     * If the child process calls this method, it will send data to the parent process and vice versa.
     *
     * @param $data
     */
    public function sendData($data) {
        if($this->isMainProcess())
            $this->toChildPipe->sendData($data);
        else
            $this->toParentPipe->sendData($data);
    }

    /**
     * Checks if there was data sent by the child or parent process.
     *
     * @param bool $blockThread
     * @return mixed
     */
    public function receiveData(bool $blockThread = false) {
        if($this->isMainProcess()) {
            return $this->toParentPipe->receiveData($blockThread);
        } else {
            return $this->toChildPipe->receiveData($blockThread);
        }
    }

    /**
     * @return array
     */
    public function getTrappedSignals(): array
    {
        return $this->trappedSignals;
    }

    /**
     * @param array $trappedSignals
     */
    public function setTrappedSignals(array $trappedSignals): void
    {
        if(!$this->isRunning())
            $this->trappedSignals = $trappedSignals;
    }
}