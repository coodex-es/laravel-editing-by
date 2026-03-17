<?php

namespace CoodexEs\LaravelEditingBy\Concerns;

use CoodexEs\LaravelEditingBy\Events\EditingTakenOver;
use CoodexEs\LaravelEditingBy\Exceptions\ModelIsBeingEditedException;
use CoodexEs\LaravelEditingBy\Models\Editing;
use CoodexEs\LaravelEditingBy\Support\EditingByConfig;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait HasEditingBy
{
    public function editing(): MorphOne
    {
        return $this->morphOne(Editing::class, 'item');
    }

    public function editor()
    {
        return $this->editingRecord()?->user;
    }

    public function editingRecord(): ?Editing
    {
        /** @var Editing|null $editing */
        $editing = $this->relationLoaded('editing')
            ? $this->getRelation('editing')
            : $this->editing()->with('user')->first();

        if (! $editing) {
            return null;
        }

        if ($editing->expiration->isPast()) {
            return null;
        }

        return $editing;
    }

    public function isBeingEdited(): bool
    {
        $editing = $this->editingRecord();

        return $editing !== null && (string) $editing->user_id !== (string) Auth::id();
    }

    public function markEditing(): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            throw new \RuntimeException('markEditing requires an authenticated user.');
        }

        $editing = $this->createEditingRecord($userId);

        if ($editing) {
            $this->setRelation('editing', $editing->load('user'));

            return;
        }

        DB::transaction(function () use ($userId): void {
            $editing = $this->lockEditingRecord();

            if (! $editing) {
                $editing = $this->createEditingRecord($userId) ?? $this->lockEditingRecord();
            }

            if (! $editing) {
                throw new \RuntimeException('Unable to create or load the editing record.');
            }

            if (! $editing->expiration->isPast() && (string) $editing->user_id !== (string) $userId) {
                throw new ModelIsBeingEditedException($editing);
            }

            $editing->forceFill([
                'user_id' => $userId,
                'expiration' => $this->freshEditingExpiration(),
            ])->save();

            $this->setRelation('editing', $editing->fresh('user'));
        });
    }

    public function addEditingTime(): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            throw new \RuntimeException('addEditingTime requires an authenticated user.');
        }

        /** @var Editing|null $editing */
        $editing = $this->editing()->where('user_id', $userId)->first();

        if (! $editing) {
            throw new \RuntimeException('No active editing record exists for the authenticated user.');
        }

        $editing->forceFill([
            'expiration' => $this->freshEditingExpiration(),
        ])->save();

        $this->setRelation('editing', $editing->fresh('user'));
    }

    public function releaseEditing(): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            throw new \RuntimeException('releaseEditing requires an authenticated user.');
        }

        /** @var Editing|null $editing */
        $editing = $this->editing()->where('user_id', $userId)->first();

        if (! $editing) {
            return;
        }

        $editing->loadMissing('user', 'item');
        $editing->delete();
        $this->unsetRelation('editing');
    }

    public function takeOverEditing(): void
    {
        $userId = Auth::id();
        $user = Auth::user();

        if ($userId === null || $user === null) {
            throw new \RuntimeException('takeOverEditing requires an authenticated user.');
        }

        $editing = $this->createEditingRecord($userId);

        if ($editing) {
            $this->setRelation('editing', $editing->load('user'));

            return;
        }

        DB::transaction(function () use ($userId, $user): void {
            $editing = $this->lockEditingRecord();

            if (! $editing) {
                $editing = $this->createEditingRecord($userId) ?? $this->lockEditingRecord();
            }

            if (! $editing) {
                throw new \RuntimeException('Unable to create or load the editing record.');
            }

            if ($editing->expiration->isPast() || (string) $editing->user_id === (string) $userId) {
                $editing->forceFill([
                    'user_id' => $userId,
                    'expiration' => $this->freshEditingExpiration(),
                ])->save();

                $this->setRelation('editing', $editing->fresh('user'));

                return;
            }

            $previousUser = $editing->user;

            $editing->forceFill([
                'user_id' => $userId,
                'expiration' => $this->freshEditingExpiration(),
            ])->save();

            $editing->load('user');
            $this->setRelation('editing', $editing);

            EditingTakenOver::dispatch($this, $editing, $user, $previousUser);
        });
    }

    public function scopeWithActiveEditor(EloquentBuilder $query, bool $excludeCurrentUser = true): EloquentBuilder
    {
        $editingTable = EditingByConfig::editingTable();
        $userTable = EditingByConfig::userTable();
        $userKeyName = EditingByConfig::userKeyName();
        $qualifiedKey = $this->qualifyColumn($this->getKeyName());
        $qualifiedKeyAsString = EditingByConfig::itemKeyAsStringExpression($query->getModel()->getConnection(), $qualifiedKey);
        $fullNameExpression = EditingByConfig::fullNameExpression($query->getModel()->getConnection(), 'active_model_editing_users');
        $alias = 'active_model_editings';
        $usersAlias = 'active_model_editing_users';

        $query->leftJoin("{$editingTable} as {$alias}", function ($join) use ($alias, $qualifiedKeyAsString, $excludeCurrentUser) {
            $join->whereRaw("{$alias}.item_id = {$qualifiedKeyAsString}")
                ->where("{$alias}.item_type", '=', $this->getMorphClass())
                ->where("{$alias}.expiration", '>', Carbon::now());

            if ($excludeCurrentUser && Auth::id() !== null) {
                $join->where("{$alias}.user_id", '!=', Auth::id());
            }
        });

        $query->leftJoin("{$userTable} as {$usersAlias}", "{$usersAlias}.{$userKeyName}", '=', "{$alias}.user_id");

        return $query->addSelect([
            DB::raw("{$alias}.user_id as editing_by_user_id"),
            DB::raw("{$usersAlias}.name as editing_by_name"),
            DB::raw("{$usersAlias}.surname as editing_by_surname"),
            DB::raw("{$usersAlias}.email as editing_by_email"),
            DB::raw("{$fullNameExpression} as editing_by_fullname"),
        ]);
    }

    protected function freshEditingExpiration(): Carbon
    {
        return Carbon::now()->addSeconds($this->editingTtlSeconds());
    }

    protected function editingTtlSeconds(): int
    {
        return property_exists($this, 'editingTtlSeconds')
            ? (int) $this->editingTtlSeconds
            : (int) config('editing-by.default_ttl_seconds', 20);
    }

    protected function editingRecordQuery(): EloquentBuilder
    {
        return Editing::query()
            ->where('item_type', $this->getMorphClass())
            ->where('item_id', (string) $this->getKey());
    }

    protected function lockEditingRecord(): ?Editing
    {
        /** @var Editing|null $editing */
        $editing = $this->editingRecordQuery()
            ->lockForUpdate()
            ->with('user')
            ->first();

        return $editing;
    }

    protected function createEditingRecord(int|string $userId): ?Editing
    {
        for ($attempt = 1; $attempt <= $this->editingWriteAttempts(); $attempt++) {
            try {
                return $this->editing()->create([
                    'user_id' => $userId,
                    'expiration' => $this->freshEditingExpiration(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return null;
            } catch (QueryException $exception) {
                if (! $this->isRetryableEditingWriteException($exception) || $attempt === $this->editingWriteAttempts()) {
                    throw $exception;
                }
            }
        }

        return null;
    }

    protected function editingWriteAttempts(): int
    {
        return 3;
    }

    protected function isRetryableEditingWriteException(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return in_array($code, ['1205', '1213', '40001'], true)
            || str_contains($message, 'deadlock found')
            || str_contains($message, 'lock wait timeout exceeded')
            || str_contains($message, 'database is locked');
    }
}
