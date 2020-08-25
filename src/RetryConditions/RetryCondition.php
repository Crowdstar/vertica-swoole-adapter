<?php

namespace CrowdStar\VerticaSwooleAdapter\RetryConditions;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
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
                if ((strpos($e->getMessage(), "no connection to the server") !== false)
                    || (strpos($e->getMessage(), "server closed the connection unexpectedly") !== false)) {
                    $this->proxy->reconnect($e);

                    return false;
                }
            }

            Bugsnag::notifyException($e);

            throw $e;
        }

        return true;
    }
}
