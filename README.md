# QneOAuthBundle

Quick'n'Easy OAuth Bundle is a Symfony bundle to easily add OAuth authentication to your API Platform project.

It piggy-backs on LexikJWTAuthenticationBundle and GesdinetJWTRefreshTokenBundle to provide a seamless integration of OAuth and JWT authentication.

Albeit a not the cleanest way to do it, the OAuth tokens are NO LONGER used once the user is connected. This let you use the same setup as for regular user without having to worry about what's in the token.

It also lets you customize the token for your app however you want (e.g. use Mercure).

Only offline oauth token are used server-side to refresh the user information (like roles) when your refresh token is called.

Currently only tested with Keycloak, it provides the following feature:
- Generate the OAuth login URL (Or directly redirect to it depending on your setup)
- Exchage the OAuth code for a JWT token
- Bring your own user entity
- Role synchronization
- Works in combination with standard password authentication

## Warning

Although I tried to stay as close as possible to the OAuth2 standard, this was only tested with Keycloak, so not sure if it work with other providers.

It is also pretty tailored to my use case, so if you have any issue or want a feature, please open a PR.

For now, it only support ONE OAuth provider per project.

It does not support CSRF protection through the state parameter.

For now ROLES SYNCHRONIZATION IS MANDATORY ! At some point I'd like to have an option so that the provider's roles doesn't matter (Which would also stop the need for the user to log in through OAuth every time their offline token expires).

## Usage

### Keycloak

As an exemple, and because this is the only one currently implemented, let's setup Keycloak.

First we create a client in the master realm:
- Go to the admin interface, clients, "Create a client"
- Fill the ID of the client: my_project and set a name / description.
- Let the default settings on the second screen except "Client authentication" which you should enable.
- On the last screen, fill the values:
    - Root URL: `http://localhost` => Your app frontend base url
    - Home URL: `/`
    - Valid Redirect URIs: `/oauth-callback` => The frontend URL that your OAuth provider will redirect to after login
- In the roles you can create an "ROLE_ADMIN" role (or any role your app will need)
- Then in "Groups" you can create an "admin" group and assign the "admin" role to it in the mapping tab.
- Finally you can add / remove your users in the member tab of the group.

By default, the user will have to log in again every 30 days because of the offline token expiration.

You can change that by going into the realm settings and updating the "Offline Session Max" value in the "Tokens" tab.

You can get the `client_secret` in the "Credentials" tab of your client.

### Bundle setup

```bash
$ composer require oxodao/qne-oauth-bundle
```

Then enable the bundle in your `config/bundles.php`:

```php
<?php
return [
    // ...
    Oxodao\QneOAuthBundle\QneOauthBundle::class => ['all' => true],
];
```

First we need to setup the bundle, including the role parser, for keycloak here's how to do it in `config/packages/qne_oauth.yaml`:
```yaml
services:
  kc_role_parser:
    class: Oxodao\QneOAuthBundle\Service\TokenRoleParser\KeycloakTokenRoleParser
    arguments:
      $kcAppName: 'my_project' # The keycloak app name, which is visible in the `resource_access` field of the userinfo response
       
qne_oauth:
  url: '%env(OAUTH_BASE_URL)%' # The base URL of your OAuth provider; for Keycloak it is something like https://YOUR_KEYCLOAK_URL/auth/realms/master/protocol/openid-connect
  client_id: '%env(OAUTH_CLIENT_ID)%' # The client ID
  client_secret: '%env(OAUTH_CLIENT_SECRET)%' # The client secret
  redirect_url: '%env(PUBLIC_URL)%/login-callback' # The frontend URL where the OAuth provider will redirect after login which will in turn call your API
  role_parser: 'kc_role_parser' # The role parser we created above
  user_updater: 'App\Service\OAuthUserUpdater' # A service implementing Oxodao\QneOAuthBundle\Service\OAuthUserUpdaterInterface that will return a non-persisted instance of your user based on the OAuth user infos
  user_entity: 'App\Entity\User' # Your user entity class
  login_url_as_json: true # Optional, when true the /login_oauth route will return the login URL as JSON instead of redirecting directly. Defaults to false
```

We will also setup the routes in `config/routes/qne_oauth.yaml`:

```yaml
qne_oauth_login_url:
  path: '/api/login_oauth'
  methods: [GET]
  controller: Oxodao\QneOAuthBundle\Controller\QneOAuthController::getLoginUrl

qne_oauth_callback:
  path: '/api/login_oauth'
  methods: [POST]
  controller: Oxodao\QneOAuthBundle\Controller\QneOAuthController::loginCallback
```

