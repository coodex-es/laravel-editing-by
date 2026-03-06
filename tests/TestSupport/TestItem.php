<?php

namespace CoodexEs\LaravelEditingBy\Tests\TestSupport;

use CoodexEs\LaravelEditingBy\Concerns\HasEditingBy;
use Illuminate\Database\Eloquent\Model;

class TestItem extends Model
{
    use HasEditingBy;

    protected $table = 'items';

    protected $guarded = [];
}
