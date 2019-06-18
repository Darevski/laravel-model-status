<?php

namespace Spatie\ModelStatus;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Spatie\ModelStatus\Events\StatusUpdated;
use Spatie\ModelStatus\Exceptions\InvalidSetting;
use Spatie\ModelStatus\Exceptions\InvalidStatus;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait HasStatuses
{
    public function statuses(): MorphMany
    {
        return $this->morphMany($this->getStatusModelClassName(), 'model', 'model_type', $this->getModelKeyColumnName())
            ->latest('id');
    }

    public function status(string $name): ?Status
    {
        return $this->latestStatus($name);
    }


    public function scopeCurrentStatus(Builder $builder, $name, array $values)
    {
        $builder
            ->whereHas(
                'statuses',
                function (Builder $query) use ($name, $values) {
                    $query
                        ->where('name', $name)
                        ->whereIn('value', $values)
                        ->whereIn(
                            'id',
                            function (QueryBuilder $query) {
                                $query
                                    ->select(DB::raw('id'))
                                    ->from($this->getStatusTableName())
                                    ->where('model_type', $this->getStatusModelType())
                                    ->whereColumn($this->getModelKeyColumnName(), $this->getQualifiedKeyName());
                            }
                        );
                }
            );
    }

    public function setStatuses(array $statuses){
        foreach ($statuses as $key => $value){
            $this->setStatus($key,$value);
        }
    }

    public function setStatus(string $name, ?string $value = null): self
    {
        if (! $this->isValidStatus($name, $value)) {
            throw InvalidStatus::create($name);
        }

        return $this->forceSetStatus($name, $value);
    }

    public function isValidStatus(string $name, ?string $value = null): bool
    {
        return true;
    }

    public function latestStatus(string $name): ?Status
    {
        return $this->statuses()->where('name', $name)->first();
    }

    public function forceSetStatus(string $name, ?string $value = null): self
    {
        $oldStatus = $this->latestStatus($name);

        $newStatus = $this->statuses()->updateOrCreate([
            'name'   => $name,
        ],[
            'value' => $value,
        ]);

        event(new StatusUpdated($oldStatus, $newStatus, $this));

        return $this;
    }

    protected function getStatusTableName(): string
    {
        $modelClass = $this->getStatusModelClassName();

        return (new $modelClass)->getTable();
    }

    protected function getModelKeyColumnName(): string
    {
        return config('model-status.model_primary_key_attribute') ?? 'model_id';
    }

    protected function getStatusModelClassName(): string
    {
        return config('model-status.status_model');
    }

    protected function getStatusModelType(): string
    {
        return array_search(static::class, Relation::morphMap()) ?: static::class;
    }
}
