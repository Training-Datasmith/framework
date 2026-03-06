<?php

namespace Illuminate\Support;

use ArrayAccess;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Dumpable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use JsonSerializable;
use Stringable as BaseStringable;

class Stringable implements JsonSerializable, ArrayAccess, BaseStringable
{
    use Conditionable, Dumpable, Macroable, Tappable;

    /**
     * The underlying string value.
     *
     * @var string
     */
    protected $value;

    /**
     * Create a new instance of the class.
     *
     * @param  string  $value
     */
    public function __construct($value = '')
    {
        $this->value = (string) $value;
    }

    /**
     * Return the remainder of a string after the first occurrence of a given value.
     *
     * @param  string  $search
     */
    public function after($search): static
    {
        return new static(Str::after($this->value, $search));
    }

    /**
     * Return the remainder of a string after the last occurrence of a given value.
     *
     * @param  string  $search
     */
    public function afterLast($search): static
    {
        return new static(Str::afterLast($this->value, $search));
    }

    /**
     * Append the given values to the string.
     *
     * @param  array|string  ...$values
     */
    public function append(...$values): static
    {
        return new static($this->value.implode('', $values));
    }

    /**
     * Append a new line to the string.
     *
     * @param  int  $count
     * @return $this
     */
    public function newLine($count = 1)
    {
        return $this->append(str_repeat(PHP_EOL, $count));
    }

    /**
     * Transliterate a UTF-8 value to ASCII.
     *
     * @param  string  $language
     */
    public function ascii($language = 'en'): static
    {
        return new static(Str::ascii($this->value, $language));
    }

    /**
     * Get the trailing name component of the path.
     *
     * @param  string  $suffix
     */
    public function basename($suffix = ''): static
    {
        return new static(basename($this->value, $suffix));
    }

    /**
     * Get the character at the specified index.
     *
     * @param  int  $index
     * @return string|false
     */
    public function charAt($index)
    {
        return Str::charAt($this->value, $index);
    }

    /**
     * Remove the given string if it exists at the start of the current string.
     *
     * @param  string|array  $needle
     */
    public function chopStart($needle): static
    {
        return new static(Str::chopStart($this->value, $needle));
    }

    /**
     * Remove the given string if it exists at the end of the current string.
     *
     * @param  string|array  $needle
     */
    public function chopEnd($needle): static
    {
        return new static(Str::chopEnd($this->value, $needle));
    }

    /**
     * Get the basename of the class path.
     */
    public function classBasename(): static
    {
        return new static(class_basename($this->value));
    }

    /**
     * Get the portion of a string before the first occurrence of a given value.
     *
     * @param  string  $search
     */
    public function before($search): static
    {
        return new static(Str::before($this->value, $search));
    }

    /**
     * Get the portion of a string before the last occurrence of a given value.
     *
     * @param  string  $search
     */
    public function beforeLast($search): static
    {
        return new static(Str::beforeLast($this->value, $search));
    }

    /**
     * Get the portion of a string between two given values.
     *
     * @param  string  $from
     * @param  string  $to
     */
    public function between($from, $to): static
    {
        return new static(Str::between($this->value, $from, $to));
    }

    /**
     * Get the smallest possible portion of a string between two given values.
     *
     * @param  string  $from
     * @param  string  $to
     */
    public function betweenFirst($from, $to): static
    {
        return new static(Str::betweenFirst($this->value, $from, $to));
    }

    /**
     * Convert a value to camel case.
     */
    public function camel(): static
    {
        return new static(Str::camel($this->value));
    }

    /**
     * Determine if a given string contains a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @param  bool  $ignoreCase
     * @return bool
     */
    public function contains($needles, $ignoreCase = false)
    {
        return Str::contains($this->value, $needles, $ignoreCase);
    }

    /**
     * Determine if a given string contains all array values.
     *
     * @param  iterable<string>  $needles
     * @param  bool  $ignoreCase
     * @return bool
     */
    public function containsAll($needles, $ignoreCase = false)
    {
        return Str::containsAll($this->value, $needles, $ignoreCase);
    }

    /**
     * Determine if a given string doesn't contain a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @param  bool  $ignoreCase
     * @return bool
     */
    public function doesntContain($needles, $ignoreCase = false)
    {
        return Str::doesntContain($this->value, $needles, $ignoreCase);
    }

