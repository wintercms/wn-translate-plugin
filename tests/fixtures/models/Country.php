<?php namespace Winter\Translate\Tests\Fixtures\Models;

use Model;

/**
 * Country Model
 */
class Country extends Model
{
    public $implement = ['@Winter.Translate.Behaviors.TranslatableModel'];

    public $translatable = [['name', 'index' => true], 'states'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'translate_test_countries';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Jsonable fields
     */
    protected $jsonable = ['states'];
}
