<?php

namespace Gametech\Integrations;

use Gametech\Integrations\Contracts\ProviderContract;
use InvalidArgumentException;

final class ProviderManager
{
    public function __construct(
        /** @var array<string, class-string<ProviderContract>> */
        private array $map
    ) {}

    public function resolve(string $groupBot): ProviderContract
    {
        $class = $this->map[$groupBot] ?? null;
        if (!$class) {
            throw new InvalidArgumentException("Unknown provider group: {$groupBot}");
        }
        return app($class);
    }
}
