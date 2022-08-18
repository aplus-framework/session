Session
=======

.. image:: image.png
    :alt: Aplus Framework Session Library

Aplus Framework Session Library.

- `Installation`_
- `Getting Started`_
- `Managing Data`_
- `Temporary Data`_
- `Flash Data`_
- `Save Handlers`_
- `Conclusion`_

Installation
------------

The installation of this library can be done with Composer:

.. code-block::

    composer require aplus/session

Getting Started
---------------

The Session class has methods that facilitate session management.

.. code-block:: php

    use Framework\Session\Session;
    
    $session = new Session();

    // Start the session
    $session->start();

    // Check if the session is active
    $active = $session->isActive();

    // Regenerate the session id
    $session->regenerateId();

    // Destroy the session
    $session->destroy();
    $session->destroyCookie();

    // Stop the session, write and close
    $session->stop();

To make sure the session is active you can use the ``activate`` method:

.. code-block:: php

    $session->activate();

Options
^^^^^^^

Options can be passed through the Session class constructor:

.. code-block:: php

    $session = new Session([
        'name' => 'session_id'
    ]);

or by the ``start`` method:

.. code-block:: php

    $session = new Session();
    $session->start([
        'name' => 'session_id'
    ]);

Auto Regenerate ID
##################

It is possible to auto-regenerate the session id by passing the following
options:

- ``auto_regenerate_maxlifetime`` to set the maximum file lifetime and 
- ``auto_regenerate_destroy`` to destroy the old session file.

.. code-block:: php

    $session = new Session([
        'auto_regenerate_maxlifetime' => 7200,
        'auto_regenerate_destroy' => true,
    ]));

Managing Data
-------------

Data manipulation can be performed with the ``get`` and ``set`` methods or by
calling the properties directly using the magic methods:

.. code-block:: php

    // Set user_id as 1
    $session->set('user_id', 1);

    // Set user_id as 1 using magic setter
    $session->user_id = 1;

    // Get the value of user_id
    $uid = $session->get('user_id'); // 1

    // Get the value of user_id using magic getter
    $uid = $session->user_id; // 1

Multiple Items at Once
^^^^^^^^^^^^^^^^^^^^^^

Multiple items can be handled at once:

.. code-block:: php

    $session->setMulti([
        'user_id' => 1,
        'active' => true,
    ]);

    // Get an array with the two keys
    $data = $session->getMulti([
        'user_id',
        'active',
    ]);

Abort
^^^^^

If necessary, you can abort the current session's modifications by returning to
the previous one using the ``abort`` method:

.. code-block:: php

    $session->abort();

Session ID
^^^^^^^^^^

The session id can be obtained through the ``id`` method:

.. code-block:: php

    $id = $session->id();

and also set as follows:

.. code-block:: php

    $oldId = $session->id('foo');

Getting All Items
^^^^^^^^^^^^^^^^^

Using the ``getAll`` method, you can get all the items in the session:

.. code-block:: php

    $data = $session->getAll();

With the ``has`` method, you can check if there is an item with a certain key:

.. code-block:: php

    // Check if user_id key exists
    $exists = $session->has('user_id'); // bool

Removing Items
^^^^^^^^^^^^^^

Item removal can be performed individually or multiple at once:

.. code-block:: php

    // Remove user_id
    $session->remove('user_id'); 

    // Remove 'active' and 'foo'
    $session->removeMulti([ 
        'active',
        'foo',
    ]);

Temporary Data
--------------

Temporary data are items saved with a TTL (Time To Live) in seconds of how long
the item will be in the session.

.. code-block:: php

    // Set 'message' for 15 seconds
    $session->setTemp('message', 'Hello!', 15); 

    // Get 'message' value or null if expired
    $msg = $session->getTemp('message');

Flash Data
----------

Flash data are items to be used only for the next request.

.. code-block:: php

    // Set 'message' for the next request
    $session->setFlash('message', 'Hi, John!');

    // Get 'message' value or null if expired
    $session->getFlash('message');

Expired Flash and Temp data are automatically removed when the session starts.

Save Handlers
-------------

Save Handlers make it possible to store session data in different ways.

Save Handlers are classes that can be set in the second argument of the Session
class:

.. code-block:: php

    use Framework\Session\Session;

    $session = new Session($options, $saveHandler);

These are the Save Handlers available by default:

Database Handler
^^^^^^^^^^^^^^^^

Allows you to store session data in a database.

.. code-block:: php

    use Framework\Session\SaveHandlers\DatabaseHandler;

    $saveHandler = new DatabaseHandler($configs);

These are the DatabaseHandler configs:

