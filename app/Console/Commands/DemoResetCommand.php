<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('demo:reset')]
#[Description('Reseed the database to the deterministic demo state between recording takes.')]
class DemoResetCommand extends Command
{
    /**
     * @var list<string>
     */
    private const array PRESERVED_TABLES = [
        'migrations',
        'sqlite_sequence',
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('demo:reset cannot run in production.');

            return self::FAILURE;
        }

        $this->wipeDataTables();
        $this->call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->info('Demo data reset to the known seeded state.');
        $this->line('Sign in at '.url('/login'));
        $this->line('  acme-admin@example.com / password   (SQL panel + /admin/ai-logs)');
        $this->line('  acme-agent@example.com / password   (default demo actor)');
        $this->line('  acme-viewer@example.com / password  (no tax_id)');

        return self::SUCCESS;
    }

    private function wipeDataTables(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (Schema::getTableListing(schemaQualified: false) as $table) {
                if (in_array($table, self::PRESERVED_TABLES, true)) {
                    continue;
                }

                DB::table($table)->truncate();
            }
        });
    }
}
