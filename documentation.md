# Session Library *documentation*

The Session library has methods that facilitate session management.

```php
use Framework\Session\Session;

$session = new Session();
// Start the session
$session->start();
// Chekc if the session is active
$started = $session->isStarted();
// Regenerate the session id
$session->regenerate();
// Destroy the session
$session->destroy();
// Stop the session
$session->stop();
```

## Managing data

```php
// Set user_id as 1
$session->set('user_id', 1);
// Set user_id as 1 using magic setter
$session->user_id = 1;
// Get the value of user_id
$uid = $session->get('user_id'); // 1
// Get the value of user_id using magic getter
$uid = $session->user_id; // 1
```

### Multiple items at once

```php
$session->setMulti([
	'user_id' => 1,
	'active' => true,
]);
// Get an array with the two keys
$data = $session->getMulti([
	'user_id',
	'active',
]);
```

### Getting all items

```php
$data = $session->getAll();
// Check if user_id key exists
$exists = $session->has('user_id'); // true
```

### Removing items

```php
// Remove user_id
$session->remove('user_id'); 
// Remove active and foo
$session->removeMulti([ 
	'active',
	'foo',
]);
// Check if user_id key exists
$exists = $session->has('user_id'); // false
```

## Temporary data

Temporary data are items saved with a TTL (Time To Live) in seconds of how long
the item will be in session.

```php
// Set message for 15 seconds
$session->setTemp('message', 'Hello!', 15); 
// Get the message or null if it has expired
$msg = $session->getTemp('message'); 
```

## Flash data

Flash data are items to be used only for the next request.

```php
// Set message for the next request
$session->setFlash('message', 'Hi, John!');
// Get the message or null if it has expired
$session->getFlash('message');
```

Expired Flash and Temp data are automatically removed at the beginning of each
session.
