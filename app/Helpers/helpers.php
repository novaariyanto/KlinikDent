<?php

if (! function_exists('theme')) {
    function theme(string $path = ''): string
    {
        return asset('themes/skote/'.ltrim($path, '/'));
    }
}
