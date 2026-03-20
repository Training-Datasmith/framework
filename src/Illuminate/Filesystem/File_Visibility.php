<?php

declare(strict_types=1);

namespace Illuminate\Filesystem;

/**
 * Represents the visibility of a stored file.
 *
 * Backed by the string values used in Flysystem / Laravel's filesystem
 * abstraction so that instances can be persisted and compared without
 * stringly-typed checks.
 *
 * The backing values are intentionally identical to the
 * {@see \Illuminate\Contracts\Filesystem\Filesystem} interface constants
 * (`VISIBILITY_PUBLIC` and `VISIBILITY_PRIVATE`) for backwards compatibility.
 *
 * @since 12.x
 */
enum File_Visibility: string
{
    /**
     * Files are publicly accessible from the web (world-readable).
     */
    case Public = 'public';

    /**
     * Files are not publicly accessible and require authentication or direct access.
     */
    case Private = 'private';

    /**
     * Determine whether this visibility allows public access.
     *
     * @return bool  True only for the Public case.
     */
    public function isPublic(): bool
    {
        return $this === self::Public;
    }

    /**
     * Determine whether this visibility restricts public access.
     *
     * @return bool  True only for the Private case.
     */
    public function isPrivate(): bool
    {
        return $this === self::Private;
    }
}
