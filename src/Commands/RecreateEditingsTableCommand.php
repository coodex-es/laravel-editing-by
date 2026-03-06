<?php

namespace CoodexEs\LaravelEditingBy\Commands;

use CoodexEs\LaravelEditingBy\Support\EditingByConfig;
use CoodexEs\LaravelEditingBy\Support\EditingTable;
use Illuminate\Console\Command;

class RecreateEditingsTableCommand extends Command
{
    protected $signature = 'editing-by:recreate-table {--force : Drop and recreate the editing table using the current configuration}';

    protected $description = 'Drop and recreate the editing table using the current editing-by configuration.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->components->error('This command is destructive. Re-run it with --force.');

            return self::FAILURE;
        }

        EditingTable::recreate();

        $this->components->info(sprintf(
            'Recreated [%s] for user model [%s] using key [%s] (%s).',
            EditingByConfig::editingTable(),
            EditingByConfig::userModelClass(),
            EditingByConfig::userKeyName(),
            EditingByConfig::userKeyColumnType(),
        ));

        return self::SUCCESS;
    }
}
