<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Container;

use Exception;
use Psr\Container\Container_Exception_Interface;
class Circular_Dependency_Exception extends Exception implements Container_Exception_Interface
{
}