<?php

namespace CoodexEs\LaravelEditingBy\Concerns;

use CoodexEs\LaravelEditingBy\Events\EditingTakenOver;
use CoodexEs\LaravelEditingBy\Exceptions\ModelIsBeingEditedException;
use CoodexEs\LaravelEditingBy\Models\Editing;
use CoodexEs\LaravelEditingBy\Support\EditingByConfig;
use Illuminate\Database\Eloquent\Builder;
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

        DB::transaction(function () use ($userId): void {
            $editing = Editing::query()
                ->where('item_type', $this->getMorphClass())
                ->where('item_id', (string) $this->getKey())
                ->lockForUpdate()
                ->with('user')
                ->first();

            if ($editing && $editing->expiration->isPast()) {
                $editing->delete();
                $editing = null;
            }

            if ($editing && (string) $editing->user_id !== (string) $userId) {
                throw new ModelIsBeingEditedException($editing);
            }

            if ($editing) {
                $editing->forceFill([
                    'expiration' => $this->freshEditingExpiration(),
                ])->save();

                $this->setRelation('editing', $editing->fresh('user'));

                return;
            }

            $editing = $this->editing()->create([
                'user_id' => $userId,
                'expiration' => $this->freshEditingExpiration(),
            ]);

            $this->setRelation('editing', $editing->load('user'));
        });
    }

    public function addEditingTime(): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            throw new \RuntimeException('addEditingTime requires an authenticated user.');
        }

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

        DB::transaction(function () use ($userId, $user): void {
            $editing = Editing::query()
                ->where('item_type', $this->getMorphClass())
                ->where('item_id', (string) $this->getKey())
                ->lockForUpdate()
                ->with('user')
                ->first();

            if ($editing && $editing->expiration->isPast()) {
                $editing->delete();
                $editing = null;
            }

            $previousUser = $editing?->user;

            if (! $editing) {
                $created = $this->editing()->create([
                    'user_id' => $userId,
                    'expiration' => $this->freshEditingExpiration(),
                ])->load('user');

                $this->setRelation('editing', $created);

                return;
            }

            if ((string) $editing->user_id === (string) $userId) {
                $editing->forceFill([
                    'expiration' => $this->freshEditingExpiration(),
                ])->save();

                $this->setRelation('editing', $editing->fresh('user'));

                return;
            }

            $editing->forceFill([
                'user_id' => $userId,
                'expiration' => $this->freshEditingExpiration(),
            ])->save();

            $editing->load('user');
            $this->setRelation('editing', $editing);

            EditingTakenOver::dispatch($this, $editing, $user, $previousUser);
        });
    }

    public function scopeWithActiveEditor(Builder $query, bool $excludeCurrentUser = true): Builder
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
            DB::raw("{$fullNameExpression} as locked_by"),
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
}
