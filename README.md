[![Build Status](https://travis-ci.org/Crowdstar/vertica-swoole-adapter.svg?branch=master)](https://travis-ci.org/Crowdstar/vertica-swoole-adapter)
[![Latest Stable Version](https://poser.pugx.org/Crowdstar/vertica-swoole-adapter/v/stable.svg)](https://packagist.org/packages/crowdstar/vertica-swoole-adapter)
[![Latest Unstable Version](https://poser.pugx.org/Crowdstar/vertica-swoole-adapter/v/unstable.svg)](https://packagist.org/packages/crowdstar/vertica-swoole-adapter)
[![License](https://poser.pugx.org/Crowdstar/vertica-swoole-adapter/license.svg)](https://packagist.org/packages/crowdstar/vertica-swoole-adapter)

# Summary

This library provides a DB layer to communicate to HP Vertica databases for [Swoole](https://github.com/swoole/swoole-src) based applications.

Features supported:

* Connection pool.
* Auto-reconnect.
* Retry with exponential backoff (for failed operations).
* Logging support.

Vertica connections are made through package [skatrych/vertica-php-adapter](https://github.com/skatrych/vertica-php-adapter), which is implemented using ODBC.

This package was derived from our work at [Glu Mobile](https://www.glu.com). It has been used in one of our internal microservices, and ran smoothly for months.

# Installation

```bash
composer require crowdstar/vertica-swoole-adapter
```

# Sample Usage

Following example creates a Vertica connection pool, gets a connection from the pool, makes a database query, then puts
the connection back to the pool:
 
```php
<?php
use CrowdStar\VerticaSwooleAdapter\VerticaAdapter;
use CrowdStar\VerticaSwooleAdapter\VerticaProxy;
use Swoole\ConnectionPool;

$pool = new ConnectionPool(
    function () {
        return new VerticaAdapter($config);
    },
    ConnectionPool::DEFAULT_SIZE,
    VerticaProxy::class
);
/** @var VerticaAdapter $conn */
$conn = $pool->get();
$data = $conn->fetchAll($conn->query($sql)) ?: [];
$pool->put($conn);
?>
```
