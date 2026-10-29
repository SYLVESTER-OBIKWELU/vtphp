<?php

declare(strict_types=1);

namespace VtPhp\Queue;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores Eloquent models as "class + id" when the object is serialized (put
 * on the queue) and loads them fresh from the database when it comes back,
 * so a job never works on stale data or carries a whole row in its payload.
 */
trait SerializesModels
{
    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $values = [];

        foreach ((new \ReflectionObject($this))->getProperties() as $property) {
            if ($property->isStatic() || !$property->isInitialized($this)) {
                continue;
            }

            $values[$property->getDeclaringClass()->getName()."\0".$property->getName()] = self::packModels($property->getValue($this));
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            [$class, $name] = explode("\0", $key, 2);

            // Bound to the declaring class so private and readonly
            // properties can be filled in too.
            \Closure::bind(function (mixed $value) use ($name): void {
                $this->{$name} = $value;
            }, $this, $class)(self::unpackModels($value));
        }
    }

    private static function packModels(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return new ModelIdentifier($value::class, $value->getKey());
        }

        if ($value instanceof EloquentCollection && $value->isNotEmpty()) {
            return new ModelIdentifier($value->first()::class, $value->modelKeys(), collection: true);
        }

        return $value;
    }

    private static function unpackModels(mixed $value): mixed
    {
        if (!$value instanceof ModelIdentifier) {
            return $value;
        }

        /** @var class-string<Model> $class */
        $class = $value->class;

        if (!$value->collection) {
            // Throws ModelNotFoundException if the row was deleted meanwhile.
            return $class::query()->findOrFail($value->id);
        }

        /** @var array<int, int|string> $ids */
        $ids = $value->id;
        $models = $class::query()->findMany($ids)->keyBy(fn (Model $model): mixed => $model->getKey());

        // Keep the original order; rows deleted meanwhile are left out.
        return new EloquentCollection(array_values(array_filter(array_map(fn (int|string $id): ?Model => $models->get($id), $ids))));
    }
}
