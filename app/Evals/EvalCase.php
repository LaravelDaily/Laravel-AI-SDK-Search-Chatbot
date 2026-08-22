<?php

namespace App\Evals;

use App\Search\SearchAttempt;
use Closure;
use InvalidArgumentException;

final readonly class EvalCase
{
    /**
     * @param  array{
     *     entity?: string,
     *     outcome?: string,
     *     assert?: callable(SearchAttempt): bool
     * }  $expect
     * @param  list<string>  $tags
     * @param  array<string, mixed>|Closure(): array<string, mixed>|null  $translation
     */
    public function __construct(
        public string $id,
        public string $question,
        public string $actor,
        public array $expect,
        public array $tags,
        public array|Closure|null $translation = null,
    ) {}

    /**
     * @param  array<string, mixed>  $case
     */
    public static function fromArray(array $case): self
    {
        $id = $case['id'] ?? null;
        $question = $case['question'] ?? null;
        $actor = $case['actor'] ?? null;
        $expect = $case['expect'] ?? null;
        $tags = $case['tags'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new InvalidArgumentException('Eval case is missing a string id.');
        }

        if (! is_string($question) || $question === '') {
            throw new InvalidArgumentException("Eval case [{$id}] is missing a question.");
        }

        if (! is_string($actor) || $actor === '') {
            throw new InvalidArgumentException("Eval case [{$id}] is missing an actor.");
        }

        if (! is_array($expect)) {
            throw new InvalidArgumentException("Eval case [{$id}] is missing expect.");
        }

        if (! is_array($tags) || $tags === []) {
            throw new InvalidArgumentException("Eval case [{$id}] must have at least one tag.");
        }

        /** @var list<string> $tags */
        $tags = array_values(array_map(strval(...), $tags));

        $translation = $case['translation'] ?? null;

        if ($translation !== null && ! is_array($translation) && ! $translation instanceof Closure) {
            throw new InvalidArgumentException("Eval case [{$id}] translation must be an array or closure.");
        }

        return new self(
            id: $id,
            question: $question,
            actor: $actor,
            expect: $expect,
            tags: $tags,
            translation: $translation,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolvedTranslation(): ?array
    {
        if ($this->translation instanceof Closure) {
            return ($this->translation)();
        }

        return $this->translation;
    }

    public function matchesFilter(?string $filter): bool
    {
        if ($filter === null || $filter === '') {
            return true;
        }

        $needle = mb_strtolower($filter);

        if (str_contains(mb_strtolower($this->id), $needle)) {
            return true;
        }

        foreach ($this->tags as $tag) {
            if (str_contains(mb_strtolower($tag), $needle)) {
                return true;
            }
        }

        return false;
    }
}
