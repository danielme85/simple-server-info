<?php

declare(strict_types=1);

namespace danielme85\Server\Contracts;

interface CollectorInterface
{
    /**
     * Return all data provided by this collector.
     */
    public function all(): array;
}
