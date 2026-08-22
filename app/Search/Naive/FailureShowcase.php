<?php

namespace App\Search\Naive;

use App\Ai\SearchQueryTranslatorService;
use App\Ai\TranslationFailed;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Search\Aggregate;
use App\Search\EntityResolver;
use App\Search\Filter;
use App\Search\InvalidSearchQuery;
use App\Search\Operator;
use App\Search\SearchQuery;
use App\Search\SearchQueryRunner;
use App\Search\SearchQueryValidator;
use App\Search\SearchSchema;
use Illuminate\Database\QueryException;

/**
 * Runs the Phase 6 before/after comparisons against seeded demo data.
 */
final class FailureShowcase
{
    public function __construct(
        private NaiveQueryRunner $naiveRunner,
        private SearchQueryRunner $realRunner,
        private NaiveEntityResolver $naiveResolver,
        private EntityResolver $realResolver,
        private SearchQueryValidator $validator,
        private SearchQueryTranslatorService $translator,
    ) {}

    /**
     * @return list<FailureComparison>
     */
    public function comparisons(): array
    {
        $agent = $this->acmeAgent();
        $viewer = $this->acmeViewer();

        return [
            $this->softDeletesComparison($agent),
            $this->tenantLeakComparison($agent),
            $this->piiExposureComparison($viewer),
            $this->ambiguousEntityComparison($agent),
            $this->rollingVsCalendarComparison($agent),
            $this->hallucinatedFieldComparison($agent),
            $this->unboundedComparison($agent),
        ];
    }

    public function acmeAgent(): User
    {
        return User::query()->where('email', 'acme-agent@example.com')->firstOrFail();
    }

    public function acmeViewer(): User
    {
        return User::query()->where('email', 'acme-viewer@example.com')->firstOrFail();
    }

