<?php
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Session\Debug;

use Framework\Session\Debug\SessionCollection;
use PHPUnit\Framework\TestCase;

final class SessionCollectionTest extends TestCase
{
    protected SessionCollection $collection;

    protected function setUp() : void
    {
        $this->collection = new SessionCollection('Session');
    }

    public function testIcon() : void
    {
        self::assertStringStartsWith('<svg ', $this->collection->getIcon());
    }
}
