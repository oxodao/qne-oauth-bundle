<?php

namespace Oxodao\QneOAuthBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Oxodao\QneOAuthBundle\Behavior\OAuthUserInterface;
use Oxodao\QneOAuthBundle\Model\OAuthLoginResponse;
use Oxodao\QneOAuthBundle\Model\OAuthUserInfos;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class OAuthUserManager
{
    public function __construct(
        private EntityManagerInterface $emi,
        #[Autowire(service: 'qne_oauth.user_updater')]
        private OAuthUserUpdaterInterface $userUpdater,
    ) {
    }

    public function createAndPersistUser(OAuthUserInfos $userInfos, OAuthLoginResponse $loginInfos): OAuthUserInterface
    {
        $user = $this->userUpdater->update(null, $userInfos);

        return $this->updateUser($user, $userInfos, $loginInfos);
    }

    public function updateAndPersistUser(
        OAuthUserInterface $user,
        OAuthUserInfos $userInfos,
        OAuthLoginResponse $loginInfos,
    ): OAuthUserInterface {
        return $this->updateUser(
            $this->userUpdater->update($user, $userInfos),
            $userInfos,
            $loginInfos,
        );
    }

    private function updateUser(OAuthUserInterface $user, OAuthUserInfos $userInfos, OAuthLoginResponse $loginInfos): OAuthUserInterface
    {
        $user->setOAuthUserId($userInfos->oauthUserId);
        $user->setOAuthOfflineToken($loginInfos->refreshToken);
        $user->setRoles($userInfos->roles);

        $this->emi->persist($user);
        $this->emi->flush();

        return $user;
    }
}
