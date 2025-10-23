<?php

namespace Gametech\Integrations\Contracts;

final class ProviderResult
{
    public function __construct(
        public bool $success,
        public string $msg = '',
        public mixed $old_credit = null,
        public mixed $after_credit = null,
        public array $raw = [],
    ) {}
}
