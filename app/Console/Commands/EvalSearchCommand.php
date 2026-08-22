<?php

namespace App\Console\Commands;

use App\Evals\EvalCaseResult;
use App\Evals\EvalReport;
use App\Evals\EvalRunner;
use App\Evals\EvalRunResult;
use App\Evals\GoldenDataset;
use App\Models\Company;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

#[Signature('eval:search {--model= : Model override for the translator agent} {--runs=1 : Times to run each case for a consistency score} {--filter= : Filter cases by id or tag}')]
#[Description('Run the natural-language search eval harness against the golden dataset.')]
class EvalSearchCommand extends Command
{
    public function handle(EvalRunner $runner): int
    {
        if (! Company::query()->where('name', 'Acme Services')->exists()) {
            $this->error('No demo data found. Run php artisan migrate:fresh --seed first.');

            return self::FAILURE;
        }

        $cases = GoldenDataset::matching($this->option('filter') ?: null);

        if ($cases === []) {
            $this->error('No golden cases matched the given filter.');

            return self::FAILURE;
        }

        $model = filled($this->option('model')) ? (string) $this->option('model') : null;
        $runs = max(1, (int) $this->option('runs'));

        $report = $runner->run($cases, $runs, $model);
        $path = $this->writeReport($report);

        $this->info('Natural-language search eval');
        $this->line('Model: '.$report->model.'  Cases: '.count($report->cases).'  Runs: '.$report->runsPerCase);
        $this->newLine();

        $this->table(
            ['ID', 'Result', 'Latency ms', 'Tokens', 'Cost cents'],
            array_map(fn (EvalCaseResult $case): array => $this->tableRow($case), $report->cases),
        );

        $this->newLine();
        $this->info(sprintf(
            'Accuracy: %s  Consistency: %s  Avg latency: %sms  Cost / 1k searches: %s cents',
            Number::percentage($report->accuracy() * 100, 1),
            Number::percentage($report->consistency() * 100, 1),
            Number::format($report->averageLatencyMs(), 0),
            Number::format($report->costPerThousandSearchesCents()),
        ));
        $this->line('Wrote '.$path);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function tableRow(EvalCaseResult $case): array
    {
        $runCount = count($case->runs);
        $result = $runCount === 1
            ? ($case->passed() ? 'PASS' : 'FAIL')
            : $case->passCount().'/'.$runCount;

        $run = $case->lastRun();

        if (! $run instanceof EvalRunResult) {
            return [$case->id, $result, '0', '0', '0'];
        }

        return [
            $case->id,
            $result,
            (string) $run->latencyMs,
            (string) ($run->inputTokens + $run->outputTokens),
            (string) $run->estimatedCostCents,
        ];
    }

    private function writeReport(EvalReport $report): string
    {
        $filename = now()->format('Ymd-His-u').'-'.Str::slug($report->model).'.json';
        $path = 'evals/'.$filename;

        Storage::disk('local')->put(
            $path,
            json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        return 'storage/app/'.$path;
    }
}
