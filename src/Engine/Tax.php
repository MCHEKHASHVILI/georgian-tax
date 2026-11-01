<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use Closure;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Outcome;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Pipeline\Pipeline;
use LogicException;

/**
 * One tax of the Code: income tax, profit tax, VAT, excise, import, property.
 *
 * Twice a pipeline. In the engine it is one pipe: it adds its outcome to the TaxReport and hands the
 * report on. Inside, it sends its own subject — a Supply, an Assessment, a Duty — through its stages
 * with Laravel's Pipeline, every stage either answering or calling $next.
 *
 * stages() lists them in order. A stage that takes handlers maps to the handler classes:
 *
 *     Stage\IdentifyOperation::class,
 *     Stage\ApplyExemptions::class => [Exemption\Export::class, Exemption\ExemptCategories::class],
 *
 * Every class is built by the container, so stages and handlers can type-hint what they need, and
 * an app can replace a tax's stages through config('georgian-tax.pipelines.<tax>').
 */
abstract class Tax
{
    public function __construct(
        protected readonly Container $container,
        protected readonly Config $config,
    ) {}

    /** The key this tax reports under: 'income', 'vat', 'profit', 'excise', 'import', 'property'. */
    abstract public function name(): string;

    /** @return array<int|class-string, class-string|list<class-string>> */
    abstract protected function stages(): array;

    /** What travels through this tax's stages. The report shows what earlier taxes decided. */
    abstract protected function subject(TaxableEvent $event, ?TaxReport $report): object;

    public function assess(TaxableEvent $event, ?TaxReport $report = null): Outcome
    {
        if ($event instanceof Refund) {
            return $this->assess($event->original)->reversed($event->share());
        }

        return new Pipeline($this->container)
            ->send($this->subject($event, $report))
            ->through($this->pipes())
            ->then(fn () => throw new LogicException(static::class . ': no stage answered — the last stage must always answer.'));
    }

    /** As a pipe of the engine. */
    final public function handle(TaxReport $report, Closure $next): TaxReport
    {
        return $next($report->with($this->assess($report->event, $report)));
    }

    /** @return list<object> */
    protected function pipes(): array
    {
        $stages = $this->config->get("georgian-tax.pipelines.{$this->name()}") ?? $this->stages();
        $pipes = [];

        foreach ($stages as $key => $value) {
            $pipes[] = is_int($key)
                ? $this->container->make($value)
                : $this->container->make($key, ['handlers' => array_map($this->container->make(...), $value)]);
        }

        return $pipes;
    }
}
