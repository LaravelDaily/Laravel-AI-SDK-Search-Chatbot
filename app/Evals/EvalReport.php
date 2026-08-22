<?php

namespace App\Evals;

final readonly class EvalReport
{
    /**
     * @param  list<EvalCaseResult>  $cases
     */
    public function __construct(
        public string $model,
        public int $runsPerCase,
        public array $cases,
        public string $timestamp,
    ) {}

    public function accuracy(): float
    {
        $total = $this->totalRuns();

        if ($total === 0) {
            return 0.0;
        }

        return $this->passedRuns() / $total;
    }

    public function consistency(): float
    {
        $total = count($this->cases);

        if ($total === 0) {
            return 1.0;
        }

        $consistent = count(array_filter(
            $this->cases,
            fn (EvalCaseResult $case): bool => $case->isConsistent(),
        ));

        return $consistent / $total;
    }

    public function averageLatencyMs(): float
    {
        $total = $this->totalRuns();

        if ($total === 0) {
            return 0.0;
        }

        $sum = 0;

        foreach ($this->cases as $case) {
            foreach ($case->runs as $run) {
                $sum += $run->latencyMs;
            }
        }

        return $sum / $total;
    }

    public function totalInputTokens(): int
    {
        $sum = 0;

        foreach ($this->cases as $case) {
            foreach ($case->runs as $run) {
                $sum += $run->inputTokens;
            }
        }

        return $sum;
    }

    public function totalOutputTokens(): int
    {
        $sum = 0;

        foreach ($this->cases as $case) {
            foreach ($case->runs as $run) {
                $sum += $run->outputTokens;
            }
        }

        return $sum;
    }

    public function estimatedCostCents(): int
    {
        $sum = 0;

        foreach ($this->cases as $case) {
            foreach ($case->runs as $run) {
                $sum += $run->estimatedCostCents;
            }
        }

        return $sum;
    }

    public function costPerThousandSearchesCents(): int
    {
        $total = $this->totalRuns();

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->estimatedCostCents() / $total) * 1000);
    }

    public function passedRuns(): int
    {
        $sum = 0;

        foreach ($this->cases as $case) {
            $sum += $case->passCount();
        }

        return $sum;
    }

    public function totalRuns(): int
    {
        $sum = 0;

        foreach ($this->cases as $case) {
            $sum += count($case->runs);
        }

        return $sum;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'timestamp' => $this->timestamp,
            'runs' => $this->runsPerCase,
            'accuracy' => $this->accuracy(),
            'consistency' => $this->consistency(),
            'avg_latency_ms' => $this->averageLatencyMs(),
            'total_input_tokens' => $this->totalInputTokens(),
            'total_output_tokens' => $this->totalOutputTokens(),
            'estimated_cost_cents' => $this->estimatedCostCents(),
            'cost_per_1k_searches_cents' => $this->costPerThousandSearchesCents(),
            'passed_runs' => $this->passedRuns(),
            'total_runs' => $this->totalRuns(),
            'cases' => array_map(
                fn (EvalCaseResult $case): array => $case->toArray(),
                $this->cases,
            ),
        ];
    }
}
