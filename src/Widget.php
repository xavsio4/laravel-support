<?php

namespace FifteenPeas\Support;

/** The widget script and the tag that loads it. */
class Widget
{
    public static function path(): string
    {
        return __DIR__.'/../dist/support.js';
    }

    public static function version(): string
    {
        static $version;

        return $version ??= substr((string) md5_file(self::path()), 0, 12);
    }

    public static function url(): string
    {
        return route('support.widget', ['v' => self::version()]);
    }

    /**
     * The script tag, for the layout of signed-in pages. Options become data-
     * attributes: position, launcher, locale, accent, token, app_name.
     *
     * @param  array<string, string|null>  $options
     */
    public static function tag(array $options = []): string
    {
        $attributes = array_filter($options + [
            'app_name' => config('support.app_name'),
            'endpoint' => '/'.trim(config('support.routes.prefix'), '/'),
        ], fn ($value) => $value !== null && $value !== '');

        $html = '<script type="module" data-support src="'.e(self::url()).'"';

        foreach ($attributes as $name => $value) {
            $html .= ' data-'.e(str_replace('_', '-', $name)).'="'.e($value).'"';
        }

        return $html.'></script>';
    }
}