Finally, in your `security.yaml` we'll hook the custom UserChecker that take care of updating the roles on each token creation/refresh
```yaml
[...]

# This is your GesdinetJWTRefreshTokenBundle firewall
login_refresh:
  pattern: ^/api/login_refresh
  stateless: true
  provider: user_provider
  user_checker: Oxodao\QneOAuthBundle\Security\OAuthUserChecker # <--- Add this line
  refresh_jwt:
    check_path: /api/login_refresh

login:
  pattern: ^/api/login
  stateless: true
  provider: user_provider
  user_checker: Oxodao\QneOAuthBundle\Security\OAuthUserChecker # <--- Add this line
  json_login:
    check_path: /api/login
    success_handler: lexik_jwt_authentication.handler.authentication_success
    failure_handler: lexik_jwt_authentication.handler.authentication_failure
[...]
```

Once the main config is ready, you'll need to add some helpers on your entity:

```php
class User implements [...], OAuthUserInterface
{
    use OAuthUserTrait;
}
```

Here's an example of the `OAuthUserUpdater`:
```php
<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\Language;
use Oxodao\QneOAuthBundle\Behavior\OAuthUserInterface;
use Oxodao\QneOAuthBundle\Model\OAuthUserInfos;
use Oxodao\QneOAuthBundle\Service\OAuthUserUpdaterInterface;

readonly class OAuthUserUpdater implements OAuthUserUpdaterInterface
{
    public function __construct(
        private UniqueUsernameStrategy $uniqueUsernameStrategy,
    )
    {
    }

    /**
     * Here we can update the user entity with the information received from the OAuth provider.
     * We NEED to setup everything required to persist the entity as it is also
     * used to create new users.
     */
    public function update(?OAuthUserInterface $user, OAuthUserInfos $infos): OAuthUserInterface
    {
        if (null === $user) {
            $user = new User();

            $uniqueUsername = $this->uniqueUsernameStrategy->generate($infos);
            if (!$uniqueUsername) {
                throw new \LogicException('Failed to generate a unique username');
            }

            // The username can only be set once, at creation time
            $user->setUsername($uniqueUsername);
        }

        if (!$user instanceof User) {
            throw new \InvalidArgumentException('User must be an instance of App\Entity\User');
        }

        $user->setEmail($infos->email);
        $user->setLanguage(Language::fromAlpha2($infos->locale));

        return $user;
    }
}
```

And the basic `UniqueUsernameStrategy` used to generate unique usernames:
```php
<?php

namespace App\Service;

use App\Repository\UserRepository;
use Oxodao\QneOAuthBundle\Model\OAuthUserInfos;

readonly class UniqueUsernameStrategy
{
    public function __construct(
        private UserRepository $userRepository,
    )
    {
    }

    public function generate(OAuthUserInfos $infos): ?string
    {
        $baseUsername = $infos->username ?? '';
        if (\strlen($baseUsername) > 0) {
            $user = $this->userRepository->findOneByUsername($baseUsername);
            if (null === $user) {
                return $baseUsername;
            }
        }

        // The OAuth preferred_username is NOT GUARANTEED to be unique, so we try to append a random suffix to it if a user with the same username already exists
        if (\strlen($baseUsername) > 0) {
            $tries = 0;

            while ($tries < 20) {
                $randomSuffix = \random_int(1, 99999);

                $username = \sprintf('%s_%05d', $baseUsername, $randomSuffix);
                $user = $this->userRepository->findOneByUsername($username);
                if (null === $user) {
                    return $username;
                }

                ++$tries;
            }
        }

        return null;
    }
}
```

This will add the following info on your user:
- OAuth User Id: The unique ID from the OAuth provider
- OAuth Offline Token: An offline refresh token that lets the bundle refresh the user infos

As soon as your user has these info, the bundle will consider them as "OAuth users" and will try to update their infos on each token refresh and login.

/!\ This means that if the user's offline token expires, the user won't be able to login in any way unless they go through the OAuth login once again. /!\

### Authenticating

To authenticate through OAuth, you need to do a GET request to `/api/login_oauth`.

If you set `login_url_as_json` to true, you'll get a JSON response with the login URL, otherwise you'll be redirected directly to the OAuth provider.

Then, the OAuth provider will redirect to your frontend URL with a `code` query parameter.

From your frontend, you need to do a POST request to `/api/login_oauth` with the body:
```json
{"code": "the_code_from_the_query_parameters"}
```

You will get a standard token / refresh token response.

## License

Copyright 2025 Oxodao

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
