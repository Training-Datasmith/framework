<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Foundation\Concerns\Resolves_Dump_Source;
use Symfony\Component\Console\Output\Console_Output;
use Symfony\Component\Var_Dumper\Caster\Reflection_Caster;
use Symfony\Component\Var_Dumper\Cloner\Data;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
use Symfony\Component\Var_Dumper\Dumper\Cli_Dumper as BaseCliDumper;
use Symfony\Component\Var_Dumper\Var_Dumper;
class Cli_Dumper extends Base_Cli_Dumper
{
    use Resolves_Dump_Source;
    /**
     * If the dumper is currently dumping.
     *
     * @var bool
     */
    protected $dumping = false;
    /**
     * Create a new CLI dumper instance.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string  $basePath
     * @param  string  $compiledViewPath
     */
    public function __construct(
        /**
         * The output instance.
         */
        protected $output,
        /**
         * The base path of the application.
         */
        protected $base_path,
        /**
         * The compiled view path for the application.
         */
        protected $compiled_view_path
    )
    {
        parent::__construct();
        $this->set_colors($this->supports_colors());
    }
    /**
     * Create a new CLI dumper instance and register it as the default dumper.
     *
     * @param  string  $basePath
     * @param  string  $compiledViewPath
     */
    public static function register($base_path, $compiled_view_path): void
    {
        $cloner = tap(new Var_Cloner())->add_casters(Reflection_Caster::UNSET_CLOSURE_FILE_INFO);
        $dumper = new static(new Console_Output(), $base_path, $compiled_view_path);
        Var_Dumper::set_handler(fn($value) => $dumper->dump_with_source($cloner->clone_var($value)));
    }
    /**
     * Dump a variable with its source file / line.
     */
    public function dump_with_source(Data $data): void
    {
        if ($this->dumping) {
            $this->dump($data);
            return;
        }
        $this->dumping = true;
        $output = (string) $this->dump($data, true);
        $lines = explode("\n", $output);
        $lines[array_key_last($lines) - 1] .= $this->get_dump_source_content();
        $this->output->write(implode("\n", $lines));
        $this->dumping = false;
    }
    /**
     * Get the dump's source console content.
     *
     * @return string
     */
    protected function get_dump_source_content()
    {
        if (is_null($dump_source = $this->resolve_dump_source())) {
            return '';
        }
        [$file, $relative_file, $line] = $dump_source;
        $href = $this->resolve_source_href($file, $line);
        return sprintf(' <fg=gray>// <fg=gray%s>%s%s</></>', is_null($href) ? '' : ";href={$href}", $relative_file, is_null($line) ? '' : ":{$line}");
    }
    /**
     * {@inheritDoc}
     */
    protected function supports_colors(): bool
    {
        return $this->output->is_decorated();
    }
}