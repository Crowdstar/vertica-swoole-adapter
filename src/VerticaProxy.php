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

namespace CrowdStar\VerticaSwooleAdapter;

use CrowdStar\Backoff\ExponentialBackoff;
use CrowdStar\VerticaSwooleAdapter\RetryConditions\CloseConnectionCondition;
use CrowdStar\VerticaSwooleAdapter\RetryConditions\RetryCondition;
use Psr\Log\LoggerInterface;
use Swoole\ObjectProxy;
use Throwable;

/**
 * Class VerticaProxy
 *
 * @package CrowdStar\VerticaSwooleAdapter
 */
class VerticaProxy extends ObjectProxy
{
    /**
     * @var callable callable
     */
    protected $constructor;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * VerticaProxy constructor.
     *
     * @param callable $constructor
     */
    public function __construct(callable $constructor)
    {
        $this->constructor = $constructor;
        parent::__construct($constructor());
    }

    public function reconnect(?Throwable $t = null): void
    {
        if (!empty($t)) {
            $this->logError(
                'Reconnecting to Vertica due to: ' . $t->getMessage(),
                [
                    'code'  => $t->getCode(),
                    'class' => get_class($t),
                ]
            );
        }

        // It "seems" that from a single process there is only one DB connection that could be made (according to what
        // I see from the results of query "SELECT * FROM v_monitor.sessions"; and, even we try to "reconnect" by
        // creating a new VerticaAdapter object, all VerticaAdapter objects from the same process use/share the same
        // connection.
        // Thus, we want to force to disconnect before reconnecting, to avoid any possible issues related to it.
        // @see https://www.vertica.com/blog/the-parts-of-a-session-id-quick-tip/ The Parts of a Session ID: Quick Tip
        //
        // Here we should call $adapter->closeConnection() instead of $this->closeConnection() to avoid
        // infinite loop (caused by exponential backoff in method $this->__call().
        /** @var VerticaAdapter $adapter */
        $adapter   = $this->__getObject();
        $condition = new CloseConnectionCondition($adapter);
        if (!$condition->met(null, null)) { // If the connection is open.
            (new ExponentialBackoff($condition))->setMaxAttempts(3)->run(
                function () use ($adapter) {
                    $adapter->closeConnection();
                }
            );
        }

        $constructor = $this->constructor;
        parent::__construct($constructor());
    }

    public function __call(string $name, array $arguments)
    {
        return (new ExponentialBackoff(new RetryCondition($this)))->setMaxAttempts(3)->run(
            function () use ($name, $arguments) {
                return parent::__call($name, $arguments);
            }
        );
    }

    public function logError(string $message, array $context = []): self
    {
        if (isset($this->logger)) {
            $this->logger->error($message, $context);
        }

        return $this;
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }
}
