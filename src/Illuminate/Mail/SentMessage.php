<?php

declare(strict_types=1);

namespace Illuminate\Mail;

use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;

/**
 * @mixin \Symfony\Component\Mailer\SentMessage
 */
class SentMessage
{
    use ForwardsCalls;

    /**
     * Create a new SentMessage instance.
     */
    public function __construct(
        /**
         * The Symfony SentMessage instance.
         */
        protected \Symfony\Component\Mailer\SentMessage $sentMessage
    ) {
    }

    /**
     * Get the underlying Symfony Email instance.
     */
    public function getSymfonySentMessage(): \Symfony\Component\Mailer\SentMessage
    {
        return $this->sentMessage;
    }

    /**
     * Dynamically pass missing methods to the Symfony instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo($this->sentMessage, $method, $parameters);
    }

    /**
     * Get the serializable representation of the object.
     *
     * @return array
     */
    public function __serialize()
    {
        $hasAttachments = (new Collection($this->sentMessage->getOriginalMessage()->getAttachments()))->isNotEmpty();

        return [
            'hasAttachments' => $hasAttachments,
            'sentMessage' => $hasAttachments ? base64_encode(serialize($this->sentMessage)) : $this->sentMessage,
        ];
    }

    /**
     * Marshal the object from its serialized data.
     *
     * @return void
     */
    public function __unserialize(array $data)
    {
        $hasAttachments = ($data['hasAttachments'] ?? false) === true;

        $this->sentMessage = $hasAttachments ? unserialize(base64_decode((string) $data['sentMessage'])) : $data['sentMessage'];
    }
}
