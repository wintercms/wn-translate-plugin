<?php namespace Winter\Translate\Tests\Fixtures\Classes;

use Winter\Translate\Classes\ThemeScanner;

/**
 * Feature Model
 */
class MessageScanner extends ThemeScanner
{
    public function doesStringMatch($string)
    {
        return count($this->processStandardTags($string)) === 1;
    }
}
