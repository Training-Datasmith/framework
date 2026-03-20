<?php

declare(strict_types=1);

namespace Illuminate\Auth\Passwords;

/**
 * Represents the possible outcomes of a password reset or reset-link operation.
 *
 * Using a backed enum instead of string constants provides IDE completion,
 * exhaustive match expressions, and type-safe comparisons without
 * relying on stringly-typed checks.
 *
 * The backing values are intentionally identical to the
 * {@see \Illuminate\Contracts\Auth\Password_Broker} interface constants so
 * that existing code using those constants remains compatible.
 *
 * @since 12.x
 */
enum Password_Reset_Status: string
{
    /**
     * A password reset link was successfully emailed to the user.
     */
    case ResetLinkSent = 'passwords.sent';

    /**
     * The password was successfully reset.
     */
    case PasswordReset = 'passwords.reset';

    /**
     * No user was found matching the supplied credentials.
     */
    case InvalidUser = 'passwords.user';

    /**
     * The supplied reset token is invalid or has expired.
     */
    case InvalidToken = 'passwords.token';

    /**
     * The reset request has been throttled due to too many attempts.
     */
    case ResetThrottled = 'passwords.throttled';

    /**
     * Determine whether this status represents a successful outcome.
     *
     * @return bool  True for ResetLinkSent and PasswordReset; false for all error cases.
     */
    public function isSuccessful(): bool
    {
        return match ($this) {
            self::ResetLinkSent, self::PasswordReset => true,
            default => false,
        };
    }

    /**
     * Create a status instance from a Password_Broker interface constant value.
     *
     * Provides a bridge between legacy string constants and the enum so that
     * existing callers can gradually migrate without a hard cut-over.
     *
     * @param  string  $value  One of the Password_Broker::* constant values.
     * @return self
     *
     * @throws \ValueError When $value does not match any known status.
     */
    public static function fromBrokerConstant(string $value): self
    {
        return self::from($value);
    }
}
