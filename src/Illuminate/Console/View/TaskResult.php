<?php

declare (strict_types=1);
namespace Illuminate\Console\View;

enum Task_Result : int
{
    case Success = 1;
    case Failure = 2;
    case Skipped = 3;
}