    public function globexJohnSmith(): Customer
    {
        $globex = Company::query()->where('name', 'Globex Ltd')->firstOrFail();

        return Customer::query()
            ->where('company_id', $globex->id)
            ->where('name', 'John Smith')
            ->firstOrFail();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function lastYearWindow(): array
    {
        return [now()->subYear()->toDateString(), now()->toDateString()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function calendarYearWindow(): array
    {
        return [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()];
    }

    public static function lastYearQuery(): SearchQuery
    {
        return new SearchQuery(
            entity: 'service_requests',
            filters: [
                new Filter('opened_at', Operator::Between->value, self::lastYearWindow()),
            ],
            aggregate: new Aggregate('count', 'id'),
        );
    }

    public static function rollingYearQuery(): SearchQuery
    {
        return self::lastYearQuery();
    }

    public static function calendarYearQuery(): SearchQuery
    {
        return new SearchQuery(
            entity: 'service_requests',
            filters: [
                new Filter('opened_at', Operator::Between->value, self::calendarYearWindow()),
            ],
            aggregate: new Aggregate('count', 'id'),
        );
    }

    public static function globexCustomerQuery(int $customerId): SearchQuery
    {
        return new SearchQuery(
            entity: 'customers',
            filters: [new Filter('id', Operator::Equals->value, $customerId)],
        );
    }

    public static function customersQuery(): SearchQuery
    {
        return new SearchQuery(entity: 'customers');
    }

    public static function johnSmithQuery(): SearchQuery
    {
        return new SearchQuery(
            entity: 'service_requests',
            filters: [new Filter('customer_name', Operator::Contains->value, 'John Smith')],
        );
    }

    public static function hallucinatedVipQuery(): SearchQuery
    {
        return new SearchQuery(
            entity: 'customers',
            filters: [new Filter('is_vip', Operator::Equals->value, true)],
        );
    }

    public static function unboundedQuery(): SearchQuery
    {
        return new SearchQuery(entity: 'service_requests', limit: 500);
    }

    /**
     * @return array<string, mixed>
     */
    public static function hallucinatedVipTranslatorOutput(): array
    {
        return [
            'entity' => 'customers',
            'filters' => [
                [
                    'field' => 'is_vip',
                    'operator' => '=',
                    'value' => true,
                ],
            ],
            'sort' => null,
            'direction' => 'desc',
            'limit' => 50,
            'confidence' => 'high',
            'interpretation' => 'VIP customers',
            'clarification_needed' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rollingYearTranslatorOutput(): array
    {
        [$from, $to] = self::lastYearWindow();

        return [
            'entity' => 'service_requests',
            'filters' => [
                [
                    'field' => 'opened_at',
                    'operator' => 'between',
                    'value' => [$from, $to],
                ],
            ],
            'sort' => null,
            'direction' => 'desc',
            'limit' => 50,
            'aggregate' => [
                'function' => 'count',
                'field' => 'id',
            ],
            'confidence' => 'high',
            'interpretation' => "Service requests opened in the rolling 12 months from {$from} to {$to}",
            'clarification_needed' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function calendarYearTranslatorOutput(): array
    {
        [$from, $to] = self::calendarYearWindow();

        return [
            'entity' => 'service_requests',
            'filters' => [
                [
                    'field' => 'opened_at',
                    'operator' => 'between',
                    'value' => [$from, $to],
                ],
            ],
            'sort' => null,
            'direction' => 'desc',
            'limit' => 50,
            'aggregate' => [
                'function' => 'count',
                'field' => 'id',
            ],
            'confidence' => 'high',
            'interpretation' => 'Service requests opened in calendar year '.now()->year." ({$from} to {$to})",
            'clarification_needed' => null,
        ];
    }

    public function handwrittenLastYearCount(User $actor): int
    {
        return ServiceRequest::query()
            ->where('company_id', $actor->company_id)
            ->whereNull('deleted_at')
            ->whereBetween('opened_at', [now()->subYear()->startOfDay(), now()->endOfDay()])
            ->count();
    }

    public function ensureUnboundedDataset(User $actor): void
    {
        $live = ServiceRequest::query()->where('company_id', $actor->company_id)->count();
        $needed = SearchSchema::MAX_LIMIT + 1 - $live;

        if ($needed <= 0) {
            return;
        }

        $customer = Customer::query()->where('company_id', $actor->company_id)->firstOrFail();

        ServiceRequest::factory()->count($needed)->create([
            'company_id' => $actor->company_id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $actor->id,
        ]);
    }

    public function translateHallucinatedField(User $actor): TranslationFailed
    {
        $result = $this->translator->translate('show VIP customers', $actor);

        if (! $result instanceof TranslationFailed) {
            throw new InvalidSearchQuery('Expected a validation failure for the hallucinated is_vip field.');
        }

        return $result;
    }

    private function softDeletesComparison(User $actor): FailureComparison
    {
        $query = self::lastYearQuery();
        $naive = $this->naiveRunner->run($query, $actor);
        $real = $this->realRunner->run($query, $actor);

        return new FailureComparison(
            mode: 'Soft deletes',
            naive: "{$naive->total} requests last year (includes deleted)",
            guarded: "{$real->total} requests last year (matches whereNull deleted_at)",
        );
    }

    private function tenantLeakComparison(User $actor): FailureComparison
    {
        $globexJohn = $this->globexJohnSmith();
        $query = self::globexCustomerQuery($globexJohn->id);
        $naive = $this->naiveRunner->run($query, $actor);
        $real = $this->realRunner->run($query, $actor);

        return new FailureComparison(
            mode: 'Tenant leak',
            naive: $naive->total.' Globex John Smith row'.($naive->total === 1 ? '' : 's'),
            guarded: $real->total.' rows',
        );
    }

    private function piiExposureComparison(User $actor): FailureComparison
    {
        $query = self::customersQuery();
        $naive = $this->naiveRunner->run($query, $actor);
        $real = $this->realRunner->run($query, $actor);

        $naiveRow = $naive->rows->first();
        $realRow = $real->rows->first();

        $naiveHasTaxId = $naiveRow instanceof Customer && array_key_exists('tax_id', $naiveRow->getAttributes());
        $realHasTaxId = $realRow instanceof Customer && array_key_exists('tax_id', $realRow->getAttributes());

        return new FailureComparison(
            mode: 'PII exposure',
            naive: $naiveHasTaxId ? 'tax_id present' : 'tax_id missing',
            guarded: $realHasTaxId ? 'tax_id present' : 'tax_id omitted for viewer',
        );
    }

    private function ambiguousEntityComparison(User $actor): FailureComparison
    {
        $query = self::johnSmithQuery();
        $naive = $this->naiveResolver->resolve($query, $actor);
        $real = $this->realResolver->resolve($query, $actor);
        $pickedId = $naive->query->filters[0]->value ?? null;

        return new FailureComparison(
            mode: 'Ambiguous entity',
            naive: "picked customer #{$pickedId}",
            guarded: count($real->candidates).' candidates; Jon Smyth omitted (soft-deleted)',
        );
    }

    private function rollingVsCalendarComparison(User $actor): FailureComparison
    {
        $rolling = $this->realRunner->run(self::rollingYearQuery(), $actor);
        $calendar = $this->realRunner->run(self::calendarYearQuery(), $actor);

        return new FailureComparison(
            mode: 'Rolling vs calendar year',
            naive: 'one unlabeled window',
            guarded: "rolling {$rolling->total} ≠ calendar {$calendar->total}",
        );
    }

    private function hallucinatedFieldComparison(User $actor): FailureComparison
    {
        $query = self::hallucinatedVipQuery();

        try {
            $this->naiveRunner->run($query, $actor);
            $naive = 'no error';
        } catch (QueryException $exception) {
            $naive = $this->sqlErrorSummary($exception);
        }

        try {
            $this->validator->validate($query);
            $guarded = 'accepted is_vip';
        } catch (InvalidSearchQuery $exception) {
            $guarded = $exception->reason().' (1 repair attempt)';
        }

        return new FailureComparison(
            mode: 'Hallucinated field',
            naive: $naive,
            guarded: $guarded,
        );
    }

    private function unboundedComparison(User $actor): FailureComparison
    {
        $this->ensureUnboundedDataset($actor);

        $naive = $this->naiveRunner->run(self::unboundedQuery(), $actor);
        $real = $this->realRunner->run(self::unboundedQuery(), $actor);

        return new FailureComparison(
            mode: 'Unbounded result set',
            naive: "{$naive->total} rows, no cap",
            guarded: "{$real->rows->count()} rows, truncated=".($real->truncated ? 'true' : 'false'),
        );
    }

    private function sqlErrorSummary(QueryException $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'is_vip')) {
            return 'SQL error: no such column is_vip';
        }

        return 'SQL error: '.str($message)->limit(80)->value();
    }
}
