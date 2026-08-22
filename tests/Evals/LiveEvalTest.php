<?php

use App\Evals\EvalRunner;
use App\Evals\GoldenDataset;
use Database\Seeders\DemoDataSeeder;

uses()->group('live');

it('meets the committed accuracy baseline', function () {
    $hasKey = filled(config('ai.providers.anthropic.key')) || filled(config('ai.providers.openai.key'));

    if (! $hasKey) {
        $this->markTestSkipped('Live evals require ANTHROPIC_API_KEY or OPENAI_API_KEY.');
    }

    $this->travelTo('2026-08-22 09:00:00');
    $this->seed(DemoDataSeeder::class);

    $baseline = json_decode((string) file_get_contents(base_path('tests/Evals/baseline.json')), true);
    $minimum = (float) ($baseline['accuracy'] ?? 0);

    $report = app(EvalRunner::class)->run(GoldenDataset::cases());

    expect(app(EvalRunner::class)->meetsBaseline($report, $minimum))->toBeTrue()
        ->and($report->accuracy())->toBeGreaterThanOrEqual($minimum);
});
