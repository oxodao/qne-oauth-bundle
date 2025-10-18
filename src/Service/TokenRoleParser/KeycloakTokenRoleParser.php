<?php

namespace Oxodao\QneOAuthBundle\Service\TokenRoleParser;

use Lcobucci\JWT\UnencryptedToken;

readonly class KeycloakTokenRoleParser implements TokenRoleParserInterface
{
    public function __construct(
        private string $kcAppName,
    ) {
    }

    /** @return array<string> */
    public function parse(UnencryptedToken $token): array
    {
        $claims = $token->claims();
        if (!$claims->has('resource_access')) {
            return [];
        }

        $ra = $claims->get('resource_access');

        if (
            !\is_array($ra)
            || !isset($ra[$this->kcAppName])
            || !isset($ra[$this->kcAppName]['roles'])
            || !\is_array($ra[$this->kcAppName]['roles'])
        ) {
            return [];
        }

        return $ra[$this->kcAppName]['roles'];
    }
}
