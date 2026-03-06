<?php

namespace CoodexEs\LaravelEditingBy\Models;

use CoodexEs\LaravelEditingBy\Events\EditingReleased;
use CoodexEs\LaravelEditingBy\Events\EditingStarted;
use CoodexEs\LaravelEditingBy\Support\EditingByConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class Editing extends Model
{
    protected const SERIALIZED_USER_ATTRIBUTES = [
        'id',
        'name',
        'surname',
        'full_name',
        'email',
    ];

    protected $guarded = [];

    protected $casts = [
        'expiration' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Editing $editing): void {
            EditingStarted::dispatch($editing->item, $editing, $editing->user);
        });

        static::deleted(function (Editing $editing): void {
            EditingReleased::dispatch($editing->item, $editing, $editing->user);
        });
    }

    public function getTable()
    {
        return EditingByConfig::editingTable();
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(EditingByConfig::userModelClass(), 'user_id', EditingByConfig::userKeyName());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expiration', '>', Carbon::now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expiration', '<=', Carbon::now());
    }

    public function toArray(): array
    {
        $this->limitSerializedUser();

        return parent::toArray();
    }

    protected function limitSerializedUser(): void
    {
        if (! $this->relationLoaded('user') || ! $this->user) {
            return;
        }

        $this->setRelation('user', $this->user->setVisible(self::SERIALIZED_USER_ATTRIBUTES));
    }
}
