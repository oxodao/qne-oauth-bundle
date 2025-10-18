<?php

namespace Oxodao\QneOAuthBundle\Behavior;

interface OAuthUserInterface
{
    public function getOAuthUserId(): ?string;

    public function setOAuthUserId(?string $id): static;

    public function getOAuthOfflineToken(): ?string;

    public function setOAuthOfflineToken(?string $token): static;

    /** @return array<string> */
    public function getRoles(): array;

    /** @param array<string> $roles */
    public function setRoles(array $roles): static;
}
