<?php

declare (strict_types=1);
namespace Illuminate\Database;

use PDOException;
class Deadlock_Exception extends PDOException
{
}