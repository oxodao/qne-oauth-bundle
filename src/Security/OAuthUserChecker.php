<?php

namespace Oxodao\QneOAuthBundle\Security;

use Oxodao\QneOAuthBundle\Behavior\OAuthUserInterface;
use Oxodao\QneOAuthBundle\Exception\OfflineTokenExpiredException;
use Oxodao\QneOAuthBundle\Service\OAuthClient;
use Oxodao\QneOAuthBundle\Service\OAuthUserManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class OAuthUserChecker implements UserCheckerInterface
{
    public function __construct(
        private OAuthClient $client,
        private OAuthUserManager $userManager,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        // Not a OAuth user, we don't care
        if (!$user instanceof OAuthUserInterface) {
            return;
        }

        // User has never logged in through OAuth, we don't care
        if (!$user->getOAuthUserId()) {
            return;
        }

        // The offline token is missing, we can't fetch the roles => reject the authentication
        if (!$user->getOAuthOfflineToken()) {
            throw new OfflineTokenExpiredException();
        }

        try {
            $loginInfos = $this->client->refreshToken($user->getOAuthOfflineToken());
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to refresh OAuth token: ' . $e->getMessage(), previous: $e);
        }

        try {
            $userInfos = $this->client->parseToken($loginInfos->accessToken);
            $this->userManager->updateAndPersistUser(
                $user,
                $userInfos,
                $loginInfos,
            );
        } catch (\Throwable $e) {
            if ($e instanceof OfflineTokenExpiredException) {
                throw $e;
            }

            // @TODO: Logger info + 401
            throw new \RuntimeException('Unable to refresh OAuth token: ' . $e->getMessage(), previous: $e);
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Nothing to do in the post-auth
    }
}
