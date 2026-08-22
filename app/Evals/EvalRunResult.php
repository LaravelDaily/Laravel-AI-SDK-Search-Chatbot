<?php

namespace App\Evals;

final readonly class EvalRunResult
{
    public function __construct(
        public bool $passed,
        public string $outcome,
        public int $latencyMs,
        public int $inputTokens,
        public int $outputTokens,
        public int $estimatedCostCents,
        public ?int $total = null,
        public ?string $message = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'outcome' => $this->outcome,
            'latency_ms' => $this->latencyMs,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'estimated_cost_cents' => $this->estimatedCostCents,
            'total' => $this->total,
            'message' => $this->message,
        ];
    }
}
