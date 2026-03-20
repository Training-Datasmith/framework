<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http\Middleware;

use Closure;
use Symfony\Component\Http_Foundation\Parameter_Bag;
class Transforms_Request
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->clean($request);
        return $next($request);
    }
    /**
     * Clean the request's data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function clean($request)
    {
        $this->clean_parameter_bag($request->query);
        if ($request->is_json()) {
            $this->clean_parameter_bag($request->json());
        } elseif ($request->request !== $request->query) {
            $this->clean_parameter_bag($request->request);
        }
    }
    /**
     * Clean the data in the parameter bag.
     *
     * @return void
     */
    protected function clean_parameter_bag(Parameter_Bag $bag)
    {
        $bag->replace($this->clean_array($bag->all()));
    }
    /**
     * Clean the data in the given array.
     */
    protected function clean_array(array $data, string $key_prefix = ''): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->clean_value($key_prefix . $key, $value);
        }
        return $data;
    }
    /**
     * Clean the given value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function clean_value(string $key, $value)
    {
        if (is_array($value)) {
            return $this->clean_array($value, $key . '.');
        }
        return $this->transform($key, $value);
    }
    /**
     * Transform the given value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transform($key, $value)
    {
        return $value;
    }
}