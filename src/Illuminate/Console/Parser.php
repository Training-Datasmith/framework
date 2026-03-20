<?php

declare (strict_types=1);
namespace Illuminate\Console;

use InvalidArgumentException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Option;
class Parser
{
    /**
     * Parse the given console command definition into an array.
     *
     * @return array{string, array{}, array{}}|array{string, \Symfony\Component\Console\Input\InputArgument[], \Symfony\Component\Console\Input\InputOption[]}
     * @throws \InvalidArgumentException
     */
    public static function parse(string $expression): array
    {
        $name = static::name($expression);
        if (preg_match_all('/\{\s*(.*?)\s*\}/', $expression, $matches) && count($matches[1])) {
            return array_merge([$name], static::parameters($matches[1]));
        }
        return [$name, [], []];
    }
    /**
     * Extract the name of the command from the expression.
     *
     *
     * @throws \InvalidArgumentException
     */
    protected static function name(string $expression): string
    {
        if (!preg_match('/[^\s]+/', $expression, $matches)) {
            throw new InvalidArgumentException('Unable to determine command name from signature.');
        }
        return $matches[0];
    }
    /**
     * Extract all parameters from the tokens.
     *
     * @param  string[]  $tokens
     * @return array{\Symfony\Component\Console\Input\InputArgument[], \Symfony\Component\Console\Input\InputOption[]}
     */
    protected static function parameters(array $tokens): array
    {
        $arguments = [];
        $options = [];
        foreach ($tokens as $token) {
            if (preg_match('/^-{2,}(.*)/', $token, $matches)) {
                $options[] = static::parse_option($matches[1]);
            } else {
                $arguments[] = static::parse_argument($token);
            }
        }
        return [$arguments, $options];
    }
    /**
     * Parse an argument expression.
     *
     * @return \Symfony\Component\Console\Input\InputArgument
     */
    protected static function parse_argument(string $token)
    {
        [$token, $description] = static::extract_description($token);
        return match (true) {
            str_ends_with($token, '?*') => new Input_Argument(trim($token, '?*'), Input_Argument::IS_ARRAY, $description),
            str_ends_with($token, '*') => new Input_Argument(trim($token, '*'), Input_Argument::IS_ARRAY | Input_Argument::REQUIRED, $description),
            str_ends_with($token, '?') => new Input_Argument(trim($token, '?'), Input_Argument::OPTIONAL, $description),
            (bool) preg_match('/(.+)\=\*(.+)/', $token, $matches) => new Input_Argument($matches[1], Input_Argument::IS_ARRAY, $description, preg_split('/,\s?/', $matches[2])),
            (bool) preg_match('/(.+)\=(.+)/', $token, $matches) => new Input_Argument($matches[1], Input_Argument::OPTIONAL, $description, $matches[2]),
            default => new Input_Argument($token, Input_Argument::REQUIRED, $description),
        };
    }
    /**
     * Parse an option expression.
     *
     * @return \Symfony\Component\Console\Input\InputOption
     */
    protected static function parse_option(string $token)
    {
        [$token, $description] = static::extract_description($token);
        $matches = preg_split('/\s*\|\s*/', $token, 2);
        $shortcut = null;
        if (isset($matches[1])) {
            $shortcut = $matches[0];
            $token = $matches[1];
        }
        return match (true) {
            str_ends_with($token, '=') => new Input_Option(trim($token, '='), $shortcut, Input_Option::VALUE_OPTIONAL, $description),
            str_ends_with($token, '=*') => new Input_Option(trim($token, '=*'), $shortcut, Input_Option::VALUE_OPTIONAL | Input_Option::VALUE_IS_ARRAY, $description),
            (bool) preg_match('/(.+)\=\*(.+)/', $token, $matches) => new Input_Option($matches[1], $shortcut, Input_Option::VALUE_OPTIONAL | Input_Option::VALUE_IS_ARRAY, $description, preg_split('/,\s?/', $matches[2])),
            (bool) preg_match('/(.+)\=(.+)/', $token, $matches) => new Input_Option($matches[1], $shortcut, Input_Option::VALUE_OPTIONAL, $description, $matches[2]),
            default => new Input_Option($token, $shortcut, Input_Option::VALUE_NONE, $description),
        };
    }
    /**
     * Parse the token into its token and description segments.
     *
     * @return array{string, string}
     */
    protected static function extract_description(string $token)
    {
        $parts = preg_split('/\s+:\s+/', trim($token), 2);
        return count($parts) === 2 ? $parts : [$token, ''];
    }
}