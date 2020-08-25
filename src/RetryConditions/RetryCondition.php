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

namespace CrowdStar\VerticaSwooleAdapter\RetryConditions;

use CrowdStar\Backoff\AbstractRetryCondition;
use CrowdStar\VerticaSwooleAdapter\VerticaProxy;
use Exception;
use VerticaPhpAdapter\Exception\VerticaConnectionException;

class RetryCondition extends AbstractRetryCondition
{
    /**
     * @var VerticaProxy
     */
    protected $proxy;

    public function __construct(VerticaProxy $proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function met($result, ?\Exception $e): bool
    {
        if (!empty($e)) {
            if ($e instanceof VerticaConnectionException) {
                $this->proxy->reconnect($e);

                return false;
            } else {
                // We saw following errors in method \VerticaPhpAdapter\Db\Odbc\VerticaOdbcAbstract::query():
                // 1. \VerticaPhpAdapter\Exception\VerticaQueryException where "no connection to the server".
                // 2. \ErrorException odbc_exec(): SQL error: [Vertica][VerticaDSII] (10) An error occurred during query
                //    preparation: no connection to the server, SQL state S1000 in SQLExecDirect...
                // 3. \ErrorException odbc_exec(): SQL error: [Vertica][VerticaDSII] (20) An error occurred during query
                //    execution: server closed the connection unexpectedly ...... SQL state S1000 in SQLExecDirect
                if (
                    (strpos($e->getMessage(), "no connection to the server") !== false)
                    || (strpos($e->getMessage(), "server closed the connection unexpectedly") !== false)
                ) {
                    $this->proxy->reconnect($e);

                    return false;
                }
            }

            $this->proxy->logError(
                'Vertica operation failed due to: ' . $e->getMessage(),
                [
                    'code'  => $e->getCode(),
                    'class' => get_class($e),
                ]
            );

            throw $e;
        }

        return true;
    }
}
