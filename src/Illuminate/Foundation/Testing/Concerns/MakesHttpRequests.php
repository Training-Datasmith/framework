<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Backed_Enum;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\Cookie_Value_Prefix;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Uri;
use Illuminate\Testing\Logged_Exception_Collection;
use Illuminate\Testing\Test_Response;
use Symfony\Component\Http_Foundation\File\Uploaded_File as SymfonyUploadedFile;
use Symfony\Component\Http_Foundation\Request as SymfonyRequest;
trait Makes_Http_Requests
{
    /**
     * Additional headers for the request.
     *
     * @var array
     */
    protected $default_headers = [];
    /**
     * Additional cookies for the request.
     *
     * @var array
     */
    protected $default_cookies = [];
    /**
     * Additional cookies will not be encrypted for the request.
     *
     * @var array
     */
    protected $unencrypted_cookies = [];
    /**
     * Additional server variables for the request.
     *
     * @var array
     */
    protected $server_variables = [];
    /**
     * Indicates whether redirects should be followed.
     *
     * @var bool
     */
    protected $follow_redirects = false;
    /**
     * Indicates whether cookies should be encrypted.
     *
     * @var bool
     */
    protected $encrypt_cookies = true;
    /**
     * Indicated whether JSON requests should be performed "with credentials" (cookies).
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest/withCredentials
     *
     * @var bool
     */
    protected $with_credentials = false;
    /**
     * Define additional headers to be sent with the request.
     *
     * @return $this
     */
    public function with_headers(array $headers)
    {
        $this->default_headers = array_merge($this->default_headers, $headers);
        return $this;
    }
    /**
     * Add a header to be sent with the request.
     *
     * @return $this
     */
    public function with_header(string $name, string $value)
    {
        $this->default_headers[$name] = $value;
        return $this;
    }
    /**
     * Remove a header from the request.
     *
     * @return $this
     */
    public function without_header(string $name)
    {
        unset($this->default_headers[$name]);
        return $this;
    }
    /**
     * Remove headers from the request.
     *
     * @return $this
     */
    public function without_headers(array $headers)
    {
        foreach ($headers as $name) {
            $this->without_header($name);
        }
        return $this;
    }
    /**
     * Add an authorization token for the request.
     *
     * @return $this
     */
    public function with_token(string $token, string $type = 'Bearer')
    {
        return $this->with_header('Authorization', $type . ' ' . $token);
    }
    /**
     * Add a basic authentication header to the request with the given credentials.
     *
     * @return $this
     */
    public function with_basic_auth(string $username, string $password)
    {
        return $this->with_token(base64_encode("{$username}:{$password}"), 'Basic');
    }
    /**
     * Remove the authorization token from the request.
     *
     * @return $this
     */
    public function without_token()
    {
        return $this->without_header('Authorization');
    }
    /**
     * Flush all the configured headers.
     *
     * @return $this
     */
    public function flush_headers()
    {
        $this->default_headers = [];
        return $this;
    }
    /**
     * Define a set of server variables to be sent with the requests.
     *
     * @return $this
     */
    public function with_server_variables(array $server)
    {
        $this->server_variables = $server;
        return $this;
    }
    /**
     * Disable middleware for the test.
     *
     * @param  string|array|null  $middleware
     * @return $this
     */
    public function without_middleware($middleware = null)
    {
        if (is_null($middleware)) {
            $this->app->instance('middleware.disable', true);
            return $this;
        }
        foreach ((array) $middleware as $abstract) {
            $this->app->instance($abstract, new class
            {
                public function handle($request, $next)
                {
                    return $next($request);
                }
            });
        }
        return $this;
    }
    /**
     * Enable the given middleware for the test.
     *
     * @param  string|array|null  $middleware
     * @return $this
     */
    public function with_middleware($middleware = null)
    {
        if (is_null($middleware)) {
            unset($this->app['middleware.disable']);
            return $this;
        }
        foreach ((array) $middleware as $abstract) {
            unset($this->app[$abstract]);
        }
        return $this;
    }
    /**
     * Define additional cookies to be sent with the request.
     *
     * @return $this
     */
    public function with_cookies(array $cookies)
    {
        $this->default_cookies = array_merge($this->default_cookies, $cookies);
        return $this;
    }
    /**
     * Add a cookie to be sent with the request.
     *
     * @return $this
     */
    public function with_cookie(string $name, string $value)
    {
        $this->default_cookies[$name] = $value;
        return $this;
    }
    /**
     * Define additional cookies will not be encrypted before sending with the request.
     *
     * @return $this
     */
    public function with_unencrypted_cookies(array $cookies)
    {
        $this->unencrypted_cookies = array_merge($this->unencrypted_cookies, $cookies);
        return $this;
    }
    /**
     * Add a cookie will not be encrypted before sending with the request.
     *
     * @return $this
     */
    public function with_unencrypted_cookie(string $name, string $value)
    {
        $this->unencrypted_cookies[$name] = $value;
        return $this;
    }
    /**
     * Automatically follow any redirects returned from the response.
     *
     * @return $this
     */
    public function following_redirects()
    {
        $this->follow_redirects = true;
        return $this;
    }
    /**
     * Include cookies and authorization headers for JSON requests.
     *
     * @return $this
     */
    public function with_credentials()
    {
        $this->with_credentials = true;
        return $this;
    }
    /**
     * Disable automatic encryption of cookie values.
     *
     * @return $this
     */
    public function disable_cookie_encryption()
    {
        $this->encrypt_cookies = false;
        return $this;
    }
    /**
     * Set the referer header and previous URL session value from a given URL in order to simulate a previous request.
     *
     * @return $this
     */
    public function from(string $url)
    {
        $this->app['session']->set_previous_url($url);
        return $this->with_header('referer', $url);
    }
    /**
     * Set the referer header and previous URL session value from a given route in order to simulate a previous request.
     *
     * @param  mixed  $parameters
     * @return $this
     */
    public function from_route(Backed_Enum|string $name, $parameters = [])
    {
        return $this->from($this->app['url']->route($name, $parameters));
    }
    /**
     * Set the Precognition header to "true".
     *
     * @return $this
     */
    public function with_precognition()
    {
        return $this->with_header('Precognition', 'true');
    }
    /**
     * Visit the given URI with a GET request.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @return \Illuminate\Testing\TestResponse
     */
    public function get($uri, array $headers = [])
    {
        $server = $this->transform_headers_to_server_vars($headers);
        $cookies = $this->prepare_cookies_for_request();
        return $this->call('GET', $uri, [], $cookies, [], $server);
    }
    /**
     * Visit the given URI with a GET request, expecting a JSON response.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  int  $options
     * @return \Illuminate\Testing\TestResponse
     */
    public function get_json($uri, array $headers = [], $options = 0)
    {
        return $this->json('GET', $uri, [], $headers, $options);
    }
    /**
     * Visit the given URI with a POST request.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @return \Illuminate\Testing\TestResponse
     */
    public function post($uri, array $data = [], array $headers = [])
    {
        $server = $this->transform_headers_to_server_vars($headers);
        $cookies = $this->prepare_cookies_for_request();
        return $this->call('POST', $uri, $data, $cookies, [], $server);
    }
    /**
     * Visit the given URI with a POST request, expecting a JSON response.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  int  $options
     * @return \Illuminate\Testing\TestResponse
     */
    public function post_json($uri, array $data = [], array $headers = [], $options = 0)
    {
        return $this->json('POST', $uri, $data, $headers, $options);
    }
    /**
     * Visit the given URI with a PUT request.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @return \Illuminate\Testing\TestResponse
     */
    public function put($uri, array $data = [], array $headers = [])
    {
        $server = $this->transform_headers_to_server_vars($headers);
        $cookies = $this->prepare_cookies_for_request();
        return $this->call('PUT', $uri, $data, $cookies, [], $server);
    }
    /**
     * Visit the given URI with a PUT request, expecting a JSON response.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  int  $options
     * @return \Illuminate\Testing\TestResponse
     */
    public function put_json($uri, array $data = [], array $headers = [], $options = 0)
    {
        return $this->json('PUT', $uri, $data, $headers, $options);
    }
    /**
     * Visit the given URI with a PATCH request.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @return \Illuminate\Testing\TestResponse
     */
    public function patch($uri, array $data = [], array $headers = [])
    {
        $server = $this->transform_headers_to_server_vars($headers);
        $cookies = $this->prepare_cookies_for_request();
        return $this->call('PATCH', $uri, $data, $cookies, [], $server);
    }
    /**
     * Visit the given URI with a PATCH request, expecting a JSON response.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  int  $options
     * @return \Illuminate\Testing\TestResponse
     */
    public function patch_json($uri, array $data = [], array $headers = [], $options = 0)
    {
        return $this->json('PATCH', $uri, $data, $headers, $options);
    }
    /**
     * Visit the given URI with a DELETE request.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @return \Illuminate\Testing\TestResponse
     */
    public function delete($uri, array $data = [], array $headers = [])
    {
        $server = $this->transform_headers_to_server_vars($headers);
        $cookies = $this->prepare_cookies_for_request();
        return $this->call('DELETE', $uri, $data, $cookies, [], $server);
    }
    /**
     * Visit the given URI with a DELETE request, expecting a JSON response.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  int  $options
     * @return \Illuminate\Testing\TestResponse
     */
    public function delete_json($uri, array $data = [], array $headers = [], $options = 0)
    {
        return $this->json('DELETE', $uri, $data, $headers, $options);
    }
    /**
     * Visit the given URI with an OPTIONS request.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @return \Illuminate\Testing\TestResponse
     */
    public function options($uri, array $data = [], array $headers = [])
    {
        $server = $this->transform_headers_to_server_vars($headers);
        $cookies = $this->prepare_cookies_for_request();
        return $this->call('OPTIONS', $uri, $data, $cookies, [], $server);
    }
    /**
     * Visit the given URI with an OPTIONS request, expecting a JSON response.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  int  $options
     * @return \Illuminate\Testing\TestResponse
     */
    public function options_json($uri, array $data = [], array $headers = [], $options = 0)
    {
        return $this->json('OPTIONS', $uri, $data, $headers, $options);
    }
    /**
     * Visit the given URI with a HEAD request.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @return \Illuminate\Testing\TestResponse
     */
    public function head($uri, array $headers = [])
    {
        $server = $this->transform_headers_to_server_vars($headers);
        $cookies = $this->prepare_cookies_for_request();
        return $this->call('HEAD', $uri, [], $cookies, [], $server);
    }
    /**
     * Call the given URI with a JSON request.
     *
     * @param  string  $method
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  int  $options
     * @return \Illuminate\Testing\TestResponse
     */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        $files = $this->extract_files_from_data_array($data);
        $content = json_encode($data, $options);
        $headers = array_merge(['CONTENT_LENGTH' => mb_strlen($content, '8bit'), 'CONTENT_TYPE' => 'application/json', 'Accept' => 'application/json'], $headers);
        return $this->call($method, $uri, [], $this->prepare_cookies_for_json_request(), $files, $this->transform_headers_to_server_vars($headers), $content);
    }
    /**
     * Call the given URI and return the Response.
     *
     * @param  string  $method
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  array  $parameters
     * @param  array  $cookies
     * @param  array  $files
     * @param  array  $server
     * @param  string|null  $content
     * @return \Illuminate\Testing\TestResponse
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $kernel = $this->app->make(Http_Kernel::class);
        $files = array_merge($files, $this->extract_files_from_data_array($parameters));
        $symfony_request = Symfony_Request::create($this->prepare_url_for_request($uri), $method, $parameters, $cookies, $files, array_replace($this->server_variables, $server), $content);
        $response = $kernel->handle($request = $this->create_test_request($symfony_request));
        $kernel->terminate($request, $response);
        if ($this->follow_redirects) {
            $response = $this->follow_redirects($response);
        }
        return $this->create_test_response($response, $request);
    }
    /**
     * Turn the given URI into a fully-qualified URL.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     */
    protected function prepare_url_for_request($uri): string
    {
        $uri = $uri instanceof Uri ? $uri->value() : $uri;
        if (str_starts_with($uri, '/')) {
            $uri = substr($uri, 1);
        }
        return trim(url($uri), '/');
    }
    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     *
     * @return array
     */
    protected function transform_headers_to_server_vars(array $headers)
    {
        return (new Collection(array_merge($this->default_headers, $headers)))->map_with_keys(function ($value, $name): array {
            $name = strtr(strtoupper($name), '-', '_');
            return [$this->format_server_header_key($name) => $value];
        })->all();
    }
    /**
     * Format the header name for the server array.
     *
     * @param  string  $name
     */
    protected function format_server_header_key($name): string
    {
        if (!str_starts_with($name, 'HTTP_') && $name !== 'CONTENT_TYPE' && $name !== 'REMOTE_ADDR') {
            return 'HTTP_' . $name;
        }
        return $name;
    }
    /**
     * Extract the file uploads from the given data array.
     */
    protected function extract_files_from_data_array(array &$data): array
    {
        $files = [];
        foreach ($data as $key => $value) {
            if ($value instanceof Symfony_Uploaded_File) {
                $files[$key] = $value;
                unset($data[$key]);
            }
            if (is_array($value)) {
                $files[$key] = $this->extract_files_from_data_array($value);
                $data[$key] = $value;
            }
        }
        return $files;
    }
    /**
     * If enabled, encrypt cookie values for request.
     *
     * @return array
     */
    protected function prepare_cookies_for_request()
    {
        if (!$this->encrypt_cookies) {
            return array_merge($this->default_cookies, $this->unencrypted_cookies);
        }
        return (new Collection($this->default_cookies))->map(fn($value, string $key): string => encrypt(Cookie_Value_Prefix::create($key, app('encrypter')->get_key()) . $value, false))->merge($this->unencrypted_cookies)->all();
    }
    /**
     * If enabled, add cookies for JSON requests.
     *
     * @return array
     */
    protected function prepare_cookies_for_json_request()
    {
        return $this->with_credentials ? $this->prepare_cookies_for_request() : [];
    }
    /**
     * Follow a redirect chain until a non-redirect is received.
     *
     * @param  \Illuminate\Http\Response|\Illuminate\Testing\TestResponse  $response
     * @return \Illuminate\Http\Response|\Illuminate\Testing\TestResponse
     */
    protected function follow_redirects($response)
    {
        $this->follow_redirects = false;
        while ($response->is_redirect()) {
            $response = $this->get($response->headers->get('Location'));
        }
        return $response;
    }
    /**
     * Create the request instance used for testing from the given Symfony request.
     *
     * @return \Illuminate\Http\Request
     */
    protected function create_test_request(\Symfony\Component\Http_Foundation\Request $symfony_request)
    {
        return Request::create_from_base($symfony_request);
    }
    /**
     * Create the test response instance from the given response.
     *
     * @param  \Illuminate\Http\Response  $response
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Testing\TestResponse
     */
    protected function create_test_response($response, $request)
    {
        return tap(Test_Response::from_base_response($response, $request), function ($response): void {
            $response->with_exceptions($this->app->bound(Logged_Exception_Collection::class) ? $this->app->make(Logged_Exception_Collection::class) : new Logged_Exception_Collection());
        });
    }
}