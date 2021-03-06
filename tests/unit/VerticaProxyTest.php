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

declare(strict_types=1);

namespace CrowdStar\Tests\VerticaSwooleAdapter;

use CrowdStar\VerticaSwooleAdapter\VerticaAdapter;
use CrowdStar\VerticaSwooleAdapter\VerticaProxy;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Swoole\ConnectionPool;
use Swoole\Coroutine;

/**
 * Class VerticaProxyTest
 * @package CrowdStar\Tests\VerticaSwooleAdapter
 */
class VerticaProxyTest extends TestCase
{
    /**
     * @covers VerticaAdapter
     * @covers VerticaProxy
     */
    public function testConnectionPool(): void
    {
        Coroutine\run(function () {
            $pool = $this->getPool();

            /** @var VerticaAdapter $conn */
            $conn = $pool->get();
            $data = $conn->fetchOne($conn->query('SELECT version()'));
            $pool->put($conn);

            self::assertRegExp("/Vertica Analytic Database v\d+\.\d+\.\d+\-\d+/", $data[0]['version']);

            $pool->close();
        });
    }

    /**
     * @covers VerticaProxy::reconnect
     */
    public function testLogging(): void
    {
        Coroutine\run(function () {
            $pool   = $this->getPool();
            $logger = new TestLogger();

            /** @var VerticaProxy $conn */
            $conn = $pool->get();
            $conn->setLogger($logger);

            $conn->reconnect(new Exception('connection broken', 1234));
            $pool->put($conn);

            $pool->close();

            self::assertTrue(
                $logger->hasError(
                    [
                        'message' => 'Reconnecting to Vertica due to: connection broken',
                        'context' => [
                            'code'  => 1234,
                            'class' => Exception::class,
                        ],
                    ]
                )
            );
        });
    }

    protected function getPool(): ConnectionPool
    {
        return new ConnectionPool(
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
    }
}
