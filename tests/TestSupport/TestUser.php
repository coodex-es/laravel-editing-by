<?php

namespace CoodexEs\LaravelEditingBy\Tests\TestSupport;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
