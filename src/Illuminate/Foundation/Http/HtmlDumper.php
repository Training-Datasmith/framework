<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http;

use Illuminate\Foundation\Concerns\Resolves_Dump_Source;
use Symfony\Component\Var_Dumper\Caster\Reflection_Caster;
use Symfony\Component\Var_Dumper\Cloner\Data;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
use Symfony\Component\Var_Dumper\Dumper\Html_Dumper as BaseHtmlDumper;
use Symfony\Component\Var_Dumper\Var_Dumper;
class Html_Dumper extends Base_Html_Dumper
{
    use Resolves_Dump_Source;
    /**
     * Where the source should be placed on "expanded" kind of dumps.
     *
     * @var string
     */
    public const EXPANDED_SEPARATOR = 'class=sf-dump-expanded>';
    /**
     * Where the source should be placed on "non expanded" kind of dumps.
     *
     * @var string
     */
    public const NON_EXPANDED_SEPARATOR = "\n</pre><script>";
    /**
     * If the dumper is currently dumping.
     *
     * @var bool
     */
    protected $dumping = false;
    /**
     * Create a new HTML dumper instance.
     *
     * @param  string  $basePath
     * @param  string  $compiledViewPath
     */
    public function __construct(
        /**
         * The base path of the application.
         */
        protected $base_path,
        /**
         * The compiled view path of the application.
         */
        protected $compiled_view_path
    )
    {
        parent::__construct();
    }
    /**
     * Create a new HTML dumper instance and register it as the default dumper.
     *
     * @param  string  $basePath
     * @param  string  $compiledViewPath
     */
    public static function register($base_path, $compiled_view_path): void
    {
        $cloner = tap(new Var_Cloner())->add_casters(Reflection_Caster::UNSET_CLOSURE_FILE_INFO);
        $dumper = new static($base_path, $compiled_view_path);
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
        $output = match (true) {
            str_contains($output, static::EXPANDED_SEPARATOR) => str_replace(static::EXPANDED_SEPARATOR, static::EXPANDED_SEPARATOR . $this->get_dump_source_content(), $output),
            str_contains($output, static::NON_EXPANDED_SEPARATOR) => str_replace(static::NON_EXPANDED_SEPARATOR, $this->get_dump_source_content() . static::NON_EXPANDED_SEPARATOR, $output),
            default => $output,
        };
        fwrite($this->output_stream, $output);
        $this->dumping = false;
    }
    /**
     * Get the dump's source HTML content.
     *
     * @return string
     */
    protected function get_dump_source_content()
    {
        if (is_null($dump_source = $this->resolve_dump_source())) {
            return '';
        }
        [$file, $relative_file, $line] = $dump_source;
        $source = sprintf('%s%s', $relative_file, is_null($line) ? '' : ":{$line}");
        if ($href = $this->resolve_source_href($file, $line)) {
            $source = sprintf('<a href="%s">%s</a>', $href, $source);
        }
        return sprintf('<span style="color: #A0A0A0;"> // %s</span>', $source);
    }
}