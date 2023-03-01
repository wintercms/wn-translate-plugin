<?php

namespace Winter\Translate\Models;

use Model;

/**
 * Attribute Model
 */
class Attribute extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_translate_attributes';

    public $morphTo = [
        'model' => []
    ];
}
