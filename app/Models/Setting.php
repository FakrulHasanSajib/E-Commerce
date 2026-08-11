<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_public',
    ];

    protected $attributes = [
        'type' => 'string',
        'is_public' => false,
    ];

    /**
     * Accessor for value to safely handle scalar strings, numbers, booleans, and JSON arrays.
     */
    public function getValueAttribute($value)
    {
        if (is_null($value)) return null;
        if (is_bool($value) || is_numeric($value)) return $value;
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $value;
    }

    /**
     * Mutator for value.
     */
    public function setValueAttribute($value)
    {
        if (is_array($value) || is_object($value)) {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }
}
