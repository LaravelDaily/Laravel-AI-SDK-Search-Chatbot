<?php

namespace App\Livewire;

use App\Models\User;
use App\Search\Operator;
use App\Search\SearchAttempt;
use App\Search\SearchOutcome;
use App\Search\SearchQuery;
use App\Search\SearchResultPresenter;
use App\Search\SearchSchema;
use App\Search\SearchService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class SearchPanel extends Component
{
    public string $question = '';

    #[Locked]
    public ?string $interpretation = null;

    #[Locked]
    public ?string $confidence = null;

    #[Locked]
    public ?string $outcome = null;

    #[Locked]
    public ?string $message = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $appliedQuery = null;

    /**
     * @var list<array{id: int, name: string, email: string|null}>
     */
    #[Locked]
    public array $candidates = [];

    /**
     * @var list<string>
     */
    #[Locked]
    public array $tableHeadings = [];

    /**
     * @var list<list<string>>
     */
    #[Locked]
    public array $tableRows = [];

    #[Locked]
    public int $tableTotal = 0;

    #[Locked]
    public bool $tableTruncated = false;

    #[Locked]
    public ?string $sql = null;

    public ?int $editingIndex = null;

    /**
     * @var array{field: string, operator: string, value: string}
     */
    public array $draftFilter = [
        'field' => '',
        'operator' => '=',
        'value' => '',
    ];

    public function search(SearchService $searchService): void
    {
        $this->validate([
            'question' => ['required', 'string'],
        ]);

        try {
            $attempt = $searchService->search(trim($this->question), $this->actor());
        } catch (TooManyRequestsHttpException $exception) {
            $this->resetAttempt();
            $this->outcome = SearchOutcome::Blocked->value;
            $this->message = $exception->getMessage();

            return;
        }

        $this->applyAttempt($attempt);
    }

    public function removeFilter(int $index, SearchService $searchService): void
    {
        if ($this->appliedQuery === null) {
            return;
        }

        $filters = $this->appliedQuery['filters'] ?? [];

        if (! is_array($filters) || ! array_key_exists($index, $filters)) {
            return;
        }

        unset($filters[$index]);

        $query = $this->appliedQuery;
        $query['filters'] = array_values($filters);
        $this->appliedQuery = $query;
        $this->editingIndex = null;

        $this->rerun($searchService);
    }

    public function editFilter(int $index): void
    {
        $filter = $this->filters()[$index] ?? null;

        if ($filter === null) {
            return;
        }

        $this->editingIndex = $index;
        $this->draftFilter = [
            'field' => (string) $filter['field'],
            'operator' => (string) $filter['operator'],
            'value' => SearchResultPresenter::formatFilterValue($filter['value']),
        ];
    }

    public function applyFilterEdit(SearchService $searchService): void
    {
        if ($this->appliedQuery === null || $this->editingIndex === null) {
            return;
        }

        $filters = $this->appliedQuery['filters'] ?? [];

        if (! is_array($filters) || ! array_key_exists($this->editingIndex, $filters)) {
            return;
        }

        $filters[$this->editingIndex] = [
            'field' => $this->draftFilter['field'],
            'operator' => $this->draftFilter['operator'],
            'value' => $this->parseDraftValue($this->draftFilter['operator'], $this->draftFilter['value']),
        ];

        $query = $this->appliedQuery;
        $query['filters'] = array_values($filters);
        $this->appliedQuery = $query;
        $this->editingIndex = null;

        $this->rerun($searchService);
    }

    public function cancelFilterEdit(): void
    {
        $this->editingIndex = null;
    }

    /**
     * @return list<array{field: string, operator: string, value: mixed}>
     */
    public function filters(): array
    {
        $filters = $this->appliedQuery['filters'] ?? [];

        if (! is_array($filters)) {
            return [];
        }

        $normalized = [];

        foreach (array_values($filters) as $filter) {
            if (! is_array($filter) || ! isset($filter['field'], $filter['operator'])) {
                continue;
            }

            $normalized[] = [
                'field' => (string) $filter['field'],
                'operator' => (string) $filter['operator'],
                'value' => $filter['value'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function editableFields(): array
    {
        $entity = SearchSchema::entity((string) ($this->appliedQuery['entity'] ?? ''));

        if ($entity === null) {
            return [];
        }

        $fields = [];

        foreach ($entity->fields as $field) {
            if (! $field->denied) {
                $fields[] = $field->name;
            }
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    public function editableOperators(): array
    {
        $entity = SearchSchema::entity((string) ($this->appliedQuery['entity'] ?? ''));
        $field = $entity?->field($this->draftFilter['field']);

        if ($field === null) {
            return array_map(
                static fn (Operator $operator): string => $operator->value,
                Operator::cases(),
            );
        }

        return array_map(
            static fn (Operator $operator): string => $operator->value,
            $field->operators,
        );
    }

    public function showingClarification(): bool
    {
        return $this->outcome === SearchOutcome::Clarification->value;
    }

    public function showingResults(): bool
    {
        return $this->outcome === SearchOutcome::Ok->value;
    }

    public function render(): View
    {
        return view('livewire.search-panel');
    }

    private function rerun(SearchService $searchService): void
    {
        if ($this->appliedQuery === null) {
            return;
        }

        $this->applyAttempt($searchService->rerun(
            query: SearchQuery::fromArray($this->appliedQuery),
            actor: $this->actor(),
            question: $this->question,
            interpretation: $this->interpretation,
            confidence: $this->confidence,
        ));
    }

    private function applyAttempt(SearchAttempt $attempt): void
    {
        $actor = $this->actor();

        $this->interpretation = $attempt->interpretation;
        $this->confidence = $attempt->confidence;
        $this->outcome = $attempt->outcome->value;
        $this->message = $attempt->message;
        $this->appliedQuery = ($attempt->result?->appliedQuery ?? $attempt->query)?->toArray();
        $this->candidates = $attempt->candidates;
        $this->sql = $attempt->result?->sql;

        if ($attempt->result === null) {
            $this->tableHeadings = [];
            $this->tableRows = [];
            $this->tableTotal = 0;
            $this->tableTruncated = false;

            return;
        }

        $table = SearchResultPresenter::present($attempt->result, $actor);
        $this->tableHeadings = $table['headings'];
        $this->tableRows = $table['rows'];
        $this->tableTotal = $table['total'];
        $this->tableTruncated = $table['truncated'];
    }

    private function resetAttempt(): void
    {
        $this->interpretation = null;
        $this->confidence = null;
        $this->outcome = null;
        $this->message = null;
        $this->appliedQuery = null;
        $this->candidates = [];
        $this->tableHeadings = [];
        $this->tableRows = [];
        $this->tableTotal = 0;
        $this->tableTruncated = false;
        $this->sql = null;
        $this->editingIndex = null;
    }

    private function actor(): User
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }

    private function parseDraftValue(string $operator, string $value): mixed
    {
        if (! in_array($operator, [Operator::Between->value, Operator::In->value], true)) {
            return $value;
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', str_replace('–', ',', $value))),
            static fn (string $part): bool => $part !== '',
        ));
    }
}
