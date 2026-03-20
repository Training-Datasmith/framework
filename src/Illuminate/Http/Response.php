<?php

declare (strict_types=1);
namespace Illuminate\Http;

use ArrayObject;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use JsonSerializable;
use Symfony\Component\Http_Foundation\Response as SymfonyResponse;
use Symfony\Component\Http_Foundation\Response_Header_Bag;
class Response extends Symfony_Response
{
    use Response_Trait, Macroable {
        Macroable::__call as macroCall;
    }
    /**
     * Create a new HTTP response.
     *
     * @param  mixed  $content
     * @param  int  $status
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($content = '', $status = 200, array $headers = [])
    {
        $this->headers = new Response_Header_Bag($headers);
        $this->set_content($content);
        $this->set_status_code($status);
        $this->set_protocol_version('1.0');
    }
    /**
     * Get the response content.
     */
    #[\Override]
    public function get_content(): string|false
    {
        return transform(parent::get_content(), fn($content): mixed => $content, '');
    }
    /**
     * Set the content on the response.
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function set_content(mixed $content): static
    {
        $this->original = $content;
        // If the content is "JSONable" we will set the appropriate header and convert
        // the content to JSON. This is useful when returning something like models
        // from routes that will be automatically transformed to their JSON form.
        if ($this->should_be_json($content)) {
            $this->header('Content-Type', 'application/json');
            $content = $this->morph_to_json($content);
            if ($content === false) {
                throw new InvalidArgumentException(json_last_error_msg());
            }
        } elseif ($content instanceof Renderable) {
            $content = $content->render();
        }
        parent::set_content($content);
        return $this;
    }
    /**
     * Determine if the given content should be turned into JSON.
     *
     * @param  mixed  $content
     * @return bool
     */
    protected function should_be_json($content)
    {
        return $content instanceof Arrayable || $content instanceof Jsonable || $content instanceof ArrayObject || $content instanceof JsonSerializable || is_array($content);
    }
    /**
     * Morph the given content into JSON.
     *
     * @param  mixed  $content
     * @return string|false
     */
    protected function morph_to_json($content)
    {
        if ($content instanceof Jsonable) {
            return $content->to_json();
        }
        if ($content instanceof Arrayable) {
            return json_encode($content->to_array());
        }
        return json_encode($content);
    }
}