<?php

namespace CoodexEs\LaravelEditingBy\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class EditingTable
{
    public static function create(): void
    {
        Schema::create(EditingByConfig::editingTable(), function (Blueprint $table): void {
            self::define($table);
        });
    }

    public static function recreate(): void
    {
        Schema::dropIfExists(EditingByConfig::editingTable());
        self::create();
    }

    public static function define(Blueprint $table): void
    {
        $table->id();
        self::addUserReference($table);
        $table->string('item_type');
        $table->string('item_id');
        $table->timestamp('expiration');
        $table->timestamps();

        $table->unique(['item_type', 'item_id'], 'model_editings_item_unique');
        $table->index('expiration', 'model_editings_expiration_index');
        $table->index('user_id', 'model_editings_user_id_index');
        $table->index(['item_type', 'item_id', 'expiration'], 'model_editings_item_expiration_index');
    }

    protected static function addUserReference(Blueprint $table): void
    {
        $userTable = EditingByConfig::userTable();
        $userKeyName = EditingByConfig::userKeyName();

        match (EditingByConfig::userKeyColumnType()) {
            'foreignId' => $table->foreignId('user_id')->constrained($userTable, $userKeyName)->cascadeOnDelete(),
            'unsignedBigInteger' => self::foreignColumn($table, 'unsignedBigInteger', $userTable, $userKeyName),
            'uuid' => self::foreignColumn($table, 'uuid', $userTable, $userKeyName),
            'ulid' => self::foreignColumn($table, 'ulid', $userTable, $userKeyName),
            'string' => self::foreignColumn($table, 'string', $userTable, $userKeyName),
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported editing-by.user_key_column_type [%s]. Supported values: auto, foreignId, unsignedBigInteger, uuid, ulid, string.',
                EditingByConfig::userKeyColumnType(),
            )),
        };
    }

    protected static function foreignColumn(Blueprint $table, string $columnType, string $userTable, string $userKeyName): void
    {
        $table->{$columnType}('user_id');
        $table->foreign('user_id')->references($userKeyName)->on($userTable)->cascadeOnDelete();
    }
}
