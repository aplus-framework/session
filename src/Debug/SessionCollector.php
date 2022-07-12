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

use Framework\Debug\Collector;
use Framework\Debug\Debugger;
use Framework\Helpers\ArraySimple;
use Framework\Session\SaveHandler;
use Framework\Session\SaveHandlers\DatabaseHandler;
use Framework\Session\SaveHandlers\FilesHandler;
use Framework\Session\SaveHandlers\MemcachedHandler;
use Framework\Session\SaveHandlers\RedisHandler;
use Framework\Session\Session;

/**
 * Class SessionCollector.
 *
 * @package session
 */
class SessionCollector extends Collector
{
    protected Session $session;
    /**
     * @var array<string>
     */
    protected array $options = [];
    protected SaveHandler $saveHandler;

    public function setSession(Session $session) : static
    {
        $this->session = $session;
        return $this;
    }

    /**
     * @param array<string> $options
     *
     * @return static
     */
    public function setOptions(array $options) : static
    {
        $this->options = $options;
        return $this;
    }

    public function setSaveHandler(SaveHandler $handler) : static
    {
        $this->saveHandler = $handler;
        return $this;
    }

    public function getContents() : string
    {
        if ( ! isset($this->session)) {
            return '<p>No Session instance has been set on this collector.</p>';
        }
        if ( ! $this->session->isActive()) {
            return '<p>Session is inactive.</p>';
        }
        \ob_start(); ?>
        <p><strong>Name:</strong> <?= \ini_get('session.name') ?></p>
        <p><strong>Id:</strong> <?= $this->session->id() ?></p>
        <h1>Data</h1>
        <?= $this->renderData() ?>
        <h1>Flash</h1>
        <?= $this->renderFlash() ?>
        <h1>Temp</h1>
        <?= $this->renderTemp() ?>
        <h1>Save Handler</h1>
        <?= $this->renderSaveHandler() ?>
        <h1>Cookie Params</h1>
        <?= $this->renderCookieParams() ?>
        <h1>Auto Regenerate Id</h1>
        <?php
        echo $this->renderAutoRegenerateId();
        return \ob_get_clean(); // @phpstan-ignore-line
    }

