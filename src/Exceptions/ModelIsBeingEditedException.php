<?php

namespace CoodexEs\LaravelEditingBy\Exceptions;

use CoodexEs\LaravelEditingBy\Models\Editing;
use RuntimeException;

class ModelIsBeingEditedException extends RuntimeException
{
    public function __construct(public readonly Editing $editing)
    {
        $user = $editing->relationLoaded('user') ? $editing->user : null;
        $name = $user?->name ?? 'another user';

        parent::__construct(sprintf('This model is currently being edited by %s.', $name));
    }
}
