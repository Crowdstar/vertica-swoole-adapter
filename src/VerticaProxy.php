<?php
namespace CrowdStar\VerticaSwooleAdapter;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use CrowdStar\Backoff\ExponentialBackoff;
use CrowdStar\VerticaSwooleAdapter\RetryConditions\CloseConnectionCondition;
use CrowdStar\VerticaSwooleAdapter\RetryConditions\RetryCondition;
use CrowdStar\VerticaSwooleAdapter\VerticaAdapter;
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
            // Bugsnag::notifyException($t);
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
        $adapter = $this->__getObject();
        (new ExponentialBackoff(new CloseConnectionCondition($adapter)))->setMaxAttempts(3)->run(
            function () use ($adapter) {
                $adapter->closeConnection();
            }
        );

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
}
