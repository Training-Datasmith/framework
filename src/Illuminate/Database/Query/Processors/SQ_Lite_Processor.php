<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Processors;

class Sq_Lite_Processor extends Processor
{
    /** @inheritDoc */
    public function process_columns($results, $sql = ''): array
    {
        $has_primary_key = array_sum(array_column($results, 'primary')) === 1;
        return array_map(function (array $result) use ($has_primary_key, $sql): array {
            $result = (object) $result;
            $type = strtolower((string) $result->type);
            $safe_name = preg_quote((string) $result->name, '/');
            $collation = preg_match('/\b' . $safe_name . '\b[^,(]+(?:\([^()]+\)[^,]*)?(?:(?:default|check|as)\s*(?:\(.*?\))?[^,]*)*collate\s+["\'`]?(\w+)/i', (string) $sql, $matches) === 1 ? strtolower($matches[1]) : null;
            $is_generated = in_array($result->extra, [2, 3]);
            $expression = $is_generated && preg_match('/\b' . $safe_name . '\b[^,]+\s+as\s+\(((?:[^()]+|\((?:[^()]+|\([^()]*\))*\))*)\)/i', (string) $sql, $matches) === 1 ? $matches[1] : null;
            return ['name' => $result->name, 'type_name' => strtok($type, '(') ?: '', 'type' => $type, 'collation' => $collation, 'nullable' => (bool) $result->nullable, 'default' => $result->default, 'auto_increment' => $has_primary_key && $result->primary && $type === 'integer', 'comment' => null, 'generation' => $is_generated ? ['type' => match ((int) $result->extra) {
                3 => 'stored',
                2 => 'virtual',
                default => null,
            }, 'expression' => $expression] : null];
        }, $results);
    }
    /** @inheritDoc
     * @return mixed[] */
    public function process_indexes($results): array
    {
        $primary_count = 0;
        $indexes = array_map(function (array $result) use (&$primary_count): array {
            $result = (object) $result;
            if ($is_primary = (bool) $result->primary) {
                $primary_count += 1;
            }
            return ['name' => strtolower((string) $result->name), 'columns' => $result->columns ? explode(',', (string) $result->columns) : [], 'type' => null, 'unique' => (bool) $result->unique, 'primary' => $is_primary];
        }, $results);
        if ($primary_count > 1) {
            return array_filter($indexes, fn(array $index): bool => $index['name'] !== 'primary');
        }
        return $indexes;
    }
    /** @inheritDoc */
    public function process_foreign_keys($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            return ['name' => null, 'columns' => explode(',', (string) $result->columns), 'foreign_schema' => $result->foreign_schema, 'foreign_table' => $result->foreign_table, 'foreign_columns' => explode(',', (string) $result->foreign_columns), 'on_update' => strtolower((string) $result->on_update), 'on_delete' => strtolower((string) $result->on_delete)];
        }, $results);
    }
}