<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Search\Naive\FailureComparison;
use App\Search\Naive\FailureShowcase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:failures')]
#[Description('Print a before/after table of naive vs guarded search failure modes.')]
class DemoFailuresCommand extends Command
{
    public function handle(FailureShowcase $showcase): int
    {
        if (! Company::query()->where('name', 'Acme Services')->exists()) {
            $this->error('No demo data found. Run php artisan migrate:fresh --seed first.');

            return self::FAILURE;
        }

        $this->info('Naive vs guarded search — failure showcase');
        $this->newLine();

        $this->table(
            ['Failure mode', 'Naive', 'Guarded'],
            array_map(
                fn (FailureComparison $comparison): array => [
                    $comparison->mode,
                    $comparison->naive,
                    $comparison->guarded,
                ],
                $showcase->comparisons(),
            ),
        );

        return self::SUCCESS;
    }
}
