<?php

declare(strict_types=1);

namespace Illuminate\Container;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class EntryNotFoundException extends Exception implements NotFoundExceptionInterface
{
}
