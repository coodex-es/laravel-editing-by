<?php

use CoodexEs\LaravelEditingBy\Support\EditingTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        EditingTable::create();
    }

    public function down(): void
    {
        Schema::dropIfExists(config('editing-by.table', 'model_editings'));
    }
};
