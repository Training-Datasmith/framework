<?php

declare(strict_types=1);

namespace Illuminate\Tests\Integration\Http\Fixtures;

class PostResourceWithoutWrap extends PostResource
{
    public static $wrap = null;
}
