<?php

namespace CoodexEs\LaravelEditingBy\Events;

use CoodexEs\LaravelEditingBy\Models\Editing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EditingTakenOver
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $item,
        public readonly Editing $editing,
        public readonly Model $user,
        public readonly ?Model $previousUser,
    ) {}
}
