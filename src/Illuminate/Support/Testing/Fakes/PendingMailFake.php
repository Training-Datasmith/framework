<?php

declare(strict_types=1);

namespace Illuminate\Support\Testing\Fakes;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\PendingMail;

class PendingMailFake extends PendingMail
{
    /**
     * Create a new instance.
     *
     * @param  \Illuminate\Support\Testing\Fakes\MailFake  $mailer
     */
    public function __construct($mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Send a new mailable message instance.
     */
    public function send(Mailable $mailable): void
    {
        $this->mailer->send($this->fill($mailable));
    }

    /**
     * Send a new mailable message instance synchronously.
     */
    public function sendNow(Mailable $mailable): void
    {
        $this->mailer->sendNow($this->fill($mailable));
    }

    /**
     * Push the given mailable onto the queue.
     *
     * @return mixed
     */
    public function queue(Mailable $mailable)
    {
        return $this->mailer->queue($this->fill($mailable));
    }
}
