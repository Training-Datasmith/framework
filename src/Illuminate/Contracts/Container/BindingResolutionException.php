<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Container;

use Exception;
use Psr\Container\Container_Exception_Interface;
class Binding_Resolution_Exception extends Exception implements Container_Exception_Interface
{
}