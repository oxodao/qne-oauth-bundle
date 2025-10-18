<?php

namespace Oxodao\QneOAuthBundle\Behavior\Impl;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Oxodao\QneOAuthBundle\Behavior\OAuthUserInterface;

/**
 * @phpstan-require-implements OAuthUserInterface
 *
 * @phpstan-ignore-next-line trait.unused
 */
trait OAuthUserTrait
{
    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
    protected ?string $oauthUserId = null;

    #[ORM\Column(type: Types::STRING, length: 4096, nullable: true)]
    protected ?string $oauthOfflineToken = null;

    public function getOAuthUserId(): ?string
    {
        return $this->oauthUserId;
    }

    public function setOAuthUserId(?string $oauthUserId): static
    {
        $this->oauthUserId = $oauthUserId;

        return $this;
    }

    public function getOAuthOfflineToken(): ?string
    {
        return $this->oauthOfflineToken;
    }

    public function setOAuthOfflineToken(?string $oauthOfflineToken): static
    {
        $this->oauthOfflineToken = $oauthOfflineToken;

        return $this;
    }
}
