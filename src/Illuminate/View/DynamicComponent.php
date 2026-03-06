<?php

declare(strict_types=1);

namespace Illuminate\View;

use BackedEnum;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;

use function Illuminate\Support\enum_value;

use Illuminate\Support\Str;

use Illuminate\View\Compilers\ComponentTagCompiler;

class DynamicComponent extends Component
{
    /**
     * The name of the component.
     *
     * @var string
     */
    public $component;

    /**
     * The component tag compiler instance.
     *
     * @var \Illuminate\View\Compilers\BladeTagCompiler
     */
    protected static $compiler;

    /**
     * The cached component classes.
     *
     * @var array
     */
    protected static $componentClasses = [];

    /**
     * Create a new component instance.
     */
    public function __construct(BackedEnum|string $component)
    {
        $this->component = (string) enum_value($component);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|string
     */
    public function render()
    {
        $template = <<<'EOF'
<?php extract((new \Illuminate\Support\Collection($attributes->getAttributes()))->mapWithKeys(function ($value, $key) { return [Illuminate\Support\Str::camel(str_replace([':', '.'], ' ', $key)) => $value]; })->all(), EXTR_SKIP); ?>
{{ props }}
<x-{{ component }} {{ bindings }} {{ attributes }}>
{{ slots }}
{{ defaultSlot }}
</x-{{ component }}>
EOF;

        return function (array $data) use ($template): string {
            $bindings = $this->bindings($class = $this->classForComponent());

            return str_replace(
                [
                    '{{ component }}',
                    '{{ props }}',
                    '{{ bindings }}',
                    '{{ attributes }}',
                    '{{ slots }}',
                    '{{ defaultSlot }}',
                ],
                [
                    $this->component,
                    $this->compileProps($bindings),
                    $this->compileBindings($bindings),
                    class_exists($class) ? '{{ $attributes }}' : '',
                    $this->compileSlots($data['__laravel_slots']),
                    '{{ $slot ?? "" }}',
                ],
                $template
            );
        };
    }

    /**
     * Compile the @props directive for the component.
     */
    protected function compileProps(array $bindings): string
    {
        if (empty($bindings)) {
            return '';
        }

        return '@props('.'[\''.implode('\',\'', (new Collection($bindings))->map(fn (string $dataKey) => Str::camel($dataKey))->all()).'\']'.')';
    }

    /**
     * Compile the bindings for the component.
     */
    protected function compileBindings(array $bindings): string
    {
        return (new Collection($bindings))
            ->map(fn ($key): string => ':'.$key.'="$'.Str::camel(str_replace([':', '.'], ' ', $key)).'"')
            ->implode(' ');
    }

    /**
     * Compile the slots for the component.
     */
    protected function compileSlots(array $slots): string
    {
        return (new Collection($slots))
            ->map(fn ($slot, $name) => $name === '__default' ? null : '<x-slot name="'.$name.'" '.($slot->attributes).'>{{ $'.$name.' }}</x-slot>')
            ->filter()
            ->implode(PHP_EOL);
    }

    /**
     * Get the class for the current component.
     *
     * @return string
     */
    protected function classForComponent()
    {
        return static::$componentClasses[$this->component] ?? static::$componentClasses[$this->component] =
                    $this->compiler()->componentClass($this->component);
    }

    /**
     * Get the names of the variables that should be bound to the component.
     */
    protected function bindings(string $class): array
    {
        [$data] = $this->compiler()->partitionDataAndAttributes($class, $this->attributes->getAttributes());

        return array_keys($data->all());
    }

    /**
     * Get an instance of the Blade tag compiler.
     *
     * @return \Illuminate\View\Compilers\ComponentTagCompiler
     */
    protected function compiler()
    {
        if (! static::$compiler) {
            static::$compiler = new ComponentTagCompiler(
                Container::getInstance()->make('blade.compiler')->getClassComponentAliases(),
                Container::getInstance()->make('blade.compiler')->getClassComponentNamespaces(),
                Container::getInstance()->make('blade.compiler')
            );
        }

        return static::$compiler;
    }
}
