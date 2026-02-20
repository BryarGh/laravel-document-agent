<?php

namespace DocumentAgent\Exceptions;

class ScanTimeoutException extends DocumentAgentException
{
    public function __construct(string $message = 'scan_timeout')
    {
        parent::__construct($message);
    }
}
