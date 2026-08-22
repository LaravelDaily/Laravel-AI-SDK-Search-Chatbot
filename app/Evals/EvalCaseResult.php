<?php

namespace App\Evals;

final readonly class EvalCaseResult
{
    /**
     * @param  list<string>  $tags
     * @param  list<EvalRunResult>  $runs
     */
    public function __construct(
        public string $id,
        public string $question,
        public array $tags,
        public array $runs,
    ) {}

    public function passCount(): int
    {
        return count(array_filter(
            $this->runs,
            fn (EvalRunResult $run): bool => $run->passed,
        ));
    }

    public function passed(): bool
    {
        return $this->runs !== [] && $this->passCount() === count($this->runs);
    }

    public function isConsistent(): bool
    {
        if ($this->runs === []) {
            return true;
        }

        $first = $this->runs[0]->passed;

        foreach ($this->runs as $run) {
            if ($run->passed !== $first) {
                return false;
            }
        }

        return true;
    }

    public function lastRun(): ?EvalRunResult
    {
        if ($this->runs === []) {
            return null;
        }

        return $this->runs[count($this->runs) - 1];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'tags' => $this->tags,
            'passed' => $this->passed(),
            'consistent' => $this->isConsistent(),
            'pass_count' => $this->passCount(),
            'runs' => array_map(
                fn (EvalRunResult $run): array => $run->toArray(),
                $this->runs,
            ),
        ];
    }
}
