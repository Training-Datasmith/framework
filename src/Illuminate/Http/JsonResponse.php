<?php

declare (strict_types=1);
namespace Illuminate\Http;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use JsonSerializable;
use Symfony\Component\Http_Foundation\Json_Response as BaseJsonResponse;
class Json_Response extends Base_Json_Response
{
    use Response_Trait, Macroable {
        Macroable::__call as macroCall;
    }
    /**
     * Create a new JSON response instance.
     *
     * @param  mixed  $data
     * @param  int  $status
     * @param  array  $headers
     * @param  int  $options
     * @param  bool  $json
     */
    public function __construct($data = null, $status = 200, $headers = [], $options = 0, $json = false)
    {
        $this->encoding_options = $options;
        parent::__construct($data, $status, $headers, $json);
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public static function from_json_string(?string $data = null, int $status = 200, array $headers = []): static
    {
        return new static($data, $status, $headers, 0, true);
    }
    /**
     * Sets the JSONP callback.
     *
     * @param  string|null  $callback
     * @return $this
     */
    public function with_callback($callback = null)
    {
        return $this->set_callback($callback);
    }
    /**
     * Get the decoded JSON data from the response.
     *
     * @param  bool  $assoc
     * @param  int  $depth
     * @return mixed
     */
    public function get_data($assoc = false, $depth = 512)
    {
        return json_decode($this->data, $assoc, $depth);
    }
    /**
     * {@inheritdoc}
     *
     *
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function set_data($data = []): static
    {
        $this->original = $data;
        // Ensure json_last_error() is cleared...
        json_decode('[]');
        $this->data = match (true) {
            $data instanceof Jsonable => $data->to_json($this->encoding_options),
            $data instanceof JsonSerializable => json_encode($data->jsonSerialize(), $this->encoding_options),
            $data instanceof Arrayable => json_encode($data->to_array(), $this->encoding_options),
            default => json_encode($data, $this->encoding_options),
        };
        if (!$this->has_valid_json(json_last_error())) {
            throw new InvalidArgumentException(json_last_error_msg());
        }
        return $this->update();
    }
    /**
     * Determine if an error occurred during JSON encoding.
     *
     * @param  int  $jsonError
     * @return bool
     */
    protected function has_valid_json($json_error)
    {
        if ($json_error === JSON_ERROR_NONE) {
            return true;
        }
        return $this->has_encoding_option(JSON_PARTIAL_OUTPUT_ON_ERROR) && in_array($json_error, [JSON_ERROR_RECURSION, JSON_ERROR_INF_OR_NAN, JSON_ERROR_UNSUPPORTED_TYPE]);
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function set_encoding_options($options): static
    {
        $this->encoding_options = (int) $options;
        return $this->set_data($this->get_data());
    }
    /**
     * Determine if a JSON encoding option is set.
     *
     * @param  int  $option
     * @return bool
     */
    public function has_encoding_option($option)
    {
        return (bool) ($this->encoding_options & $option);
    }
}