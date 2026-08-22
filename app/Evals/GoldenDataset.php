<?php

namespace App\Evals;

final class GoldenDataset
{
    /**
     * @var list<EvalCase>|null
     */
    private static ?array $cases = null;

    public static function path(): string
    {
        return base_path('tests/Evals/golden.php');
    }

    /**
     * @return list<EvalCase>
     */
    public static function cases(): array
    {
        return self::$cases ??= array_map(
            fn (array $case): EvalCase => EvalCase::fromArray($case),
            self::raw(),
        );
    }

    /**
     * @return list<EvalCase>
     */
    public static function matching(?string $filter): array
    {
        $cases = self::cases();

        if ($filter === null || $filter === '') {
            return $cases;
        }

        $exact = array_values(array_filter(
            $cases,
            fn (EvalCase $case): bool => $case->id === $filter,
        ));

        if ($exact !== []) {
            return $exact;
        }

        return array_values(array_filter(
            $cases,
            fn (EvalCase $case): bool => $case->matchesFilter($filter),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function raw(): array
    {
        $cases = require self::path();

        if (! is_array($cases)) {
            return [];
        }

        return array_values($cases);
    }
}
