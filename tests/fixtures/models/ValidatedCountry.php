<?php namespace Winter\Translate\Tests\Fixtures\Models;

/**
 * ValidatedCountry Model
 */
class ValidatedCountry extends Country
{
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * @var array Validation rules
     */
    public $rules = [];
}
