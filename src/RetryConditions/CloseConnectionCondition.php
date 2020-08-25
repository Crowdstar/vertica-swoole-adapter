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
