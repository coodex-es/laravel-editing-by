<?php

namespace CoodexEs\LaravelEditingBy\Commands;

use CoodexEs\LaravelEditingBy\Models\Editing;
use Illuminate\Console\Command;

class PruneExpiredEditingsCommand extends Command
{
    protected $signature = 'editing-by:prune-expired';

    protected $description = 'Delete expired active editings.';

    public function handle(): int
    {
        $count = Editing::query()->expired()->delete();

        $this->components->info(sprintf('Pruned %d expired editing records.', $count));

        return self::SUCCESS;
    }
}
