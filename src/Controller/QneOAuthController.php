<?php

namespace Oxodao\QneOAuthBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Oxodao\QneOAuthBundle\Behavior\OAuthUserInterface;
use Oxodao\QneOAuthBundle\Service\OAuthClient;
use Oxodao\QneOAuthBundle\Service\OAuthUserManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class QneOAuthController
{
    public function __construct(
        private OAuthClient $client,
        #[Autowire(param: 'qne_oauth.login_url_as_json')]
        private bool $loginUrlAsJson,
        #[Autowire(param: 'qne_oauth.user_entity')]
        private string $userEntityClass,
        private OAuthUserManager $userManager,
        private EntityManagerInterface $emi,
        private AuthenticationSuccessHandler $authenticationSuccessHandler,
    ) {
        if (!\class_exists($this->userEntityClass)) {
            throw new \InvalidArgumentException(\sprintf('The user entity class "%s" does not exist', $this->userEntityClass));
        }
        if (!\is_subclass_of($this->userEntityClass, OAuthUserInterface::class)) {
            throw new \InvalidArgumentException(\sprintf('The user entity class "%s" must implement the OAuthUserInterface', $this->userEntityClass));
        }
    }

    public function getLoginUrl(): Response
    {
        if ($this->loginUrlAsJson) {
            return new JsonResponse([
                'url' => $this->client->generateLoginUrl(),
            ]);
        }

        return new RedirectResponse($this->client->generateLoginUrl());
    }

    public function loginCallback(Request $request): Response
    {
        $data = \json_decode($request->getContent(), true);

        if (empty($data['code'])) {
            return new JsonResponse(data: ['error' => 'Authorization code is missing'], status: 400);
        }

        try {
            $loginResponse = $this->client->exchangeToken($data['code']);
            $userInfos = $this->client->parseToken($loginResponse->accessToken);

            /** @var OAuthUserInterface $user */
            $user = $this->emi->getRepository($this->userEntityClass)->findOneBy([
                'oauthUserId' => $userInfos->oauthUserId,
            ]);

            if (!$user) {
                $user = $this->userManager->createAndPersistUser($userInfos, $loginResponse);
            } else {
                $this->userManager->updateAndPersistUser(
                    $user,
                    $userInfos,
                    $loginResponse,
                );
            }

            // Maybe we should have a better way of doing this, idk for now
            if (!$user instanceof UserInterface) {
                throw new \RuntimeException('The user entity must implement the UserInterface');
            }

            return $this->authenticationSuccessHandler->handleAuthenticationSuccess($user);
        } catch (\Throwable $e) {
            return new JsonResponse(data: ['error' => 'Unable to exchange token: ' . $e->getMessage()], status: 401);
        }
    }
}
