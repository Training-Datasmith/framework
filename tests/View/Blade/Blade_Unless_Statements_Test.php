<?php

declare(strict_types=1);

namespace Illuminate\Tests\View\Blade;

class BladeUnlessStatementsTest extends AbstractBladeTestCase
{
    public function testUnlessStatementsAreCompiled()
    {
        $string = '@unless (name(foo(bar)))
breeze
@endunless';
        $expected = '<?php if (! (name(foo(bar)))): ?>
breeze
<?php endif; ?>';
        $this->assertEquals($expected, $this->compiler->compileString($string));
    }
}
