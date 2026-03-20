<?php

declare (strict_types=1);
namespace Illuminate\Database\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
trait Compiles_Json_Paths
{
    /**
     * Split the given JSON selector into the field and the optional path and wrap them separately.
     *
     * @param  string  $column
     */
    protected function wrap_json_field_and_path($column): array
    {
        $parts = explode('->', $column, 2);
        $field = $this->wrap($parts[0]);
        $path = count($parts) > 1 ? ', ' . $this->wrap_json_path($parts[1], '->') : '';
        return [$field, $path];
    }
    /**
     * Wrap the given JSON path.
     *
     * @param  string  $value
     * @param  string  $delimiter
     */
    protected function wrap_json_path($value, $delimiter = '->'): string
    {
        $value = preg_replace("/([\\\\]+)?\\'/", "''", $value);
        $json_path = (new Collection(explode($delimiter, (string) $value)))->map(fn($segment) => $this->wrap_json_path_segment($segment))->join('.');
        return "'\$" . (str_starts_with((string) $json_path, '[') ? '' : '.') . $json_path . "'";
    }
    /**
     * Wrap the given JSON path segment.
     */
    protected function wrap_json_path_segment(string $segment): string
    {
        if (preg_match('/(\[[^\]]+\])+$/', $segment, $parts)) {
            $key = Str::before_last($segment, $parts[0]);
            if (!empty($key)) {
                return '"' . $key . '"' . $parts[0];
            }
            return $parts[0];
        }
        return '"' . $segment . '"';
    }
}