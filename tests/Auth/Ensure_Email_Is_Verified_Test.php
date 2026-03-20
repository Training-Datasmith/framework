<?php

declare(strict_types=1);

namespace Illuminate\Tests\Auth;

use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use PHPUnit\Framework\TestCase;

class EnsureEmailIsVerifiedTest extends TestCase
{
    public function testItCanGenerateDefinitionViaStaticMethod()
    {
        $signature = EnsureEmailIsVerified::redirectTo('route.name');
        $this->assertSame('Illuminate\Auth\Middleware\EnsureEmailIsVerified:route.name', $signature);
    }
}
