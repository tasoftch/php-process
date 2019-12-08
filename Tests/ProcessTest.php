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

/**
 * ProcessTest.php
 * ikarus-sps
 *
 * Created on 2019-12-06 16:29 by thomas
 */

use TASoft\Util\Process;
use PHPUnit\Framework\TestCase;

class ProcessTest extends TestCase
{
    private $check;

    private function readProcessIDs() {
        exec("ps aux | grep php", $output);
        $processes = [];
        foreach($output as $line) {
            if(preg_match("/^\w+\s+(\d+)/i", $line, $ms)) {
                $processes[] = $ms[1] * 1;
            }
        }

        return $processes;
    }

    public function testProcessForkWaitChild() {
        $proc = new Process(function() {
            sleep(1);
            // Maintains Hello!
            echo $this->check;

            // Has no effect to the parent process!
            $this->check = 'Thomas';
        });

        $this->check = "Hello";
        $proc->run();
        $this->check = "World";

        $processes = $this->readProcessIDs();

        $this->assertContains( $proc->getProcessID(), $processes );
        $this->assertContains( $proc->getChildProcessID(), $processes );

        $date = new DateTime();

        $proc->wait();

        $processes = $this->readProcessIDs();


        $this->assertContains( $proc->getProcessID(), $processes );
        $this->assertNotContains( $proc->getChildProcessID(), $processes );

        $diff = $date->diff( new DateTime() );
        $this->assertEquals(1, $diff->s);
        $this->assertGreaterThan(0, $diff->f);

        // Because the child task does not output in the same process memory scope.
        $this->assertEmpty($this->getActualOutput());

        $this->assertEquals("World", $this->check);
    }

    public function testProcessWithoutWait() {
        $process = new Process(function(){});
        $process->run();

        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertContains( $process->getChildProcessID(), $processes );

        // $process->wait();
        usleep(100000);

        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertNotContains( $process->getChildProcessID(), $processes );
    }

    public function testProcessDataCommunication() {
        // Please note that you should not pass the parent process into the closure:
        $process = new Process(function() use (&$process) {
            // Don't do this!
            // Because $process is here a copy of the parent process!
        });

        $process = new Process(function(Process $proc) {
            $value = $proc->receiveData( true );
            $value *= 10;
            $proc->sendData($value);
        });

        $process->run();

        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertContains( $process->getChildProcessID(), $processes );

        usleep(100000);

        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertContains( $process->getChildProcessID(), $processes );

        $process->sendData(12);
        $value = $process->receiveData(true);

        $process->wait();

        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertNotContains( $process->getChildProcessID(), $processes );

        $this->assertEquals(120, $value);
    }

    public function testKillChildProcessFromParent() {
        $process = new Process(function(){
            sleep(10);
        });

        $process->run();
        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertContains( $process->getChildProcessID(), $processes );

        $process->kill();
        $process->wait();

        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertNotContains( $process->getChildProcessID(), $processes );
    }

    public function testKillProcessFromChild() {
        $process = new Process(function(Process $proc){
            sleep(1);
            $proc->kill();
            sleep(1);
        });

        $date = new DateTime();

        $process->run();
        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertContains( $process->getChildProcessID(), $processes );

        $process->wait();

        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertNotContains( $process->getChildProcessID(), $processes );

        $diff = $date->diff( new DateTime() );
        $this->assertEquals(1, $diff->s);
        $this->assertGreaterThan(0, $diff->f);
    }

    public function testTrapSignals() {
        $process = new Process(function(){
            // Please note to pack code that you want to trap signals into declare blocks.
            // Also, use as less code as possible in the block, because declaring ticks costs performance!
            declare(ticks=1) {
                sleep(10);
            }
        });

        $process->setTrappedSignals( [SIGINT] );

        $process->run();

        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertContains( $process->getChildProcessID(), $processes );

        $process->kill();
        $status = $process->wait();

        $this->assertEquals(SIGINT, pcntl_wexitstatus( $status ));

        $processes = $this->readProcessIDs();

        $this->assertContains( $process->getProcessID(), $processes );
        $this->assertNotContains( $process->getChildProcessID(), $processes );
    }
}
