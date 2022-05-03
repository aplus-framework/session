<?php
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Session\SaveHandlers;

/**
 * Class FilesHandlerMatchTest.
 *
 * @runTestsInSeparateProcesses
 */
final class FilesHandlerMatchTest extends FilesHandlerTest
{
    protected array $config = [
        'match_ip' => true,
        'match_ua' => true,
    ];
}
