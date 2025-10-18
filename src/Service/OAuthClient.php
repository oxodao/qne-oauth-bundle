<?php

namespace Oxodao\QneOAuthBundle\Service;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Oxodao\QneOAuthBundle\Exception\OfflineTokenExpiredException;
use Oxodao\QneOAuthBundle\Model\OAuthLoginResponse;
use Oxodao\QneOAuthBundle\Model\OAuthUserInfos;
use Oxodao\QneOAuthBundle\Service\TokenRoleParser\TokenRoleParserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class OAuthClient
{
    private string $oauthUrl;
    private string $redirectUrl;

    public function __construct(
        private HttpClientInterface $http,
        private SerializerInterface $serializer,
        #[Autowire(service: 'qne_oauth.role_parser')]
        private TokenRoleParserInterface $roleParser,
        #[Autowire(param: 'qne_oauth.client_id')]
        private string $clientId,
        #[Autowire(param: 'qne_oauth.client_secret')]
        private string $clientSecret,
        #[Autowire(param: 'qne_oauth.url')]
        string $oauthUrl,
        #[Autowire(param: 'qne_oauth.redirect_url')]
        string $redirectUrl,
    ) {
        $this->oauthUrl = \trim(\rtrim($oauthUrl, '/'));
        $this->redirectUrl = \trim(\rtrim($redirectUrl, '/'));
    }

    public function generateLoginUrl(): string
    {
        $baseUrl = $this->oauthUrl . '/auth?';

        $qs = \http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => 'openid profile email offline_access',
            'state' => \bin2hex(\random_bytes(5)), // @TODO: Handle CSRF protection
        ]);

        return $baseUrl . $qs;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ExceptionInterface
     */
    public function exchangeToken(string $code): OAuthLoginResponse
    {
        try {
            // Exchange the code from the frontend for a oAuth access token
            $resp = $this->http->request('POST', $this->oauthUrl . '/token', [
                'body' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'code' => $code,
                    'redirect_uri' => $this->redirectUrl,
                ],
            ]);

            return $this->serializer->deserialize($resp->getContent(), OAuthLoginResponse::class, 'json');
        } catch (ClientExceptionInterface $e) {
            $data = $e->getResponse()->getContent(false);

            // Meh, @TODO: do better
            if (\is_string($data)) {
                $data = \json_decode($data, true);

                if ($data && 'invalid_grant' === $data['error']) {
                    throw new BadRequestHttpException($data['error_description']);
                }
            }

            throw $e;
        }
    }

    /**
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ExceptionInterface
     */
    public function refreshToken(string $refreshToken): OAuthLoginResponse
    {
        try {
            // Exchange the code from the frontend for a oAuth access token
            $resp = $this->http->request('POST', $this->oauthUrl . '/token', [
                'body' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                ],
            ]);

            return $this->serializer->deserialize($resp->getContent(), OAuthLoginResponse::class, 'json');
        } catch (ServerExceptionInterface|ClientExceptionInterface $e) {
            $data = $e->getResponse()->getContent(false);

            if (\is_string($data)) {
                $data = \json_decode($data, true);

                if ($data && 'invalid_grant' === $data['error']) {
                    throw new OfflineTokenExpiredException();
                }
            }

            throw $e;
        }
    }

    /**
     * @param non-empty-string $token
     */
    public function parseToken(string $token): OAuthUserInfos
    {
        $parser = new Parser(new JoseEncoder());

        /** @var UnencryptedToken $jwt */
        $jwt = $parser->parse($token);
        $claims = $jwt->claims();

        $userInfos = new OAuthUserInfos($claims);
        $userInfos->roles = $this->roleParser->parse($jwt);

        return $userInfos;
    }
}
