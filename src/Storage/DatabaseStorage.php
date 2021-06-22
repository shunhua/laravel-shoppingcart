<?php

/*
 * This file is part of ibrand/laravel-shopping-cart.
 *
 * (c) iBrand <https://www.ibrand.cc>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace iBrand\Shoppingcart\Storage;

use DB;
use Illuminate\Support\Arr;
use iBrand\Shoppingcart\Item;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class DatabaseStorage.
 */
class DatabaseStorage implements Storage
{
    /**
     * @var string
     */
    private $table = 'shopping_cart';

    /**
     * @var array
     */
    private $filed = ['__raw_id', 'product_id', 'name', 'qty', 'price', 'total', '__model', 'type', 'status', 'parent_id'];

    /**
     * @param $key
     * @param $values
     */
    public function set($key, $values)
    {
        if (is_null($values)) {
            $this->forget($key);

            return;
        }

        $rawIds = $values->pluck('__raw_id')->toArray();

        //Delete the data that has been removed from cart.
        DB::table($this->table)->whereNotIn('__raw_id', $rawIds)->where('key', $key)->delete();

        $keys = explode('.', $key);

        $userId = end($keys);
        $guard = prev($keys);

        $values = $values->toArray();
        foreach ($values as $value) {
            $item = Arr::only($value, $this->filed);
            $attr = json_encode(Arr::except($value, $this->filed));
            $insert = array_merge($item, ['attributes' => $attr, 'key' => $key, 'guard' => $guard, 'user_id' => $userId]);
            if (DB::table($this->table)->where(['key' => $key, '__raw_id' => $item['__raw_id']])->first()) {
                DB::table($this->table)->where(['key' => $key, '__raw_id' => $item['__raw_id']])
                    ->update(Arr::except($insert, ['key', '__raw_id']));
            } else {
                DB::table($this->table)->insert($insert);
            }
        }
    }

    /**
     * @param $key
     * @param null $default
     *
     * @return Collection
     */
    public function get($key, $default = null)
    {
        $items = DB::table($this->table)->where('key', $key)->get();
        $items = $items->toArray();
        $collection = [];
        foreach ($items as $item) {
            $item = json_decode(json_encode($item), true);
            // $attr = json_decode($item['attributes'], true);
            // $item = Arr::only($item, $this->filed);
            // $item = array_merge($item, $attr);
            $collection[$item['__raw_id']] = new Item($item);
        }

        return new Collection($collection);
    }

    /**
     * @param $key
     */
    public function forget($key)
    {
        DB::table($this->table)->where('key', $key)->delete();
    }
}