.. code-block:: php

    $configs = [
        // The name of the table used for sessions
        'table' => 'Sessions',
        // The maxlifetime used for locking
        'maxlifetime' => null, // Null to use the ini value of session.gc_maxlifetime
        // The custom column names as values
        'columns' => [
            'id' => 'id',
            'data' => 'data',
            'timestamp' => 'timestamp',
            'ip' => 'ip',
            'ua' => 'ua',
            'ua' => 'ua',
            'user_id' => 'user_id',
        ],
        // Match IP?
        'match_ip' => false,
        // Match User-Agent?
        'match_ua' => false,
        // Independent of match_ip, save the initial IP in the ip column?
        'save_ip' => false,
        // Independent of match_ua, save the initial User-Agent in the ua column?
        'save_ua' => false,
        // Save the user_id?
        'save_user_id' => false,
    ];

Note that the database connection configs must also be set.

Database Instance
#################

It is also possible to pass an instance of the Database class directly, as in
the example below:

.. code-block:: php

    use Framework\Database\Database;
    use Framework\Session\SaveHandlers\DatabaseHandler;

    $database = new Database('root', 'pass', 'app');
    $saveHandler = new DatabaseHandler();
    $saveHandler->setDatabase($database);

Files Handler
^^^^^^^^^^^^^

Allows you to store session data as files in a directory.

.. code-block:: php

    use Framework\Session\SaveHandlers\FilesHandler;

    $saveHandler = new FilesHandler($configs);

These are the FilesHandler configs:

.. code-block:: php

    $configs = [
        // The directory path where the session files will be saved
        'directory' => '',
        // A custom directory name inside the `directory` path
        'prefix' => '',
        // Match IP?
        'match_ip' => false,
        // Match User-Agent?
        'match_ua' => false,
    ];

Memcached Handler
^^^^^^^^^^^^^^^^^

Allows you to store session data on Memcached servers.

.. code-block:: php

    use Framework\Session\SaveHandlers\MemcachedHandler;

    $saveHandler = new MemcachedHandler($configs);

These are the MemcachedHandler configs:

.. code-block:: php

    $configs = [
        // A custom prefix prepended in the keys
        'prefix' => '',
        // A list of Memcached servers
        'servers' => [
            [
                'host' => '127.0.0.1', // host always is required
                'port' => 11211, // port is optional, default to 11211
                'weight' => 0, // weight is optional, default to 0
            ],
        ],
        // An associative array of Memcached::OPT_* constants
        'options' => [
            Memcached::OPT_BINARY_PROTOCOL => true,
        ],
        // Maximum attempts to try lock a session id
        'lock_attempts' => 60,
        // Interval between the lock attempts in microseconds
        'lock_sleep' => 1_000_000,
        // TTL to the lock (valid for the current session only)
        'lock_ttl' => 600,
        // The maxlifetime (TTL) used for cache item expiration
        'maxlifetime' => null, // Null to use the ini value of session.gc_maxlifetime
        // Match IP?
        'match_ip' => false,
        // Match User-Agent?
        'match_ua' => false,
    ];

Memcached Instance
##################

It is also possible to pass an instance of the Memcached class directly, as in
the example below:

.. code-block:: php

    use Framework\Session\SaveHandlers\MemcachedHandler;

    $memcached = new Memcached();
    $saveHandler = new MemcachedHandler();
    $saveHandler->setMemcached($memcached);

Redis Handler
^^^^^^^^^^^^^

Allows you to store session data on a Redis server.

.. code-block:: php

    use Framework\Session\SaveHandlers\RedisHandler;

    $saveHandler = new RedisHandler($configs);

These are the RedisHandler configs:

.. code-block:: php

    $configs = [
        // A custom prefix prepended in the keys
        'prefix' => '',
        // The Redis host
        'host' => '127.0.0.1',
        // The Redis host port
        'port' => 6379,
        // The connection timeout
        'timeout' => 0.0,
        // Optional auth password
        'password' => null,
        // Optional database to select
        'database' => null,
        // Maximum attempts to try lock a session id
        'lock_attempts' => 60,
        // Interval between the lock attempts in microseconds
        'lock_sleep' => 1_000_000,
        // TTL to the lock (valid for the current session only)
        'lock_ttl' => 600,
        // The maxlifetime (TTL) used for cache item expiration
        'maxlifetime' => null, // Null to use the ini value of session.gc_maxlifetime
        // Match IP?
        'match_ip' => false,
        // Match User-Agent?
        'match_ua' => false,
    ];

Redis Instance
##############

It is also possible to pass an instance of the Redis class directly, as in the
example below:

.. code-block:: php

    use Framework\Session\SaveHandlers\RedisHandler;

    $redis = new Redis();
    $saveHandler = new RedisHandler();
    $saveHandler->setRedis($redis);

Conclusion
----------

Aplus Session Library is an easy-to-use tool for, beginners and experienced, PHP developers. 
It is perfect for saving user sessions that can be easily scalable. 
The more you use it, the more you will learn.

.. note::
    Did you find something wrong? 
    Be sure to let us know about it with an
    `issue <https://gitlab.com/aplus-framework/libraries/session/issues>`_. 
    Thank you!
