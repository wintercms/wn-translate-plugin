<?php
use Winter\Translate\Models\Message;

if (!function_exists('t')) {
    function t($string, $params = [], $locale = null) {
        return Message::trans($string, $params, $locale);
    }
}

if (!function_exists('t_choice')) {
    function t_choice($string, $count = 0, $params = [], $locale = null) {
        return Lang::choice(Message::trans($string, $params, $locale), $count, $params);
    }
}
