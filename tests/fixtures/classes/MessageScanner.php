<?php namespace Winter\Translate\Tests\Fixtures\Classes;

use Winter\Translate\Classes\ThemeScanner;

class MessageScanner extends ThemeScanner
{
    public function countMatches($string)
    {
        return count($this->processStandardTags($string));
    }
}
