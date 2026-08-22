<?php

namespace App\Search;

use App\Ai\SearchQueryTranslatorService;
use App\Ai\TranslationFailed;
use App\Models\AiSearchLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\Usage;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class SearchService
{
    public function __construct(
        private SearchQueryTranslatorService $translator,
        private EntityResolver $entityResolver,
        private SearchQueryRunner $runner,
        private TenantGuard $tenantGuard,
        private TokenCostEstimator $costEstimator,
    ) {}

    public function search(string $question, User $actor): SearchAttempt
    {
        $startedAt = hrtime(true);

        if ($this->questionExceedsLimit($question)) {
            return $this->finish(
                actor: $actor,
                question: $question,
                outcome: SearchOutcome::Blocked,
                startedAt: $startedAt,
                message: 'Questions cannot exceed '.config('search.max_question_length').' characters.',
            );
        }

        if ($this->tooManyAttempts($actor)) {
            $attempt = $this->finish(
                actor: $actor,
                question: $question,
                outcome: SearchOutcome::Blocked,
                startedAt: $startedAt,
                message: 'Too many searches. Please wait a moment and try again.',
            );

            throw new TooManyRequestsHttpException(
                retryAfter: RateLimiter::availableIn($this->rateLimitKey($actor)),
                message: $attempt->message,
            );
        }

        RateLimiter::increment($this->rateLimitKey($actor));

        $translation = $this->translator->translate($question, $actor);

        if ($translation instanceof TranslationFailed) {
            return $this->finish(
                actor: $actor,
                question: $question,
                outcome: SearchOutcome::Failed,
                startedAt: $startedAt,
                interpretation: $translation->interpretation,
                message: $translation->reason,
                attempts: $translation->attempts,
                usage: $translation->tokenUsage,
            );
        }

        if ($translation->needsClarification()) {
            return $this->finish(
                actor: $actor,
                question: $question,
                outcome: SearchOutcome::Clarification,
                startedAt: $startedAt,
                interpretation: $translation->interpretation,
                message: $translation->clarification,
                query: $translation->query,
                attempts: $translation->attempts,
                usage: $translation->tokenUsage,
            );
        }

        $query = $translation->query;

        if (! $query instanceof SearchQuery || $this->tenantGuard->wouldAccessOtherTenant($query, $actor)) {
            return $this->finish(
                actor: $actor,
                question: $question,
                outcome: SearchOutcome::Blocked,
                startedAt: $startedAt,
                interpretation: $translation->interpretation,
                message: 'This search was blocked because it would access another tenant\'s data.',
                query: $query,
                attempts: $translation->attempts,
                usage: $translation->tokenUsage,
            );
        }

        $resolution = $this->entityResolver->resolve($query, $actor);

        if ($resolution->outcome === EntityResolutionOutcome::Ambiguous) {
            return $this->finish(
                actor: $actor,
                question: $question,
                outcome: SearchOutcome::Clarification,
                startedAt: $startedAt,
                interpretation: $translation->interpretation,
                message: $resolution->message,
                query: $resolution->query,
                candidates: $resolution->candidates,
                attempts: $translation->attempts,
                usage: $translation->tokenUsage,
            );
        }

        if ($resolution->outcome === EntityResolutionOutcome::NotFound) {
            return $this->finish(
                actor: $actor,
                question: $question,
                outcome: SearchOutcome::Failed,
                startedAt: $startedAt,
                interpretation: $translation->interpretation,
                message: $resolution->message,
                query: $resolution->query,
                attempts: $translation->attempts,
                usage: $translation->tokenUsage,
            );
        }

        $result = $this->runner->run($resolution->query, $actor);

        return $this->finish(
            actor: $actor,
            question: $question,
            outcome: SearchOutcome::Ok,
            startedAt: $startedAt,
            interpretation: $translation->interpretation,
            query: $result->appliedQuery,
            result: $result,
            attempts: $translation->attempts,
            usage: $translation->tokenUsage,
        );
    }

    /**
     * @param  list<array{id: int, name: string, email: string|null}>  $candidates
     */
    private function finish(
        User $actor,
        string $question,
        SearchOutcome $outcome,
        int $startedAt,
        ?string $interpretation = null,
        ?string $message = null,
        ?SearchQuery $query = null,
        ?SearchResult $result = null,
        array $candidates = [],
        int $attempts = 0,
        ?Usage $usage = null,
    ): SearchAttempt {
        $usage ??= new Usage;

        $log = AiSearchLog::query()->create([
            'user_id' => $actor->id,
            'company_id' => $actor->company_id,
            'question' => $question,
            'interpretation' => $interpretation,
            'query_json' => $query?->toArray(),
            'compiled_sql' => $result?->sql,
            'row_count' => $result?->total,
            'attempts' => $attempts,
            'input_tokens' => $usage->promptTokens,
            'output_tokens' => $usage->completionTokens,
            'estimated_cost_cents' => $this->costEstimator->cents($usage),
            'outcome' => $outcome,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return new SearchAttempt(
            outcome: $outcome,
            question: $question,
            interpretation: $interpretation,
            message: $message,
            query: $query,
            result: $result,
            candidates: $candidates,
            log: $log,
            attempts: $attempts,
        );
    }

    private function questionExceedsLimit(string $question): bool
    {
        return Str::length($question) > (int) config('search.max_question_length');
    }

    private function tooManyAttempts(User $actor): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->rateLimitKey($actor),
            (int) config('search.rate_limit_per_minute'),
        );
    }

    private function rateLimitKey(User $actor): string
    {
        return 'ai-search:'.$actor->id;
    }

    private function durationMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
