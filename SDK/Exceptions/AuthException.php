<?php

namespace AvecdoSDK\Exceptions;

use Exception;

class AuthException extends Exception
{
    private $payload;

    public function setPayload($payload) 
    {
        $this->payload = $payload;
    }

    public function getPayload()
    {
        return $this->payload;
    }
}
