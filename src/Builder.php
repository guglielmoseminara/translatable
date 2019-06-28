<?php namespace Laraplus\Data;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $updated = 0;

        $modelKey = $this->getModel()->getKey();
        $modelKeyName = $this->model->getKeyName();

        $values = $this->addUpdatedAtColumn($values);
        list($values, $i18nValues) = $this->filterValues($values);

        $ids = $modelKey ? [$modelKey] : $this->pluck($modelKeyName)->all();

        if($values) {
            $updated += $this->updateBase($values, $ids);
        }

        if($i18nValues) {
            $updated += $this->updateI18n($i18nValues, $ids);
        }

        return $updated;
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int  $amount
     * @param  array  $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->noTranslationsQuery()->increment($column, $amount, $extra);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int  $amount
     * @param  array  $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->noTranslationsQuery()->decrement($column, $amount, $extra);
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        list($values, $i18nValues) = $this->filterValues($values);
        if($this->query->insert($values)) {
            return $this->insertI18n($i18nValues, $values[$this->model->getKeyName()]);
        }
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        list($values, $i18nValues) = $this->filterValues($values);

        if($id = $this->query->insertGetId($values, $sequence)) {
            if($this->insertI18n($i18nValues, $id)) {
                return $id;
            }
        }

        return false;
    }

    /**
     * Delete a record from the database.
     *
     * @return mixed
     */
    public function delete()
    {
        if (isset($this->onDelete)) {
            return call_user_func($this->onDelete, $this);
        }

        return $this->i18nDeleteQuery()->delete() | $this->toBase()->delete();
    }

    /**
     * Run the default delete function on the builder.
     *
     * @return mixed
     */
    public function forceDelete()
    {
        return $this->i18nDeleteQuery(false)->delete() && $this->query->delete();
    }

    /**
     * Filters translatable values from non-translatable.
     *
     * @param array $values
     * @return array
     */
    protected function filterValues(array $values)
    {
        $attributes = $this->model->translatableAttributes();

        $translatable = [];

        foreach($attributes as $key) {
            if(array_key_exists($key, $values)) {
                $translatable[$key] = $values[$key];

                unset($values[$key]);
            }
        }
        $tmpValues = $values;
        foreach ($tmpValues as $key => $value) {
            if (strstr($key, ':') !== false) {
                [$attribute, $locale] = explode(':', $key);
                if(in_array($attribute, $attributes)) {
                    $translatable[$key] = $values[$key];
    
                    unset($values[$key]);
                }
            }
        }
        return [$values, $translatable];
    }

    protected function splitLanguages(array $values) {
        $attributes = collect($values);
        $translatables = $attributes->filter(function ($value, $key) {
            return strstr($key, ':') !== false ;
        });
        $localizedAttributes = $translatables->toArray();
        $attributes = $attributes->forget(array_keys($localizedAttributes))->toArray();
        $localizedValues = $attributes;
        $localizedValues['locales'] = [];
        foreach ($localizedAttributes as $ka => $va) {
            if (strstr($ka, ':') !== false) {
                [$attribute, $locale] = explode(':', $ka);
                if (!isset($localizedValues['locales'][$locale])) {
                    $localizedValues['locales'][$locale] = [];
                }
                $localizedValues['locales'][$locale][$attribute] = $va;    
            }
        }
        return $localizedValues;
    }

    /**
     * @param array $values
     * @param mixed $key
     * @return bool
     */
    protected function insertI18n(array $values, $key, $lang = null)
    {
        if(count($values) == 0) {
            return true;
        }

        $values = $this->splitLanguages($values);
        $locales = [];
        if (isset($values['locales'])) {
            $locales = $values['locales'];
            unset($values['locales']);
        }
        if (count($values) > 0) {
            $values[$this->model->getForeignKey()] = $key;
            $values[$this->model->getLocaleKey()] = $lang == null ? $this->model->getLocale() : $lang;
            $result = $this->i18nQuery()->insert($values);    
        }
        foreach ($locales as $lang => $value) {
            $value[$this->model->getForeignKey()] = $key;
            $value[$this->model->getLocaleKey()] = $lang;
            $result = $this->updateI18nItem($value, $key, $lang);
        }
        return $result;
    }

    /**
     * Update values in base table
     *
     * @param array $values
     * @param $ids
     * @return mixed
     */
    private function updateBase(array $values, array $ids)
    {
        $query = $this->model->newQuery()
            ->whereIn($this->model->getKeyName(), $ids)
            ->getQuery();

        return $query->update($values);
    }

    protected function updateI18nItem(array $values, $id, $lang = null)
    {
        $updated = 0;
        $query = $this->i18nQuery()
        ->whereOriginal($this->model->getForeignKey(), $id)
        ->whereOriginal($this->model->getLocaleKey(), $lang);

        if($query->exists()) {
            unset($values[$this->model->getLocaleKey()]);
            $updated += $query->update($values);
        } else {
            $updated += $this->insertI18n($values, $id, $lang);
        }
        return $updated;
    }

    /**
     * @param array $values
     * @param array $ids
     * @return bool
     */
    protected function updateI18n(array $values, array $ids)
    {
        if(count($values) == 0) {
            return true;
        }

        $values = $this->splitLanguages($values);
        $locales = [];
        if (isset($values['locales'])) {
            $locales = $values['locales'];
            unset($values['locales']);
        }
        if (count($values) > 0) {
            foreach($ids as $id) {
                $updated = $this->updateI18nItem($values, $id, $this->model->getLocale());
            }
        }
        foreach ($locales as $lang => $value) {
            $value[$this->model->getForeignKey()] = $key;
            $value[$this->model->getLocaleKey()] = $lang;
            $updated = $this->updateI18nItem($value, $key, $lang);
        }

        return $updated;
    }

    /**
     * Get the query builder instance for translation table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function i18nQuery()
    {
        $query = $this->getModel()->newQueryWithoutScopes()->getQuery();
        $query->from($this->model->getI18nTable());

        return $query;
    }

    /**
     * Get the delete query instance for translation table.
     *
     * @param bool $withGlobalScopes
     * @return \Illuminate\Database\Query\Builder
     */
    protected function i18nDeleteQuery($withGlobalScopes = true)
    {
        $subQuery = $withGlobalScopes ? $this->toBase() : $this->getQuery();
        $subQuery->select($this->model->getQualifiedKeyName());

        return $this->i18nQuery()->whereIn(
            $this->model->getForeignKey(), $subQuery->pluck($this->model->getKeyName())
        );
    }

    /**
     * Get the base query without translations
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function noTranslationsQuery()
    {
        return $this->withoutGlobalScope(TranslatableScope::class)->toBase();
    }
}
