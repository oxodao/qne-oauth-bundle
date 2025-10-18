<?php

namespace Oxodao\QneOAuthBundle\Service;

use Oxodao\QneOAuthBundle\Behavior\OAuthUserInterface;
use Oxodao\QneOAuthBundle\Model\OAuthUserInfos;

interface OAuthUserUpdaterInterface
{
    public function update(?OAuthUserInterface $user, OAuthUserInfos $infos): OAuthUserInterface;
}
