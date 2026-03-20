<?php

declare(strict_types=1);

namespace Illuminate\Routing;

/**
 * Represents a parsed route URI along with any field-level binding constraints.
 *
 * This is a value object: once constructed, neither the URI nor the binding
 * fields change. Properties are declared `readonly` to enforce immutability
 * and communicate intent to static analysis tools.
 *
 * @since 9.x
 */
class RouteUri
{
    /**
     * Create a new route URI instance.
     *
     * @param  string  $uri            The normalised URI pattern, e.g. '/users/{id}'.
     * @param  array<string, string>  $bindingFields  Map of parameter name → field name used for
     *                                                explicit model binding, e.g. ['post' => 'slug'].
     */
    public function __construct(
        /**
         * The route URI pattern (e.g. '/users/{id}').
         */
        public readonly string $uri,
        /**
         * Map of route parameter name to the model field used when resolving bindings.
         * E.g. `['post' => 'slug']` means `{post:slug}` in the original URI.
         *
         * @var array<string, string>
         */
        public readonly array $bindingFields = []
    ) {}

    /**
     * Parse the given URI.
     *
     * Extracts `{parameter:field}` syntax into the `$bindingFields` map and
     * normalises the URI to standard `{parameter}` notation.
     *
     * @param  string  $uri  Raw route URI, possibly containing field binding syntax.
     * @return static        Parsed URI instance with binding fields extracted.
     *
     * @since 9.x
     */
    public static function parse(string $uri): static
    {
        preg_match_all('/\{([\w\:]+?)\??\}/', $uri, $matches);

        $bindingFields = [];

        foreach ($matches[0] as $match) {
            if (! str_contains($match, ':')) {
                continue;
            }

            $segments = explode(':', trim($match, '{}?'));

            $bindingFields[$segments[0]] = $segments[1];

            $uri = str_contains($match, '?')
                ? str_replace($match, '{'.$segments[0].'?}', $uri)
                : str_replace($match, '{'.$segments[0].'}', $uri);
        }

        return new static($uri, $bindingFields);
    }
}
