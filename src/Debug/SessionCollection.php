<?php declare(strict_types=1);
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Framework\Session\Debug;

use Framework\Debug\Collection;

/**
 * Class SessionCollection.
 *
 * @package session
 */
class SessionCollection extends Collection
{
    protected string $iconPath = __DIR__ . '/icons/session.svg';
}