    /**
     * Convert the case of a string.
     */
    public function convertCase(int $mode = MB_CASE_FOLD, ?string $encoding = 'UTF-8'): static
    {
        return new static(Str::convertCase($this->value, $mode, $encoding));
    }

    /**
     * Replace consecutive instances of a given character with a single character.
     *
     * @param  array<string>|string  $characters
     */
    public function deduplicate(array|string $characters = ' '): static
    {
        return new static(Str::deduplicate($this->value, $characters));
    }

    /**
     * Get the parent directory's path.
     *
     * @param  int  $levels
     */
    public function dirname($levels = 1): static
    {
        return new static(dirname($this->value, $levels));
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @return bool
     */
    public function endsWith($needles)
    {
        return Str::endsWith($this->value, $needles);
    }

    /**
     * Determine if a given string doesn't end with a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @return bool
     */
    public function doesntEndWith($needles)
    {
        return Str::doesntEndWith($this->value, $needles);
    }

    /**
     * Determine if the string is an exact match with the given value.
     *
     * @param  \Illuminate\Support\Stringable|string  $value
     */
    public function exactly($value): bool
    {
        if ($value instanceof Stringable) {
            $value = $value->toString();
        }

        return $this->value === $value;
    }

    /**
     * Extracts an excerpt from text that matches the first instance of a phrase.
     *
     * @param  string  $phrase
     * @param  array  $options
     * @return string|null
     */
    public function excerpt($phrase = '', $options = [])
    {
        return Str::excerpt($this->value, $phrase, $options);
    }

    /**
     * Explode the string into a collection.
     *
     * @param  string  $delimiter
     * @param  int  $limit
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function explode($delimiter, $limit = PHP_INT_MAX): \Illuminate\Support\Collection
    {
        return new Collection(explode($delimiter, $this->value, $limit));
    }

    /**
     * Split a string using a regular expression or by length.
     *
     * @param  string|int  $pattern
     * @param  int  $limit
     * @param  int  $flags
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function split($pattern, $limit = -1, $flags = 0): \Illuminate\Support\Collection
    {
        if (filter_var($pattern, FILTER_VALIDATE_INT) !== false) {
            return new Collection(mb_str_split($this->value, $pattern));
        }

        $segments = preg_split($pattern, $this->value, $limit, $flags);

        return ! empty($segments) ? new Collection($segments) : new Collection;
    }

    /**
     * Cap a string with a single instance of a given value.
     *
     * @param  string  $cap
     */
    public function finish($cap): static
    {
        return new static(Str::finish($this->value, $cap));
    }

    /**
     * Determine if a given string matches a given pattern.
     *
     * @param  string|iterable<string>  $pattern
     * @param  bool  $ignoreCase
     * @return bool
     */
    public function is($pattern, $ignoreCase = false)
    {
        return Str::is($pattern, $this->value, $ignoreCase);
    }

    /**
     * Determine if a given string is 7 bit ASCII.
     *
     * @return bool
     */
    public function isAscii()
    {
        return Str::isAscii($this->value);
    }

    /**
     * Determine if a given string is valid JSON.
     *
     * @return bool
     */
    public function isJson()
    {
        return Str::isJson($this->value);
    }

    /**
     * Determine if a given value is a valid URL.
     *
     * @return bool
     */
    public function isUrl(array $protocols = [])
    {
        return Str::isUrl($this->value, $protocols);
    }

    /**
     * Determine if a given string is a valid UUID.
     *
     * @param  int<0, 8>|'max'|null  $version
     * @return bool
     */
    public function isUuid($version = null)
    {
        return Str::isUuid($this->value, $version);
    }

    /**
     * Determine if a given string is a valid ULID.
     *
     * @return bool
     */
    public function isUlid()
    {
        return Str::isUlid($this->value);
    }

    /**
     * Determine if the given string is empty.
     */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /**
     * Determine if the given string is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Convert a string to kebab case.
     */
    public function kebab(): static
    {
        return new static(Str::kebab($this->value));
    }

    /**
     * Return the length of the given string.
     *
     * @param  string|null  $encoding
     * @return int
     */
    public function length($encoding = null)
    {
        return Str::length($this->value, $encoding);
    }

    /**
     * Limit the number of characters in a string.
     *
     * @param  int  $limit
     * @param  string  $end
     * @param  bool  $preserveWords
     */
    public function limit($limit = 100, $end = '...', $preserveWords = false): static
    {
        return new static(Str::limit($this->value, $limit, $end, $preserveWords));
    }

    /**
     * Convert the given string to lower-case.
     */
    public function lower(): static
    {
        return new static(Str::lower($this->value));
    }

    /**
     * Convert GitHub flavored Markdown into HTML.
     */
    public function markdown(array $options = [], array $extensions = []): static
    {
        return new static(Str::markdown($this->value, $options, $extensions));
    }

    /**
     * Convert inline Markdown into HTML.
     */
    public function inlineMarkdown(array $options = [], array $extensions = []): static
    {
        return new static(Str::inlineMarkdown($this->value, $options, $extensions));
    }

    /**
     * Masks a portion of a string with a repeated character.
     *
     * @param  string  $character
     * @param  int  $index
     * @param  int|null  $length
     * @param  string  $encoding
     */
    public function mask($character, $index, $length = null, $encoding = 'UTF-8'): static
    {
        return new static(Str::mask($this->value, $character, $index, $length, $encoding));
    }

    /**
     * Get the string matching the given pattern.
     *
     * @param  string  $pattern
     */
    public function match($pattern): static
    {
        return new static(Str::match($pattern, $this->value));
    }

    /**
     * Determine if a given string matches a given pattern.
     *
     * @param  string|iterable<string>  $pattern
     * @return bool
     */
    public function isMatch($pattern)
    {
        return Str::isMatch($pattern, $this->value);
    }

    /**
     * Get the string matching the given pattern.
     *
     * @param  string  $pattern
     * @return \Illuminate\Support\Collection
     */
    public function matchAll($pattern)
    {
        return Str::matchAll($pattern, $this->value);
    }

    /**
     * Determine if the string matches the given pattern.
     *
     * @param  string  $pattern
     * @return bool
     */
    public function test($pattern)
    {
        return $this->isMatch($pattern);
    }

    /**
     * Remove all non-numeric characters from a string.
     */
    public function numbers(): static
    {
        return new static(Str::numbers($this->value));
    }

    /**
     * Pad both sides of the string with another.
     *
     * @param  int  $length
     * @param  string  $pad
     */
    public function padBoth($length, $pad = ' '): static
    {
        return new static(Str::padBoth($this->value, $length, $pad));
    }

    /**
     * Pad the left side of the string with another.
     *
     * @param  int  $length
     * @param  string  $pad
     */
    public function padLeft($length, $pad = ' '): static
    {
        return new static(Str::padLeft($this->value, $length, $pad));
    }

    /**
     * Pad the right side of the string with another.
     *
     * @param  int  $length
     * @param  string  $pad
     */
    public function padRight($length, $pad = ' '): static
    {
        return new static(Str::padRight($this->value, $length, $pad));
    }

    /**
     * Parse a Class@method style callback into class and method.
     *
     * @param  string|null  $default
     * @return array<int, string|null>
     */
    public function parseCallback($default = null)
    {
        return Str::parseCallback($this->value, $default);
    }

    /**
     * Call the given callback and return a new string.
     */
    public function pipe(callable $callback): static
    {
        return new static($callback($this));
    }

    /**
     * Get the plural form of an English word.
     *
     * @param  int|array|\Countable  $count
     * @param  bool  $prependCount
     */
    public function plural($count = 2, $prependCount = false): static
    {
        return new static(Str::plural($this->value, $count, $prependCount));
    }

    /**
     * Pluralize the last word of an English, studly caps case string.
     *
     * @param  int|array|\Countable  $count
     */
    public function pluralStudly($count = 2): static
    {
        return new static(Str::pluralStudly($this->value, $count));
    }

    /**
     * Pluralize the last word of an English, Pascal caps case string.
     *
     * @param  int|array|\Countable  $count
     */
    public function pluralPascal($count = 2): static
    {
        return new static(Str::pluralStudly($this->value, $count));
    }

    /**
     * Find the multi-byte safe position of the first occurrence of the given substring.
     *
     * @param  string  $needle
     * @param  int  $offset
     * @param  string|null  $encoding
     * @return int|false
     */
    public function position($needle, $offset = 0, $encoding = null)
    {
        return Str::position($this->value, $needle, $offset, $encoding);
    }

    /**
     * Prepend the given values to the string.
     *
     * @param  string  ...$values
     */
    public function prepend(...$values): static
    {
        return new static(implode('', $values).$this->value);
    }

    /**
     * Remove any occurrence of the given string in the subject.
     *
     * @param  string|iterable<string>  $search
     * @param  bool  $caseSensitive
     */
    public function remove($search, $caseSensitive = true): static
    {
        return new static(Str::remove($search, $this->value, $caseSensitive));
    }

    /**
     * Reverse the string.
     */
    public function reverse(): static
    {
        return new static(Str::reverse($this->value));
    }

    /**
     * Repeat the string.
     */
    public function repeat(int $times): static
    {
        return new static(str_repeat($this->value, $times));
    }

    /**
     * Replace the given value in the given string.
     *
     * @param  string|iterable<string>  $search
     * @param  string|iterable<string>  $replace
     * @param  bool  $caseSensitive
     */
    public function replace($search, $replace, $caseSensitive = true): static
    {
        return new static(Str::replace($search, $replace, $this->value, $caseSensitive));
    }

    /**
     * Replace a given value in the string sequentially with an array.
     *
     * @param  string  $search
     * @param  iterable<string>  $replace
     */
    public function replaceArray($search, $replace): static
    {
        return new static(Str::replaceArray($search, $replace, $this->value));
    }

    /**
     * Replace the first occurrence of a given value in the string.
     *
     * @param  string  $search
     * @param  string  $replace
     */
    public function replaceFirst($search, $replace): static
    {
        return new static(Str::replaceFirst($search, $replace, $this->value));
    }

    /**
     * Replace the first occurrence of the given value if it appears at the start of the string.
     *
     * @param  string  $search
     * @param  string  $replace
     */
    public function replaceStart($search, $replace): static
    {
        return new static(Str::replaceStart($search, $replace, $this->value));
    }

    /**
     * Replace the last occurrence of a given value in the string.
     *
     * @param  string  $search
     * @param  string  $replace
     */
    public function replaceLast($search, $replace): static
    {
        return new static(Str::replaceLast($search, $replace, $this->value));
    }

    /**
     * Replace the last occurrence of a given value if it appears at the end of the string.
     *
     * @param  string  $search
     * @param  string  $replace
     */
    public function replaceEnd($search, $replace): static
    {
        return new static(Str::replaceEnd($search, $replace, $this->value));
    }

    /**
     * Replace the patterns matching the given regular expression.
     *
     * @param  array|string  $pattern
     * @param  \Closure|string[]|string  $replace
     * @param  int  $limit
     */
    public function replaceMatches($pattern, $replace, $limit = -1): static
    {
        if ($replace instanceof Closure) {
            return new static(preg_replace_callback($pattern, $replace, $this->value, $limit));
        }

        return new static(preg_replace($pattern, $replace, $this->value, $limit));
    }

    /**
     * Parse input from a string to a collection, according to a format.
     *
     * @param  string  $format
     */
    public function scan($format): \Illuminate\Support\Collection
    {
        return new Collection(sscanf($this->value, $format));
    }

    /**
     * Remove all "extra" blank space from the given string.
     */
    public function squish(): static
    {
        return new static(Str::squish($this->value));
    }

    /**
     * Begin a string with a single instance of a given value.
     *
     * @param  string  $prefix
     */
    public function start($prefix): static
    {
        return new static(Str::start($this->value, $prefix));
    }

    /**
     * Strip HTML and PHP tags from the given string.
     *
     * @param  string[]|string|null  $allowedTags
     */
    public function stripTags($allowedTags = null): static
    {
        return new static(strip_tags($this->value, $allowedTags));
    }

    /**
     * Convert the given string to upper-case.
     */
    public function upper(): static
    {
        return new static(Str::upper($this->value));
    }

    /**
     * Convert the given string to proper case.
     */
    public function title(): static
    {
        return new static(Str::title($this->value));
    }

    /**
     * Convert the given string to proper case for each word.
     */
    public function headline(): static
    {
        return new static(Str::headline($this->value));
    }

    /**
     * Convert the given string to APA-style title case.
     */
    public function apa(): static
    {
        return new static(Str::apa($this->value));
    }

    /**
     * Transliterate a string to its closest ASCII representation.
     *
     * @param  string|null  $unknown
     * @param  bool|null  $strict
     */
    public function transliterate($unknown = '?', $strict = false): static
    {
        return new static(Str::transliterate($this->value, $unknown, $strict));
    }

    /**
     * Get the singular form of an English word.
     */
    public function singular(): static
    {
        return new static(Str::singular($this->value));
    }

    /**
     * Generate a URL friendly "slug" from a given string.
     *
     * @param  string  $separator
     * @param  string|null  $language
     * @param  array<string, string>  $dictionary
     */
    public function slug($separator = '-', $language = 'en', $dictionary = ['@' => 'at']): static
    {
        return new static(Str::slug($this->value, $separator, $language, $dictionary));
    }

    /**
     * Convert a string to snake case.
     *
     * @param  string  $delimiter
     */
    public function snake($delimiter = '_'): static
    {
        return new static(Str::snake($this->value, $delimiter));
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @return bool
     */
    public function startsWith($needles)
    {
        return Str::startsWith($this->value, $needles);
    }

    /**
     * Determine if a given string doesn't start with a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @return bool
     */
    public function doesntStartWith($needles)
    {
        return Str::doesntStartWith($this->value, $needles);
    }

    /**
     * Convert a value to studly caps case.
     */
    public function studly(): static
    {
        return new static(Str::studly($this->value));
    }

    /**
     * Convert the string to Pascal case.
     */
    public function pascal(): static
    {
        return new static(Str::pascal($this->value));
    }

    /**
     * Returns the portion of the string specified by the start and length parameters.
     *
     * @param  int  $start
     * @param  int|null  $length
     * @param  string  $encoding
     */
    public function substr($start, $length = null, $encoding = 'UTF-8'): static
    {
        return new static(Str::substr($this->value, $start, $length, $encoding));
    }

    /**
     * Returns the number of substring occurrences.
     *
     * @param  string  $needle
     * @param  int  $offset
     * @param  int|null  $length
     * @return int
     */
    public function substrCount($needle, $offset = 0, $length = null)
    {
        return Str::substrCount($this->value, $needle, $offset, $length);
    }

    /**
     * Replace text within a portion of a string.
     *
     * @param  string|string[]  $replace
     * @param  int|int[]  $offset
     * @param  int|int[]|null  $length
     */
    public function substrReplace($replace, $offset = 0, $length = null): static
    {
        return new static(Str::substrReplace($this->value, $replace, $offset, $length));
    }

    /**
     * Swap multiple keywords in a string with other keywords.
     */
    public function swap(array $map): static
    {
        return new static(strtr($this->value, $map));
    }

    /**
     * Take the first or last {$limit} characters.
     *
     * @return static
     */
    public function take(int $limit)
    {
        if ($limit < 0) {
            return $this->substr($limit);
        }

        return $this->substr(0, $limit);
    }

    /**
     * Trim the string of the given characters.
     *
     * @param  string|null  $characters
     */
    public function trim($characters = null): static
    {
        return new static(Str::trim(...array_merge([$this->value], func_get_args())));
    }

    /**
     * Left trim the string of the given characters.
     *
     * @param  string|null  $characters
     */
    public function ltrim($characters = null): static
    {
        return new static(Str::ltrim(...array_merge([$this->value], func_get_args())));
    }

    /**
     * Right trim the string of the given characters.
     *
     * @param  string|null  $characters
     */
    public function rtrim($characters = null): static
    {
        return new static(Str::rtrim(...array_merge([$this->value], func_get_args())));
    }

    /**
     * Make a string's first character lowercase.
     */
    public function lcfirst(): static
    {
        return new static(Str::lcfirst($this->value));
    }

    /**
     * Make a string's first character uppercase.
     */
    public function ucfirst(): static
    {
        return new static(Str::ucfirst($this->value));
    }

    /**
     * Capitalize the first character of each word in a string.
     *
     * @param  string  $separators
     */
    public function ucwords($separators = " \t\r\n\f\v"): static
    {
        return new static(Str::ucwords($this->value, $separators));
    }

    /**
     * Split a string by uppercase characters.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function ucsplit(): \Illuminate\Support\Collection
    {
        return new Collection(Str::ucsplit($this->value));
    }

    /**
     * Execute the given callback if the string contains a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @param  callable  $callback
     * @return static
     */
    public function whenContains($needles, ?callable $callback, ?callable $default = null)
    {
        return $this->when($this->contains($needles), $callback, $default);
    }

    /**
     * Execute the given callback if the string contains all array values.
     *
     * @param  iterable<string>  $needles
     * @param  callable  $callback
     * @return static
     */
    public function whenContainsAll(array $needles, ?callable $callback, ?callable $default = null)
    {
        return $this->when($this->containsAll($needles), $callback, $default);
    }

    /**
     * Execute the given callback if the string is empty.
     *
     * @param  callable  $callback
     * @return static
     */
    public function whenEmpty(?callable $callback, ?callable $default = null)
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    /**
     * Execute the given callback if the string is not empty.
     *
     * @param  callable  $callback
     * @return static
     */
    public function whenNotEmpty(?callable $callback, ?callable $default = null)
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Execute the given callback if the string ends with a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @param  callable  $callback
     * @return static
     */
    public function whenEndsWith($needles, ?callable $callback, ?callable $default = null)
    {
        return $this->when($this->endsWith($needles), $callback, $default);
    }

    /**
     * Execute the given callback if the string doesn't end with a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @param  callable  $callback
     * @return static
     */
    public function whenDoesntEndWith($needles, ?callable $callback, ?callable $default = null)
    {
        return $this->when($this->doesntEndWith($needles), $callback, $default);
    }

    /**
     * Execute the given callback if the string is an exact match with the given value.
     *
     * @param  string  $value
     * @param  callable  $callback
     * @return static
     */
    public function whenExactly($value, ?callable $callback, ?callable $default = null)
    {
        return $this->when($this->exactly($value), $callback, $default);
    }

    /**
     * Execute the given callback if the string is not an exact match with the given value.
     *
     * @param  string  $value
     * @param  callable  $callback
     * @return static
     */
    public function whenNotExactly($value, ?callable $callback, ?callable $default = null)
    {
        return $this->when(! $this->exactly($value), $callback, $default);
    }

    /**
     * Execute the given callback if the string matches a given pattern.
     *
     * @param  string|iterable<string>  $pattern
     * @param  callable  $callback
     * @return static
     */
    public function whenIs($pattern, ?callable $callback, ?callable $default = null)
    {
        return $this->when($this->is($pattern), $callback, $default);
    }

    /**
     * Execute the given callback if the string is 7 bit ASCII.
     *
     * @param  callable  $callback
     * @return static
     */
    public function whenIsAscii(?callable $callback, ?callable $default = null)
    {
        return $this->when($this->isAscii(), $callback, $default);
    }

    /**
     * Execute the given callback if the string is a valid UUID.
     *
     * @param  callable  $callback
     * @return static
     */
    public function whenIsUuid(?callable $callback, ?callable $default = null)
    {
        return $this->when($this->isUuid(), $callback, $default);
    }

    /**
     * Execute the given callback if the string is a valid ULID.
     *
     * @param  callable  $callback
     * @return static
     */
    public function whenIsUlid(?callable $callback, ?callable $default = null)
    {
        return $this->when($this->isUlid(), $callback, $default);
    }

    /**
     * Execute the given callback if the string starts with a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @param  callable  $callback
     * @return static
     */
    public function whenStartsWith($needles, ?callable $callback, ?callable $default = null)
    {
        return $this->when($this->startsWith($needles), $callback, $default);
    }

    /**
     * Execute the given callback if the string doesn't start with a given substring.
     *
     * @param  string|iterable<string>  $needles
     * @param  callable  $callback
     * @return static
     */
    public function whenDoesntStartWith($needles, ?callable $callback, ?callable $default = null)
    {
        return $this->when($this->doesntStartWith($needles), $callback, $default);
    }

    /**
     * Execute the given callback if the string matches the given pattern.
     *
     * @param  string  $pattern
     * @param  callable  $callback
     * @return static
     */
    public function whenTest($pattern, ?callable $callback, ?callable $default = null)
    {
        return $this->when($this->test($pattern), $callback, $default);
    }

    /**
     * Limit the number of words in a string.
     *
     * @param  int  $words
     * @param  string  $end
     */
    public function words($words = 100, $end = '...'): static
    {
        return new static(Str::words($this->value, $words, $end));
    }

    /**
     * Get the number of words a string contains.
     *
     * @param  string|null  $characters
     * @return int
     */
    public function wordCount($characters = null)
    {
        return Str::wordCount($this->value, $characters);
    }

    /**
     * Wrap a string to a given number of characters.
     *
     * @param  int  $characters
     * @param  string  $break
     * @param  bool  $cutLongWords
     */
    public function wordWrap($characters = 75, $break = "\n", $cutLongWords = false): static
    {
        return new static(Str::wordWrap($this->value, $characters, $break, $cutLongWords));
    }

    /**
     * Wrap the string with the given strings.
     *
     * @param  string  $before
     * @param  string|null  $after
     */
    public function wrap($before, $after = null): static
    {
        return new static(Str::wrap($this->value, $before, $after));
    }

    /**
     * Unwrap the string with the given strings.
     *
     * @param  string  $before
     * @param  string|null  $after
     */
    public function unwrap($before, $after = null): static
    {
        return new static(Str::unwrap($this->value, $before, $after));
    }

    /**
     * Convert the string into a `HtmlString` instance.
     */
    public function toHtmlString(): \Illuminate\Support\HtmlString
    {
        return new HtmlString($this->value);
    }

    /**
     * Convert the string to Base64 encoding.
     */
    public function toBase64(): static
    {
        return new static(base64_encode($this->value));
    }

    /**
     * Decode the Base64 encoded string.
     *
     * @param  bool  $strict
     */
    public function fromBase64($strict = false): static
    {
        return new static(base64_decode($this->value, $strict));
    }

    /**
     * Hash the string using the given algorithm.
     */
    public function hash(string $algorithm): static
    {
        return new static(hash($algorithm, $this->value));
    }

    /**
     * Encrypt the string.
     */
    public function encrypt(bool $serialize = false): static
    {
        return new static(encrypt($this->value, $serialize));
    }

    /**
     * Decrypt the string.
     */
    public function decrypt(bool $serialize = false): static
    {
        return new static(decrypt($this->value, $serialize));
    }

    /**
     * Dump the string.
     *
     * @param  mixed  ...$args
     * @return $this
     */
    public function dump(...$args): static
    {
        dump($this->value, ...$args);

        return $this;
    }

    /**
     * Get the underlying string value.
     *
     * @return string
     */
    public function value()
    {
        return $this->toString();
    }

    /**
     * Get the underlying string value.
     *
     * @return string
     */
    public function toString()
    {
        return $this->value;
    }

    /**
     * Get the underlying string value as an integer.
     *
     * @param  int  $base
     */
    public function toInteger($base = 10): int
    {
        return intval($this->value, $base);
    }

    /**
     * Get the underlying string value as a float.
     */
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /**
     * Get the underlying string value as a boolean.
     *
     * Returns true when value is "1", "true", "on", and "yes". Otherwise, returns false.
     */
    public function toBoolean(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get the underlying string value as a Carbon instance.
     *
     * @param  string|null  $format
     * @param  string|null  $tz
     * @return \Illuminate\Support\Carbon
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function toDate($format = null, $tz = null)
    {
        if (is_null($format)) {
            return Date::parse($this->value, $tz);
        }

        return Date::createFromFormat($format, $this->value, $tz);
    }

    /**
     * Get the underlying string value as a Uri instance.
     */
    public function toUri(): \Illuminate\Support\Uri
    {
        return Uri::of($this->value);
    }

    /**
     * Convert the object to a string when JSON encoded.
     */
    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    /**
     * Determine if the given offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->value[$offset]);
    }

    /**
     * Get the value at the given offset.
     */
    public function offsetGet(mixed $offset): string
    {
        return $this->value[$offset];
    }

    /**
     * Set the value at the given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->value[$offset] = $value;
    }

    /**
     * Unset the value at the given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->value[$offset]);
    }

    /**
     * Proxy dynamic properties onto methods.
     */
    public function __get(string $key): mixed
    {
        return $this->{$key}();
    }

    /**
     * Get the raw string value.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }
}
