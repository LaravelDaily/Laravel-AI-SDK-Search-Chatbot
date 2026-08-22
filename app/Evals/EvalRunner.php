<?php

namespace App\Evals;

use App\Search\SearchAttempt;
use App\Search\SearchService;
use Throwable;

final class EvalRunner
{
    public function __construct(private SearchService $search) {}

    /**
     * @param  list<EvalCase>  $cases
     */
    public function run(array $cases, int $runs = 1, ?string $model = null): EvalReport
    {
        config(['search.rate_limit_per_minute' => 10_000]);

        $runs = max(1, $runs);
        $results = [];

        foreach ($cases as $case) {
            $runResults = [];

            for ($iteration = 0; $iteration < $runs; $iteration++) {
                $runResults[] = $this->runOnce($case, $model);
            }

            $results[] = new EvalCaseResult(
                id: $case->id,
                question: $case->question,
                tags: $case->tags,
                runs: $runResults,
            );
        }

        return new EvalReport(
            model: $model ?? 'claude-sonnet-5',
            runsPerCase: $runs,
            cases: $results,
            timestamp: now()->toIso8601String(),
        );
    }

    public function meetsBaseline(EvalReport $report, float $minimumAccuracy): bool
    {
        return $report->accuracy() >= $minimumAccuracy;
    }

    private function runOnce(EvalCase $case, ?string $model): EvalRunResult
    {
        $actor = EvalActors::user($case->actor);
        $attempt = $this->search->search($case->question, $actor, $model, 120);

        try {
            $passed = $this->passed($case, $attempt);
            $message = $attempt->message;
        } catch (Throwable $exception) {
            $passed = false;
            $message = $exception->getMessage();
        }

        return new EvalRunResult(
            passed: $passed,
            outcome: $attempt->outcome->value,
            latencyMs: $attempt->log->duration_ms,
            inputTokens: $attempt->log->input_tokens,
            outputTokens: $attempt->log->output_tokens,
            estimatedCostCents: $attempt->log->estimated_cost_cents,
            total: $attempt->result?->total,
            message: $message,
        );
    }

    private function passed(EvalCase $case, SearchAttempt $attempt): bool
    {
        $expect = $case->expect;

        if (isset($expect['outcome']) && $attempt->outcome->value !== $expect['outcome']) {
            return false;
        }

        if (isset($expect['entity'])) {
            $entity = $attempt->result?->appliedQuery->entity ?? $attempt->query?->entity;

            if ($entity !== $expect['entity']) {
                return false;
            }
        }

        if (! array_key_exists('assert', $expect)) {
            return true;
        }

        return (bool) $expect['assert']($attempt);
    }
}
