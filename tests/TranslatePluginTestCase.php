<?php namespace Winter\Translate\Tests;

if (class_exists('\System\Tests\Bootstrap\PluginTestCase')) {
    class BaseTestCase extends \System\Tests\Bootstrap\PluginTestCase
    {
    }
} else {
    class BaseTestCase extends \PluginTestCase
    {
    }
}

abstract class TranslatePluginTestCase extends BaseTestCase
{
    protected $refreshPlugins = [
        'Winter.Translate',
    ];
}
