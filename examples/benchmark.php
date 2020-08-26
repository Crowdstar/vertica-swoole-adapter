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
 * Benchmark script to show the benefits of using connection pool.
 *
 * Usage:
 *     docker exec -ti $(docker ps -qf "name=app") ./examples/benchmark.php [Number of Queries] [Pool Size]
 *     e.g.,
 *     docker exec -ti $(docker ps -qf "name=app") ./examples/benchmark.php
 *     docker exec -ti $(docker ps -qf "name=app") ./examples/benchmark.php  500  6
 *     docker exec -ti $(docker ps -qf "name=app") ./examples/benchmark.php 1000 12
 */

declare(strict_types=1);

use CrowdStar\VerticaSwooleAdapter\VerticaAdapter;
use CrowdStar\VerticaSwooleAdapter\VerticaProxy;
use Swoole\ConnectionPool;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Coroutine\run(function () {
    $numOfQueries = (int) ($argv[1] ?? 1000);
    $poolSize     = (int) ($argv[2] ?? 12);

    $start = microtime(true);
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
        $poolSize,
        VerticaProxy::class
    );
    $wg = new WaitGroup();
    $wg->add($numOfQueries);
    for ($i = 0; $i < $numOfQueries; $i++) {
        go(function () use ($pool, $wg) {
            /** @var VerticaAdapter $conn */
            $conn = $pool->get();
            $conn->fetchOne($conn->query('SELECT ' . rand()));
            $pool->put($conn);
            echo '.';
            $wg->done();
        });
    }
    $wg->wait();
    $pool->close();
    $time = microtime(true) - $start;
    echo "\nUse connection pool: {$time} seconds. ({$numOfQueries} queries, with a pool of size {$poolSize})\n";

    $start = microtime(true);
    for ($i = 0; $i < $numOfQueries; $i++) {
        $conn = new VerticaAdapter(
            [
                'host'     => $_ENV['VERTICA_HOST'],
                'port'     => $_ENV['VERTICA_PORT'],
                'user'     => $_ENV['VERTICA_USER'],
                'password' => $_ENV['VERTICA_PASS'],
                'database' => $_ENV['VERTICA_DB'],
            ]
        );
        $conn->fetchOne($conn->query('SELECT ' . rand()));
        $conn->closeConnection();
        unset($conn);
        echo '.';
    }
    $time = microtime(true) - $start;
    echo "\nBlocking queries only: {$time} seconds. ({$numOfQueries} queries)\n";
});
