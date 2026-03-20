<?php

declare (strict_types=1);
namespace Illuminate\Auth\Passwords;

use Illuminate\Contracts\Auth\Can_Reset_Password as CanResetPasswordContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\Connection_Interface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
class Database_Token_Repository implements Token_Repository_Interface
{
    /**
     * Create a new token repository instance.
     *
     * @param  int  $expires  The number of seconds a token should remain valid.
     * @param  int  $throttle  Minimum number of seconds before the user can generate new password reset tokens.
     */
    public function __construct(protected Connection_Interface $connection, protected Hasher_Contract $hasher, protected string $table, protected string $hash_key, protected int $expires = 3600, protected int $throttle = 60)
    {
    }
    /**
     * Create a new token record.
     */
    public function create(Can_Reset_Password_Contract $user): string
    {
        $email = $user->get_email_for_password_reset();
        $this->delete_existing($user);
        // We will create a new, random token for the user so that we can e-mail them
        // a safe link to the password reset form. Then we will insert a record in
        // the database so that we can verify the token within the actual reset.
        $token = $this->create_new_token();
        $this->get_table()->insert($this->get_payload($email, $token));
        return $token;
    }
    /**
     * Delete all existing reset tokens from the database.
     *
     * @return int
     */
    protected function delete_existing(Can_Reset_Password_Contract $user)
    {
        return $this->get_table()->where('email', $user->get_email_for_password_reset())->delete();
    }
    /**
     * Build the record payload for the table.
     *
     * @param  string  $email
     * @param  string  $token
     */
    protected function get_payload(
        $email,
        #[\Sensitive_Parameter]
        $token
    ): array
    {
        return ['email' => $email, 'token' => $this->hasher->make($token), 'created_at' => new Carbon()];
    }
    /**
     * Determine if a token record exists and is valid.
     *
     * @param  string  $token
     */
    public function exists(
        Can_Reset_Password_Contract $user,
        #[\Sensitive_Parameter]
        $token
    ): bool
    {
        $record = (array) $this->get_table()->where('email', $user->get_email_for_password_reset())->first();
        return $record && !$this->token_expired($record['created_at']) && $this->hasher->check($token, $record['token']);
    }
    /**
     * Determine if the token has expired.
     *
     * @param  string  $createdAt
     * @return bool
     */
    protected function token_expired($created_at)
    {
        return Carbon::parse($created_at)->add_seconds($this->expires)->is_past();
    }
    /**
     * Determine if the given user recently created a password reset token.
     */
    public function recently_created_token(Can_Reset_Password_Contract $user): bool
    {
        $record = (array) $this->get_table()->where('email', $user->get_email_for_password_reset())->first();
        return $record && $this->token_recently_created($record['created_at']);
    }
    /**
     * Determine if the token was recently created.
     *
     * @param  string  $createdAt
     * @return bool
     */
    protected function token_recently_created($created_at)
    {
        if ($this->throttle <= 0) {
            return false;
        }
        return Carbon::parse($created_at)->add_seconds($this->throttle)->is_future();
    }
    /**
     * Delete a token record by user.
     */
    public function delete(Can_Reset_Password_Contract $user): void
    {
        $this->delete_existing($user);
    }
    /**
     * Delete expired tokens.
     */
    public function delete_expired(): void
    {
        $expired_at = Carbon::now()->sub_seconds($this->expires);
        $this->get_table()->where('created_at', '<', $expired_at)->delete();
    }
    /**
     * Create a new token for the user.
     */
    public function create_new_token(): string
    {
        return hash_hmac('sha256', Str::random(40), $this->hash_key);
    }
    /**
     * Get the database connection instance.
     */
    public function get_connection(): \Illuminate\Database\Connection_Interface
    {
        return $this->connection;
    }
    /**
     * Begin a new database query against the table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function get_table()
    {
        return $this->connection->table($this->table);
    }
    /**
     * Get the hasher instance.
     */
    public function get_hasher(): \Illuminate\Contracts\Hashing\Hasher
    {
        return $this->hasher;
    }
}