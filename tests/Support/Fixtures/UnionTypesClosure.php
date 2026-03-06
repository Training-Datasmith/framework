<?php

declare(strict_types=1);

use Illuminate\Tests\Support\AnotherExampleParameter;
use Illuminate\Tests\Support\ExampleParameter;

return function (ExampleParameter|AnotherExampleParameter $a, $b) {

};
