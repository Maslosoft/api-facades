<?php

namespace Maslosoft\ApiFacades\Support;

final class Tpl
{
    public static function render(string $tpl, array $vars): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_.]+)\s*}}/', function ($m) use ($vars)
        {
            $keys = explode('.', $m[1]);
            $v = $vars;
            foreach ($keys as $k)
            {
                $v = is_array($v) && array_key_exists($k, $v) ? $v[$k] : '';
            }
            return is_string($v) ? $v : '';
        }, $tpl);
    }
}