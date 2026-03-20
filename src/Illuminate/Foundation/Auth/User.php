<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Auth;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Must_Verify_Email;
use Illuminate\Auth\Passwords\Can_Reset_Password;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Can_Reset_Password as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
class User extends Model implements Authenticatable_Contract, Authorizable_Contract, Can_Reset_Password_Contract
{
    use Authenticatable;
    use Authorizable;
    use Can_Reset_Password;
    use Must_Verify_Email;
}