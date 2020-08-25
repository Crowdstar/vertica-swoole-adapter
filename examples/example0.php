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
 * Sample code to show how to make a query against the Vertica database server using connection pool.
 */

declare(strict_types=1);

use CrowdStar\VerticaSwooleAdapter\VerticaAdapter;
use CrowdStar\VerticaSwooleAdapter\VerticaProxy;
use Swoole\ConnectionPool;
use Swoole\Coroutine;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Coroutine\run(function () {
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

    /** @var VerticaAdapter $conn */
    $conn = $pool->get();
    $data = $conn->fetchOne($conn->query('SELECT version()'));
    $pool->put($conn);

    echo "Vertica version: ", $data[0]['version'], "\n";
});
