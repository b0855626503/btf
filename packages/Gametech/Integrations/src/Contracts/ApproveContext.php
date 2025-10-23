<?php

namespace Gametech\Integrations\Contracts;

final class ApproveContext
{
    public function __construct(
        public string $op,          // 'deposit' | 'withdraw'
        public string $mode,        // 'manual' | 'auto'
        public string $username,
        public float  $amount,
        public object $website,     // WebsiteProxy (expect fields: user, pass, scode, appID ฯลฯ)
        public int    $timeoutSec = 15,
        public int    $retryTimes = 2,
        public int    $retrySleepMs = 300,
        public ?string $traceId = null,
        public array  $extra = [],  // พารามิเตอร์พิเศษต่อ provider
    ) {}
}
