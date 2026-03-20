<?php

declare (strict_types=1);
namespace Illuminate\Http\Client;

class Batch_In_Progress_Exception extends Http_Client_Exception
{
    public function __construct()
    {
        parent::__construct('You cannot add requests to a batch that is already in progress.');
    }
}