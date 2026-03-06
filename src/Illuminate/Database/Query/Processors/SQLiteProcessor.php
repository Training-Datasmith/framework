<?php

declare(strict_types=1);

namespace Illuminate\Database\Query\Processors;

class SQLiteProcessor extends Processor
{
    /** @inheritDoc */
    public function processColumns($results, $sql = ''): array
    {
        $hasPrimaryKey = array_sum(array_column($results, 'primary')) === 1;

        return array_map(function (array $result) use ($hasPrimaryKey, $sql): array {
            $result = (object) $result;

            $type = strtolower((string) $result->type);

            $safeName = preg_quote((string) $result->name, '/');

            $collation = preg_match(
                '/\b'.$safeName.'\b[^,(]+(?:\([^()]+\)[^,]*)?(?:(?:default|check|as)\s*(?:\(.*?\))?[^,]*)*collate\s+["\'`]?(\w+)/i',
                (string) $sql,
                $matches
            ) === 1 ? strtolower($matches[1]) : null;

            $isGenerated = in_array($result->extra, [2, 3]);

            $expression = $isGenerated && preg_match(
                '/\b'.$safeName.'\b[^,]+\s+as\s+\(((?:[^()]+|\((?:[^()]+|\([^()]*\))*\))*)\)/i',
                (string) $sql,
                $matches
            ) === 1 ? $matches[1] : null;

            return [
                'name' => $result->name,
                'type_name' => strtok($type, '(') ?: '',
                'type' => $type,
                'collation' => $collation,
                'nullable' => (bool) $result->nullable,
                'default' => $result->default,
                'auto_increment' => $hasPrimaryKey && $result->primary && $type === 'integer',
                'comment' => null,
                'generation' => $isGenerated ? [
                    'type' => match ((int) $result->extra) {
                        3 => 'stored',
                        2 => 'virtual',
                        default => null,
                    },
                    'expression' => $expression,
                ] : null,
            ];
        }, $results);
    }

    /** @inheritDoc
     * @return mixed[] */
    public function processIndexes($results): array
    {
        $primaryCount = 0;

        $indexes = array_map(function (array $result) use (&$primaryCount): array {
            $result = (object) $result;

            if ($isPrimary = (bool) $result->primary) {
                $primaryCount += 1;
            }

            return [
                'name' => strtolower((string) $result->name),
                'columns' => $result->columns ? explode(',', (string) $result->columns) : [],
                'type' => null,
                'unique' => (bool) $result->unique,
                'primary' => $isPrimary,
            ];
        }, $results);

        if ($primaryCount > 1) {
            return array_filter($indexes, fn (array $index): bool => $index['name'] !== 'primary');
        }

        return $indexes;
    }

    /** @inheritDoc */
    public function processForeignKeys($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;

            return [
                'name' => null,
                'columns' => explode(',', (string) $result->columns),
                'foreign_schema' => $result->foreign_schema,
                'foreign_table' => $result->foreign_table,
                'foreign_columns' => explode(',', (string) $result->foreign_columns),
                'on_update' => strtolower((string) $result->on_update),
                'on_delete' => strtolower((string) $result->on_delete),
            ];
        }, $results);
    }
}
