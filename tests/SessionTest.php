<?php
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Session;

use Framework\Session\Session;

/**
 * Class SessionTest.
 *
 * @runTestsInSeparateProcesses
 */
final class SessionTest extends TestCase
{
    public function setUp() : void
    {
        if (\is_dir($this->getSavePath())) {
            \exec('chmod -R 777 ' . $this->getSavePath());
            \exec('rm -rf ' . $this->getSavePath());
        }
        \exec('mkdir ' . $this->getSavePath());
        $this->session = new Session([
            'name' => 'SessionName',
            'save_path' => $this->getSavePath(),
        ]);
        $this->session->start();
    }

    protected function getSavePath() : string
    {
        return \sys_get_temp_dir() . '/sessions';
    }

    public function testStartFail() : void
    {
        $this->session->stop();
        \exec('chmod 644 ' . $this->getSavePath());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Session could not be started:'
            . ' session_start(): Failed to read session data:'
            . ' files (path: ' . $this->getSavePath() . ')'
        );
        $this->session->start();
    }

    public function testGcFail() : void
    {
        $this->session->stop();
        self::assertFalse($this->session->gc());
        self::assertSame(
            'session_gc(): Session cannot be garbage collected when there is no active session',
            \error_get_last()['message']
        );
    }
}
