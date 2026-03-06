<?php

namespace CoodexEs\LaravelEditingBy\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;

class EditingByConfig
{
    public static function editingTable(): string
    {
        return (string) config('editing-by.table', 'model_editings');
    }

    public static function userModelClass(): string
    {
        return (string) config('editing-by.user_model', config('auth.providers.users.model'));
    }

    public static function userModel(): Model
    {
        $modelClass = self::userModelClass();
        $model = app($modelClass);

        if (! $model instanceof Model) {
            throw new \RuntimeException(sprintf('Configured editing-by.user_model [%s] must be an Eloquent model.', $modelClass));
        }

        return $model;
    }

    public static function userTable(): string
    {
        $configuredTable = config('editing-by.user_table');

        if (is_string($configuredTable) && $configuredTable !== '') {
            return $configuredTable;
        }

        return self::userModel()->getTable();
    }

    public static function userKeyName(): string
    {
        $configuredKeyName = config('editing-by.user_key_name');

        if (is_string($configuredKeyName) && $configuredKeyName !== '') {
            return $configuredKeyName;
        }

        return self::userModel()->getKeyName();
    }

    public static function userKeyColumnType(): string
    {
        $configuredType = (string) config('editing-by.user_key_column_type', 'auto');

        if ($configuredType !== 'auto') {
            return $configuredType;
        }

        $model = self::userModel();

        if ($model->getIncrementing() && $model->getKeyType() === 'int') {
            return 'foreignId';
        }

        if (self::modelUsesTrait($model, 'Illuminate\\Database\\Eloquent\\Concerns\\HasUlids')) {
            return 'ulid';
        }

        if (self::modelUsesTrait($model, 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids')) {
            return 'uuid';
        }

        return $model->getKeyType() === 'int' ? 'unsignedBigInteger' : 'string';
    }

    public static function itemKeyAsStringExpression(ConnectionInterface $connection, string $qualifiedColumn): string
    {
        return match ($connection->getDriverName()) {
            'pgsql', 'sqlite' => sprintf('CAST(%s AS TEXT)', $qualifiedColumn),
            'sqlsrv' => sprintf('CAST(%s AS NVARCHAR(255))', $qualifiedColumn),
            default => sprintf('CAST(%s AS CHAR)', $qualifiedColumn),
        };
    }

    public static function fullNameExpression(ConnectionInterface $connection, string $usersAlias): string
    {
        if ($connection->getDriverName() === 'sqlite') {
            return sprintf(
                "NULLIF(TRIM(COALESCE(%s.name, '') || ' ' || COALESCE(%s.surname, '')), '')",
                $usersAlias,
                $usersAlias,
            );
        }

        return sprintf(
            "NULLIF(TRIM(CONCAT(COALESCE(%s.name, ''), ' ', COALESCE(%s.surname, ''))), '')",
            $usersAlias,
            $usersAlias,
        );
    }

    protected static function modelUsesTrait(Model $model, string $trait): bool
    {
        if (! trait_exists($trait)) {
            return false;
        }

        return in_array($trait, class_uses_recursive($model), true);
    }
}
