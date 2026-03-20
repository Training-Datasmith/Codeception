<?php

declare (strict_types=1);
namespace Codeception\Lib\Actor\Shared;

trait Retry
{
    protected int $retry_num = 1;
    protected int $retry_interval = 100;
    /**
     * Configure number of retries and initial interval.
     * Interval will be doubled on each unsuccessful execution.
     *
     * Use with \$I->retryXXX() methods;
     */
    public function retry(int $num, int $interval = 200): void
    {
        $this->retry_num = $num;
        $this->retry_interval = $interval;
    }
}