<?php

declare(strict_types=1);

namespace GeorgianTax\Engine\Pipeline;

use Closure;

/**
 * Middleware pipeline. Each stage receives the subject and the next stage, and may:
 *   - answer itself (short-circuit),
 *   - pass a changed subject on: $next($subject->with...()),
 *   - or call $next and adjust what comes back.
 * Used twice: the engine runs the taxes through it, and each tax runs its own stages through it.
 *
 * @template TSubject
 * @template TResult
 */
final readonly class Pipeline
{
    /**
     * @param list<callable(TSubject, Closure(TSubject): TResult): TResult> $stages
     * @param Closure(TSubject): TResult $destination  what happens when every stage passed through
     */
    public function __construct(
        private array $stages,
        private Closure $destination,
    ) {}

    /** @param TSubject $subject @return TResult */
    public function process(mixed $subject): mixed
    {
        $next = $this->destination;

        foreach (array_reverse($this->stages) as $stage) {
            $next = static fn (mixed $subject): mixed => $stage($subject, $next);
        }

        return $next($subject);
    }
}
