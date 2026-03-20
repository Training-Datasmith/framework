<?php

declare (strict_types=1);
namespace Illuminate\Database\Migrations;

enum Migration_Result : int
{
    case Success = 1;
    case Failure = 2;
    case Skipped = 3;
}