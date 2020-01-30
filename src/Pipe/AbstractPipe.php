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

namespace TASoft\Util\Pipe;


use TASoft\Util\Exception\PipeException;

abstract class AbstractPipe implements PipeInterface
{
    const BUFFER_SIZE = 2048;

    /** @var resource */
    protected $sender;
    /** @var resource */
    protected $receiver;

    public function __construct()
    {
        $this->setupPipe($this->sender, $this->receiver);
    }

    public function __destruct()
    {
        $this->cleanupPipe($this->sender, $this->receiver);
    }

    public function sendData($data) {
        $data = serialize($data);
        if(socket_write($this->sender, $data, strlen($data)))
            return;

        $err = socket_last_error($this->sender);
        $e = new PipeException(socket_strerror($err), $err);
        $e->setPipe($this);
        throw $e;
    }

    public function receiveData(bool $blockThread = true) {
        if($blockThread) {
            $reader = function($socket) {
                if(@feof($socket))
                    return NULL;

                $buf = "";
                declare(ticks=1) {
                    pcntl_async_signals(true);
                    while ($out = socket_read($socket, static::BUFFER_SIZE)) {
                        pcntl_signal_dispatch();

                        $buf .= $out;
                        if(strlen($out) < static::BUFFER_SIZE) {
                            break;
                        }
                    }
                }
                return $buf;
            };
        } else {
            $reader = function ($socket) {
                $buffer = "";
                do {
                    socket_recv($socket, $buf, 1024, MSG_DONTWAIT);
                    if ($buf) {
                        $buffer .= $buf;
                    }
                } while ($buf);
                return $buffer;
            };
        }


        $data = $reader($this->receiver);
        return unserialize($data);
    }

    /**
     * Sets up the pipe backends
     *
     * @param $sender
     * @param $receiver
     * @return void
     */
    abstract protected function setupPipe(&$sender, &$receiver);

    /**
     * Cleanup the pipe after usage
     *
     * @param $sender
     * @param $receiver
     * @return void
     */
    abstract protected function cleanupPipe($sender, $receiver);
}