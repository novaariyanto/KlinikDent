<?php

namespace App\Support\Cms;

final class HtmlSanitizer
{
    /**
     * Allowlist-based sanitizer for CMS HTML body.
     */
    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|link|meta)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|link|meta)[^>]*/?>#is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1="#"', $html) ?? $html;

        $allowed = 'p|br|hr|h2|h3|h4|h5|h6|strong|b|em|i|u|s|ul|ol|li|blockquote|pre|code|table|thead|tbody|tr|th|td|a|img|figure|figcaption|span|div';
        $html = preg_replace_callback('/<\/?([a-z0-9]+)(\s[^>]*)?>/i', function ($m) use ($allowed) {
            $tag = strtolower($m[1]);
            if (! preg_match('/^('.$allowed.')$/', $tag)) {
                return '';
            }

            if ($m[0][1] === '/') {
                return '</'.$tag.'>';
            }

            $attrs = $m[2] ?? '';
            $safe = '';
            if (preg_match_all('/([a-z0-9:-]+)\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $am, PREG_SET_ORDER)) {
                foreach ($am as $attr) {
                    $name = strtolower($attr[1]);
                    $value = $attr[3] !== '' ? $attr[3] : $attr[4];
                    if ($tag === 'a' && in_array($name, ['href', 'title', 'rel', 'target'], true)) {
                        if ($name === 'href' && preg_match('/^\s*javascript:/i', $value)) {
                            continue;
                        }
                        if ($name === 'target' && $value !== '_blank') {
                            continue;
                        }
                        $safe .= ' '.$name.'="'.e($value).'"';
                        if ($name === 'target') {
                            $safe .= ' rel="noopener noreferrer"';
                        }
                    } elseif ($tag === 'img' && in_array($name, ['src', 'alt', 'title', 'width', 'height', 'loading'], true)) {
                        if ($name === 'src' && preg_match('/^\s*javascript:/i', $value)) {
                            continue;
                        }
                        $safe .= ' '.$name.'="'.e($value).'"';
                    } elseif (in_array($name, ['id', 'class'], true)) {
                        $safe .= ' '.$name.'="'.e($value).'"';
                    }
                }
            }

            return '<'.$tag.$safe.'>';
        }, $html) ?? $html;

        return trim($html);
    }
}
