<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests\Fixtures;

use Psr\Log\AbstractLogger;

/**
 * Records the SQL of every statement executed through the DBAL connection, so tests can assert
 * on the generated SQL and not just on the hydrated results.
 */
final class QueryRecorder extends AbstractLogger
{
    /** @var list<string> */
    private array $queries = [];

    /**
     * @param mixed[] $context
     */
    public function log(
        $level,
        string|\Stringable $message,
        array $context = []
    ): void {
        if (isset($context['sql']) && \is_string($context['sql'])) {
            $this->queries[] = $context['sql'];
        }
    }

    public function getLastQuery(): string
    {
        $last = end($this->queries);

        return false === $last ? '' : $last;
    }

    public function reset(): void
    {
        $this->queries = [];
    }
}
