<?php

declare (strict_types=1);
namespace Illuminate\Http\Concerns;

use Illuminate\Support\Str;
trait Interacts_With_Content_Types
{
    /**
     * Determine if the request is sending JSON.
     */
    public function is_json(): bool
    {
        return Str::contains($this->header('CONTENT_TYPE') ?? '', ['/json', '+json']);
    }
    /**
     * Determine if the current request probably expects a JSON response.
     */
    public function expects_json(): bool
    {
        if ($this->ajax() && !$this->pjax() && $this->accepts_any_content_type()) {
            return true;
        }
        return (bool) $this->wants_json();
    }
    /**
     * Determine if the current request is asking for JSON.
     */
    public function wants_json(): bool
    {
        $acceptable = $this->get_acceptable_content_types();
        return isset($acceptable[0]) && Str::contains(strtolower($acceptable[0]), ['/json', '+json']);
    }
    /**
     * Determines whether the current requests accepts a given content type.
     *
     * @param  string|array  $contentTypes
     */
    public function accepts($content_types): bool
    {
        $accepts = $this->get_acceptable_content_types();
        if (count($accepts) === 0) {
            return true;
        }
        $types = (array) $content_types;
        foreach ($accepts as $accept) {
            if ($accept && $pos = strpos((string) $accept, ';')) {
                $accept = trim(substr((string) $accept, 0, $pos));
            }
            if ($accept === '*/*' || $accept === '*') {
                return true;
            }
            foreach ($types as $type) {
                $accept = strtolower((string) $accept);
                $type = strtolower((string) $type);
                if ($this->matches_type($accept, $type) || $accept === strtok($type, '/') . '/*') {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Return the most suitable content type from the given array based on content negotiation.
     *
     * @param  string|array  $contentTypes
     * @return string|null
     */
    public function prefers($content_types)
    {
        $accepts = $this->get_acceptable_content_types();
        $content_types = (array) $content_types;
        foreach ($accepts as $accept) {
            if ($accept && $pos = strpos((string) $accept, ';')) {
                $accept = trim(substr((string) $accept, 0, $pos));
            }
            if (in_array($accept, ['*/*', '*'])) {
                return $content_types[0];
            }
            foreach ($content_types as $content_type) {
                $type = $content_type;
                if (!is_null($mime_type = $this->get_mime_type($content_type))) {
                    $type = $mime_type;
                }
                $accept = strtolower((string) $accept);
                $type = strtolower((string) $type);
                if ($this->matches_type($type, $accept) || $accept === strtok($type, '/') . '/*') {
                    return $content_type;
                }
            }
        }
    }
    /**
     * Determine if the current request accepts any content type.
     */
    public function accepts_any_content_type(): bool
    {
        $acceptable = $this->get_acceptable_content_types();
        return count($acceptable) === 0 || isset($acceptable[0]) && ($acceptable[0] === '*/*' || $acceptable[0] === '*');
    }
    /**
     * Determines whether a request accepts JSON.
     *
     * @return bool
     */
    public function accepts_json()
    {
        return $this->accepts('application/json');
    }
    /**
     * Determines whether a request accepts HTML.
     *
     * @return bool
     */
    public function accepts_html()
    {
        return $this->accepts('text/html');
    }
    /**
     * Determine if the given content types match.
     *
     * @param  string  $actual
     * @param  string  $type
     * @return bool
     */
    public static function matches_type($actual, $type)
    {
        if ($actual === $type) {
            return true;
        }
        $split = explode('/', $actual);
        return isset($split[1]) && preg_match('#' . preg_quote($split[0], '#') . '/.+\+' . preg_quote($split[1], '#') . '#', $type);
    }
    /**
     * Get the data format expected in the response.
     *
     * @param  string  $default
     * @return string
     */
    public function format($default = 'html')
    {
        foreach ($this->get_acceptable_content_types() as $type) {
            if ($format = $this->get_format($type)) {
                return $format;
            }
        }
        return $default;
    }
}