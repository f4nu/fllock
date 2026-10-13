<?php

namespace F4nu\Fllock\Models\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * The lock row, keyed on the string form of the record's key.
 *
 * `lockable_id` is a string column so uuid, ulid and integer keys can share one
 * table. Eloquent does not know that: for an integer key it eager loads with
 * `whereIntegerInRaw`, which writes `lockable_id in (1, 2)` straight into the
 * SQL. MySQL and SQLite compare a string to an integer by casting it; Postgres
 * refuses (`operator does not exist: character varying = integer`), so every
 * table page that eager loads locks dies there.
 *
 * Binding the keys as strings makes every path -- lazy load, eager load,
 * create -- compare a string to a string, on every database.
 */
class RecordLockRelation extends MorphOne {
    public function getParentKey() {
        $key = parent::getParentKey();

        return $key === null ? null : (string) $key;
    }

    protected function whereInMethod(Model $model, $key) {
        return 'whereIn';
    }

    protected function getKeys(array $models, $key = null) {
        return array_map('strval', parent::getKeys($models, $key));
    }
}
