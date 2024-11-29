<?php

/**
 * This file will be autoloaded by Composer. 
 * @see composer.json > autoload > files
 */

declare(strict_types=1);

if (!function_exists('env')) {
    /**
     * Get env value
     *
     * @param string $name Name of the env variable
     * @param mixed $fallback Fallback value to return if variable not found
     * @return mixed
     */
    function env(string $name, $fallback = null)
    {
        return array_key_exists($name, $_ENV) ? $_ENV[$name] : $fallback;
    }
}

if (!function_exists('safe_json_encode')) {
    function safe_json_encode($data)
    {
        if (is_string($data)) {
            if (!mb_check_encoding($data, 'UTF-8')) {
                $data = mb_convert_encoding($data, 'UTF-8', 'auto');
            }
        } elseif (is_array($data) || is_object($data)) {
            array_walk_recursive($data, function (&$item, $key) {
                if (is_string($item) && !mb_check_encoding($item, 'UTF-8')) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'auto');
                }
            });
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
