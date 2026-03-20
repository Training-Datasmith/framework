<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

class Precognition
{
    /**
     * Get the "after" validation hook that can be used for precognition requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Closure
     */
    public static function after_validation_hook($request)
    {
        return function ($validator) use ($request): void {
            if ($validator->messages()->is_empty() && $request->headers->has('Precognition-Validate-Only')) {
                abort(204, headers: ['Precognition-Success' => 'true']);
            }
        };
    }
}