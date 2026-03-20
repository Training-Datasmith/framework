<?php

declare (strict_types=1);
namespace Illuminate\Container;

use Exception;
use Psr\Container\Not_Found_Exception_Interface;
class Entry_Not_Found_Exception extends Exception implements Not_Found_Exception_Interface
{
}