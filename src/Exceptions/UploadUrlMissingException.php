<?php

namespace DocumentAgent\Exceptions;

class UploadUrlMissingException extends DocumentAgentException
{
    public function __construct(string $message = 'upload_url_missing')
    {
        parent::__construct($message);
    }
}
