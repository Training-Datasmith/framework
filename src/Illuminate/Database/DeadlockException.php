<?php

declare(strict_types=1);

namespace Illuminate\Database;

use PDOException;

class DeadlockException extends PDOException
{
}
