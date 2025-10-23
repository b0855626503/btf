<?php

namespace Gametech\Integrations\Contracts;

final class BalanceContext
{
    public function __construct(
        public string $username,
        public object $website,
        public int    $timeoutSec = 15,
        public int    $retryTimes = 2,
        public int    $retrySleepMs = 300,
        public ?string $traceId = null,
        public array  $extra = [],
    ) {}
}
