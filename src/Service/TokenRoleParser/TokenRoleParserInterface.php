<?php

namespace Oxodao\QneOAuthBundle\Service\TokenRoleParser;

use Lcobucci\JWT\UnencryptedToken;

interface TokenRoleParserInterface
{
    /** @return array<string> */
    public function parse(UnencryptedToken $token): array;
}
