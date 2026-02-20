<?php

namespace DocumentAgent\Exceptions;

class ScanFailedException extends DocumentAgentException
{
    public function __construct(string $message = 'scan_failed')
    {
        parent::__construct($message);
    }
}
