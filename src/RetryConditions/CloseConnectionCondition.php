<?php

namespace CrowdStar\VerticaSwooleAdapter\RetryConditions;

use CrowdStar\Backoff\AbstractRetryCondition;
use CrowdStar\VerticaSwooleAdapter\VerticaAdapter;
use Exception;

class CloseConnectionCondition extends AbstractRetryCondition
{
    /**
     * @var VerticaAdapter
     */
    protected $adapter;

    public function __construct(VerticaAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @inheritdoc
     * @see https://www.php.net/manual/en/resource.php List of PHP Resource Types
     */
    public function met($result, ?Exception $e): bool
    {
        return ('odbc link' !== get_resource_type($this->adapter->getConnection()));
    }
}
