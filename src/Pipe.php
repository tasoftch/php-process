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


use TASoft\Util\Exception\PipeException;

/**
 * The pipe allows communication between forked processes, because they don't share the same memory, direct accessing
 * each other is not possible.
 * The pipe MUST be created before forking a process, and then pass it to both.
 * @package Ikarus
 */
class Pipe
{
    const BUFFER_SIZE = 2048;

    private $receiver;
    private $sender;

    /**
     * Pipe constructor.
     */
    public function __construct()
    {
        if(socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $fd))
            list($this->receiver, $this->sender) = $fd;
        else {
            $e = new PipeException("Could not create communication sockets");
            $e->setPipe($this);
            throw $e;
        }
    }

    /**
     * Sends data to the other end of pipe, means another process
     * @param $data
     */
    public function sendData($data) {
        $data = serialize($data);
        if(socket_write($this->sender, $data, strlen($data)))
            return;

        $err = socket_last_error($this->sender);
        $e = new PipeException(socket_strerror($err), $err);
        $e->setPipe($this);
        throw $e;
    }

    /**
     * Receives data from another process. This method blocks until data was sent.
     *
     * @param bool $blockThread     Blocks the thread until data is available
     * @return mixed
     */
    public function receiveData(bool $blockThread = true) {
        if($blockThread) {
            $reader = function($socket) {
                if(@feof($socket))
                    return NULL;

                $buf = "";
                declare(ticks=1) {
                    while ($out = socket_read($socket, static::BUFFER_SIZE)) {
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
     * Close the sockets now
     */
    public function __destruct()
    {
        socket_close($this->sender);
        socket_close($this->receiver);
    }
}