    protected function renderData() : string
    {
        $data = $this->session->getAll();
        unset($data['$']);
        if (empty($data)) {
            return '<p>No data.</p>';
        }
        \ksort($data);
        \ob_start(); ?>
        <table>
            <thead>
            <tr>
                <th>Key</th>
                <th>Value</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $key => $value): ?>
                <tr>
                    <td><?= \htmlentities((string) $key) ?></td>
                    <td><pre><code class="language-php"><?=
                                \htmlentities(Debugger::makeDebugValue($value))
                ?></code></pre>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?php
        return \ob_get_clean(); // @phpstan-ignore-line
    }

    protected function renderFlash() : string
    {
        $old = $this->renderFlashOld();
        $new = $this->renderFlashNew();
        if ($old === '' && $new === '') {
            return '<p>No flash data.</p>';
        }
        return $old . $new;
    }

    protected function renderFlashOld() : string
    {
        $data = ArraySimple::value('$[flash][old]', $this->session->getAll());
        if (empty($data)) {
            return '';
        }
        \ob_start(); ?>
        <h2>Old</h2>
        <p>Flash data available in the current request.</p>
        <table>
            <thead>
            <tr>
                <th>Key</th>
                <th>Value</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $key => $value): ?>
                <tr>
                    <td><?= \htmlentities($key) ?></td>
                    <td><pre><code class="language-php"><?=
                                \htmlentities(Debugger::makeDebugValue($value))
                ?></code></pre>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?php
        return \ob_get_clean(); // @phpstan-ignore-line
    }

    protected function renderFlashNew() : string
    {
        $data = ArraySimple::value('$[flash][new]', $this->session->getAll());
        if (empty($data)) {
            return '';
        }
        \ob_start(); ?>
        <h2>New</h2>
        <p>Flash data available in the next request.</p>
        <table>
            <thead>
            <tr>
                <th>Key</th>
                <th>Value</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $key => $value): ?>
                <tr>
                    <td><?= \htmlentities($key) ?></td>
                    <td><pre><code class="language-php"><?=
                                \htmlentities(Debugger::makeDebugValue($value))
                ?></code></pre>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?php
        return \ob_get_clean(); // @phpstan-ignore-line
    }

    protected function renderTemp() : string
    {
        $data = ArraySimple::value('$[temp]', $this->session->getAll());
        if (empty($data)) {
            return '<p>No temp data.</p>';
        }
        \ob_start(); ?>
        <table>
            <thead>
            <tr>
                <th>Key</th>
                <th>Value</th>
                <th>TTL</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $key => $value): ?>
                <tr>
                    <td><?= \htmlentities($key) ?></td>
                    <td><pre><code class="language-php"><?=
                                \htmlentities(Debugger::makeDebugValue($value))
                ?></code></pre>
                    </td>
                    <td><?= \date('Y-m-d H:i:s', $value['ttl']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?php
        return \ob_get_clean(); // @phpstan-ignore-line
    }

    protected function renderSaveHandler() : string
    {
        \ob_start(); ?>
        <p><strong>Save Handler:</strong> <?= \ini_get('session.save_handler') ?></p>
        <p><strong>Serializer:</strong> <?= \ini_get('session.serialize_handler') ?></p>
        <?php
        if (isset($this->saveHandler)): ?>
            <table>
                <tbody>
                <tr>
                    <th>Class</th>
                    <td><?= $this->saveHandler::class ?></td>
                </tr>
                <?php foreach ($this->getSaveHandlerConfigs() as $key => $value): ?>
                    <?= $this->renderSaveHandlerTableRows($key, $value) ?>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php
        endif;
        return \ob_get_clean(); // @phpstan-ignore-line
    }

    protected function renderSaveHandlerTableRows(string $key, mixed $value) : string
    {
        \ob_start(); ?>
        <?php
        if (\is_array($value)):
            $count = \count($value); ?>
            <tr>
                <th rowspan="<?= $count ?>"><?= $key ?></th>
                <td>
                    <table>
                        <?php foreach ($value[\array_key_first($value)] as $k => $v): ?>
                            <tr>
                                <th><?= \htmlentities($k) ?></th>
                                <td><?= \htmlentities((string) $v) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </table>
                </td>
            </tr>
            <?php
            for ($i = 1; $i < $count; $i++): ?>
                <tr>
                    <td>
                        <table>
                            <?php foreach ($value[$i] as $k => $v): ?>
                                <tr>
                                    <th><?= \htmlentities($k) ?></th>
                                    <td><?= \htmlentities((string) $v) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </table>
                    </td>
                </tr>
            <?php
            endfor;
            return \ob_get_clean(); // @phpstan-ignore-line
        endif; ?>
        <tr>
            <th><?= \htmlentities($key) ?></th>
            <td><?= \htmlentities((string) $value) ?></td>
        </tr>
        <?php
        return \ob_get_clean(); // @phpstan-ignore-line
    }

    protected function renderCookieParams() : string
    {
        $params = \session_get_cookie_params();
        \ob_start(); ?>
        <table>
            <thead>
            <tr>
                <th>Lifetime</th>
                <th>Path</th>
                <th>Domain</th>
                <th>Is Secure</th>
                <th>Is HTTP Only</th>
                <th>SameSite</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><?= $params['lifetime'] ?></td>
                <td><?= $params['path'] ?></td>
                <td><?= $params['domain'] ?></td>
                <td><?= $params['secure'] ? 'Yes' : 'No' ?></td>
                <td><?= $params['httponly'] ? 'Yes' : 'No' ?></td>
                <td><?= $params['samesite'] ?></td>
            </tr>
            </tbody>
        </table>
        <?php
        return \ob_get_clean(); // @phpstan-ignore-line
    }

    protected function renderAutoRegenerateId() : string
    {
        if ($this->options['auto_regenerate_maxlifetime'] < 1) {
            return '<p>Auto regenerate id is inactive.</p>';
        }
        $maxlifetime = (int) $this->options['auto_regenerate_maxlifetime'];
        $regeneratedAt = ArraySimple::value('$[regenerated_at]', $this->session->getAll());
        $nextRegeneration = $regeneratedAt + $maxlifetime;
        \ob_start(); ?>
        <table>
            <thead>
            <tr>
                <th>Maxlifetime</th>
                <th>Destroy</th>
                <th>Regenerated At</th>
                <th>Next Regeneration</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><?= $maxlifetime ?></td>
                <td><?= $this->options['auto_regenerate_destroy'] ? 'Yes' : 'No' ?></td>
                <td><?= $regeneratedAt ? \date('Y-m-d H:i:s', $regeneratedAt) : '' ?></td>
                <td><?= \date('Y-m-d H:i:s', $nextRegeneration) ?></td>
            </tr>
            </tbody>
        </table>
        <?php
        return \ob_get_clean(); // @phpstan-ignore-line
    }

    /**
     * @return array<string,mixed>
     */
    protected function getSaveHandlerConfigs() : array
    {
        $config = $this->saveHandler->getConfig();
        if ($this->saveHandler instanceof FilesHandler) {
            return [
                'Directory' => $config['directory'],
                'Prefix' => $config['prefix'],
                'Match IP' => $config['match_ip'] ? 'Yes' : 'No',
                'Match User-Agent' => $config['match_ua'] ? 'Yes' : 'No',
            ];
        }
        if ($this->saveHandler instanceof MemcachedHandler) {
            $servers = [];
            foreach ($config['servers'] as $server) {
                $servers[] = [
                    'Host' => $server['host'],
                    'Port' => $server['port'] ?? 11211,
                    'Weight' => $server['weight'] ?? 0,
                ];
            }
            return [
                'Servers' => $servers,
                'Prefix' => $config['prefix'],
                'Lock Attempts' => $config['lock_attempts'],
                'Lock Sleep' => $config['lock_sleep'],
                'Lock TTL' => $config['lock_ttl'],
                'Maxlifetime' => $config['maxlifetime'] ?? \ini_get('session.gc_maxlifetime'),
                'Match IP' => $config['match_ip'] ? 'Yes' : 'No',
                'Match User-Agent' => $config['match_ua'] ? 'Yes' : 'No',
            ];
        }
        if ($this->saveHandler instanceof RedisHandler) {
            return [
                'Host' => $config['host'],
                'Port' => $config['port'],
                'Timeout' => $config['timeout'],
                'Database' => $config['database'],
                'Prefix' => $config['prefix'],
                'Lock Attempts' => $config['lock_attempts'],
                'Lock Sleep' => $config['lock_sleep'],
                'Lock TTL' => $config['lock_ttl'],
                'Maxlifetime' => $config['maxlifetime'] ?? \ini_get('session.gc_maxlifetime'),
                'Match IP' => $config['match_ip'] ? 'Yes' : 'No',
                'Match User-Agent' => $config['match_ua'] ? 'Yes' : 'No',
            ];
        }
        if ($this->saveHandler instanceof DatabaseHandler) {
            return [
                'Host' => $config['host'],
                'Schema' => $config['schema'],
                'Table' => $config['table'],
                'Columns' => [
                    [
                        'id' => $config['columns']['id'],
                        'data' => $config['columns']['data'],
                        'timestamp' => $config['columns']['timestamp'],
                        'ip' => $config['columns']['ip'],
                        'ua' => $config['columns']['ua'],
                        'user_id' => $config['columns']['user_id'],
                    ],
                ],
                'Maxlifetime' => $config['maxlifetime'] ?? \ini_get('session.gc_maxlifetime'),
                'Match IP' => $config['match_ip'] ? 'Yes' : 'No',
                'Match User-Agent' => $config['match_ua'] ? 'Yes' : 'No',
                'Save IP' => $config['save_ip'] ? 'Yes' : 'No',
                'Save User-Agent' => $config['save_ua'] ? 'Yes' : 'No',
                'Save User Id' => $config['save_user_id'] ? 'Yes' : 'No',
            ];
        }
        return [];
    }
}
