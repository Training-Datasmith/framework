<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/{user}', fn () => response('', 404));
