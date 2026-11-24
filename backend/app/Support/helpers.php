<?php

use Illuminate\Database\Eloquent\Model;

if (! function_exists('fillable')) {
    /**
     * Keep only a model's mass-assignable keys. Validated request arrays often carry
     * extra control keys (items, addresses, delivery_ids…) handled by services.
     *
     * @param  class-string<Model>  $model
     */
    function fillable(string $model, array $data): array
    {
        static $cache = [];
        $cache[$model] ??= array_flip((new $model)->getFillable());

        return array_intersect_key($data, $cache[$model]);
    }
}
