<?php

namespace Oxodao\QneOAuthBundle\Model;

use Symfony\Component\Serializer\Attribute\SerializedName;

class OAuthLoginResponse
{
    /**
     * @param non-empty-string $accessToken
     * @param non-empty-string $refreshToken
     */
    public function __construct(
        #[SerializedName('access_token')]
        public string $accessToken,
        #[SerializedName('refresh_token')]
        public string $refreshToken,
        #[SerializedName('expires_in')]
        public int $tokenExpiresIn,
        #[SerializedName('refresh_expires_in')]
        public int $refreshTokenExpiresIn,
    ) {
    }
}
