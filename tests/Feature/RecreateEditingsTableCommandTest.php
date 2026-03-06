<?php

namespace CoodexEs\LaravelEditingBy\Tests\Feature;

use CoodexEs\LaravelEditingBy\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class RecreateEditingsTableCommandTest extends TestCase
{
    public function test_it_requires_force_to_recreate_the_table(): void
    {
        $this->assertSame(1, Artisan::call('editing-by:recreate-table'));
        $this->assertTrue(Schema::hasTable('model_editings'));
    }

    public function test_it_recreates_the_table_when_forced(): void
    {
        Schema::table('model_editings', function ($table): void {
            $table->string('temporary_column')->nullable();
        });

        $this->assertTrue(Schema::hasColumn('model_editings', 'temporary_column'));

        $this->assertSame(0, Artisan::call('editing-by:recreate-table', ['--force' => true]));

        $this->assertTrue(Schema::hasTable('model_editings'));
        $this->assertFalse(Schema::hasColumn('model_editings', 'temporary_column'));
    }
}
