<?php

namespace App\Ai;

use App\Ai\Agents\SearchQueryTranslator;
use App\Models\User;
use App\Search\InvalidSearchQuery;
use App\Search\SearchQuery;
use App\Search\SearchQueryValidator;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class SearchQueryTranslatorService
{
    public function __construct(private SearchQueryValidator $validator) {}

    public function translate(string $question, User $actor): Translation|TranslationFailed
    {
        $agent = new SearchQueryTranslator($actor);
        $usage = new Usage;
        $attempts = 0;

        $first = $this->prompt($agent, $this->userPrompt($question, $actor), $usage, $attempts);

        if ($first instanceof TranslationFailed || $first->needsClarification()) {
            return $first;
        }

        try {
            $this->validator->validate($this->queryOrFail($first));

            return $first;
        } catch (InvalidSearchQuery $exception) {
            $repair = $this->prompt(
                $agent,
                $this->repairPrompt($question, $actor, $exception->reason()),
                $usage,
                $attempts,
            );

            if ($repair instanceof TranslationFailed || $repair->needsClarification()) {
                return $repair;
            }

            try {
                $this->validator->validate($this->queryOrFail($repair));

                return $repair;
            } catch (InvalidSearchQuery $retryException) {
                return new TranslationFailed(
                    reason: $retryException->reason(),
                    attempts: $attempts,
                    tokenUsage: $usage,
                    interpretation: $repair->interpretation,
                );
            }
        }
    }

    private function prompt(
        SearchQueryTranslator $agent,
        string $prompt,
        Usage &$usage,
        int &$attempts,
    ): Translation|TranslationFailed {
        $response = $agent->prompt($prompt);
        $usage = $usage->add($response->usage);
        $attempts++;

        return $this->mapResponse($response, $attempts, $usage);
    }

    private function mapResponse(mixed $response, int $attempts, Usage $usage): Translation|TranslationFailed
    {
        $output = $response instanceof StructuredAgentResponse
            ? $response->structured
            : (is_array($response) ? $response : []);

        if ($output === []) {
            return new TranslationFailed(
                reason: 'The model did not return a structured search query.',
                attempts: $attempts,
                tokenUsage: $usage,
            );
        }

        $clarification = $this->nullableString($output['clarification_needed'] ?? null);
        $interpretation = $this->nullableString($output['interpretation'] ?? null) ?? '';
        $confidence = $this->nullableString($output['confidence'] ?? null) ?? 'low';

        if ($clarification !== null) {
            return new Translation(
                query: null,
                interpretation: $interpretation,
                confidence: $confidence,
                clarification: $clarification,
                attempts: $attempts,
                tokenUsage: $usage,
            );
        }

        return new Translation(
            query: SearchQuery::fromArray($output),
            interpretation: $interpretation,
            confidence: $confidence,
            clarification: null,
            attempts: $attempts,
            tokenUsage: $usage,
        );
    }

    private function userPrompt(string $question, User $actor): string
    {
        $today = now()->timezone((string) config('app.timezone'))->toDateString();
        $timezone = (string) config('app.timezone');
        $company = $actor->company_id === null ? 'unknown' : $actor->company->name;
        $role = $actor->role->value;

        return <<<PROMPT
Question: {$question}

Actor context:
- Company: {$company}
- Role: {$role}
- Today: {$today}
- Timezone: {$timezone}
PROMPT;
    }

    private function queryOrFail(Translation $translation): SearchQuery
    {
        if (! $translation->query instanceof SearchQuery) {
            throw new InvalidSearchQuery('The model did not return a structured search query.');
        }

        return $translation->query;
    }

    private function repairPrompt(string $question, User $actor, string $reason): string
    {
        $normalizedReason = strtolower($reason);

        return $this->userPrompt($question, $actor).<<<PROMPT


The previous query failed validation: {$normalizedReason}
Correct the query using only allowed fields. Do not invent fields.
PROMPT;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
