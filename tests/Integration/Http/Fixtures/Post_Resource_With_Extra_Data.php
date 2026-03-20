<?php

declare(strict_types=1);

namespace Illuminate\Tests\Integration\Http\Fixtures;

class PostResourceWithExtraData extends PostResource
{
    public function with($request)
    {
        return ['foo' => 'bar'];
    }
}
