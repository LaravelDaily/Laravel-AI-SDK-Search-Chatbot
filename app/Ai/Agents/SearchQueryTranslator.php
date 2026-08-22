<?php

namespace App\Ai\Agents;

use App\Models\User;
use App\Search\Operator;
use App\Search\SearchSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

final class SearchQueryTranslator implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(public User $actor) {}

    public function instructions(): string
    {
        $today = now()->timezone((string) config('app.timezone'))->toDateString();
        $timezone = (string) config('app.timezone');
        $role = $this->actor->role->value;
        $schema = SearchSchema::forPrompt();

        return <<<PROMPT
You translate natural-language search questions into a structured SearchQuery.

Today's date is {$today}. The actor's timezone is {$timezone}. The actor's role is {$role}.

Never invent fields, entities, or operators. Use only the schema below.
Never resolve customer names to IDs. Emit a customer_name (or name) contains filter instead.
If the question is ambiguous, set clarification_needed instead of guessing.

{$schema}
PROMPT;
    }

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return 'claude-sonnet-5';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $entities = SearchSchema::entityNames();
        $operators = array_map(
            static fn (Operator $operator): string => $operator->value,
            Operator::cases(),
        );

        return [
            'entity' => $schema->string()->enum($entities)->required(),
            'filters' => $schema->array()->items($schema->object([
                'field' => $schema->string()->required(),
                'operator' => $schema->string()->enum($operators)->required(),
                'value' => $schema->anyOf([
                    $schema->string(),
                    $schema->integer(),
                    $schema->number(),
                    $schema->boolean(),
                    $schema->array(),
                ])->required(),
            ]))->required(),
            'sort' => $schema->string()->nullable(),
            'direction' => $schema->string()->enum(['asc', 'desc']),
            'limit' => $schema->integer(),
            'aggregate' => $schema->object([
                'function' => $schema->string()->enum(['count', 'sum', 'avg'])->required(),
                'field' => $schema->string()->required(),
                'groupBy' => $schema->string()->nullable(),
            ])->nullable(),
            'confidence' => $schema->string()->enum(['high', 'low'])->required(),
            'interpretation' => $schema->string()->required()->description(
                'One-sentence plain-English restatement of the interpreted query.',
            ),
            'clarification_needed' => $schema->string()->nullable()->description(
                'Set when the question is ambiguous rather than guessing.',
            ),
        ];
    }
}
