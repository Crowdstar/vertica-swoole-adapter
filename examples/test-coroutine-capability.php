#!/usr/bin/env php
<?php

/**************************************************************************
 * Copyright 2020 Glu Mobile Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *************************************************************************/

/**
 * To check if the Vertica DB adapter is coroutine-friendly or not.
 *
 * Usage:
 *     docker exec -ti $(docker ps -qf "name=app") ./examples/test-coroutine-capability.php
 */

declare(strict_types=1);

use CrowdStar\VerticaSwooleAdapter\VerticaAdapter;
use CrowdStar\VerticaSwooleAdapter\VerticaProxy;
use Swoole\ConnectionPool;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Swoole\Runtime;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Runtime::setHookFlags(SWOOLE_HOOK_ALL);
Coroutine\run(function () {
    $coroutineFriendly = null;
    $pool = new ConnectionPool(
        function () {
            return new VerticaAdapter(
                [
                    'host'     => $_ENV['VERTICA_HOST'],
                    'port'     => $_ENV['VERTICA_PORT'],
                    'user'     => $_ENV['VERTICA_USER'],
                    'password' => $_ENV['VERTICA_PASS'],
                    'database' => $_ENV['VERTICA_DB'],
                ]
            );
        },
        ConnectionPool::DEFAULT_SIZE,
        VerticaProxy::class
    );

    $wg = new WaitGroup();
    $wg->add(2);
    go(function () use ($pool, $wg, &$coroutineFriendly) {
        /** @var VerticaAdapter $conn */
        $conn = $pool->get();
        $conn->fetchOne($conn->query('SELECT ' . rand()));

        // If the Vertica adapter is coroutine-friendly, this assignment statement should be executed after the one in
        // the 2nd coroutine, and have variable $coroutineFriendly finally set to TRUE.
        $coroutineFriendly = true;

        $pool->put($conn);
        $pool->close();
        $wg->done();
    });

    go(function () use ($wg, &$coroutineFriendly) {
        $coroutineFriendly = false;
        $wg->done();
    });

    $wg->wait();

    if ($coroutineFriendly) {
        echo "The Vertica adapter is coroutine-friendly.\n";
    } else {
        echo "The Vertica adapter is not coroutine-friendly.\n";
    }
});
