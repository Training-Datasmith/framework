<?php

declare (strict_types=1);
namespace Illuminate\Console\Contracts;

interface New_Line_Aware
{
    /**
     * How many trailing newlines were written.
     *
     * @return int
     */
    public function new_lines_written();
    /**
     * Whether a newline has already been written.
     *
     * @return bool
     *
     * @deprecated use newLinesWritten
     */
    public function new_line_written();
}