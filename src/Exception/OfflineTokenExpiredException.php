<?php

namespace Oxodao\QneOAuthBundle\Exception;

class OfflineTokenExpiredException extends QneOAuthException
{
    public function __construct()
    {
        parent::__construct('The offline token has expired or is invalid. The user must login through OAuth again once to obtain a new offline token.');
    }
}
