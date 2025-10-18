<?php

namespace Oxodao\QneOAuthBundle\Model;

use Lcobucci\JWT\Token\DataSet;

class OAuthUserInfos
{
    public readonly string $oauthUserId;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $email = null;
    public ?string $locale = null;
    public ?string $username = null;

    /** @var array<string> */
    public array $roles = [];

    public function __construct(
        public readonly DataSet $claims,
    ) {
        $this->oauthUserId = $claims->get('sub'); // scope: openid

        $this->firstName = $claims->get('given_name'); // scope: profile
        $this->lastName = $claims->get('family_name'); // scope: profile
        $this->email = $claims->get('email'); // scope: email
        $this->locale = $claims->get('locale'); // scope: idk, profile?
        $this->username = $claims->get('preferred_username'); // scope: idk
    }
}
