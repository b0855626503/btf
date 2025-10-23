<?php

namespace Gametech\Integrations\Contracts;

final class BalanceResult
{
    public function __construct(
        public bool $success,
        public ?float $credit = null,       // เครดิตของสมาชิก (ถ้ารู้)
        public ?float $agent_credit = null, // เครดิตของเอเยนต์ (ถ้ารู้)
        public string $msg = '',
        public array $raw = [],
    ) {}
}
