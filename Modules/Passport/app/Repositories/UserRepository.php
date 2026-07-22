<?php

namespace Modules\Passport\Repositories;

use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\ClientRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Modules\Passport\Http\Exception\OAuthServerException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        protected Hasher $hasher,
        protected ClientRepository $clientRepository
    ) {}

    /**
     * {@inheritdoc}
     *
     * @throws OAuthServerException
     */
    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        $clientId = $clientEntity->getIdentifier();
        $client = $this->clientRepository->find($clientId);

        $provider = $client && $client->provider
            ? config('auth.guards.'.$client->provider.'.provider')
            : config('auth.guards.api.provider');

        if (is_null($model = config('auth.providers.'.$provider.'.model'))) {
            throw new RuntimeException('Unable to determine authentication model from configuration.');
        }

        if (method_exists($model, 'findAndValidateForPassport')) {
            $user = (new $model)->findAndValidateForPassport($username, $password);

            if (! $user) {
                return null;
            }

            $this->enforceSingleSession($user, $clientId);

            return new User($user->getAuthIdentifier());
        }

        $user = method_exists($model, 'findForPassport')
            ? (new $model)->findForPassport($username)
            : (new $model)->where('email', $username)->first();

        if (! $user) {
            throw OAuthServerException::customException(
                trans('passport::passport.messages.invalid_credentials'),
                BaseResponse::HTTP_UNAUTHORIZED,
                'invalid_credentials'
            );
        }

        // A disabled account must not be able to obtain a token, even with
        // correct credentials.
        if ($user->status == config('core.disabled')) {
            throw OAuthServerException::customException(
                trans('passport::passport.messages.disabled_account'),
                BaseResponse::HTTP_UNAUTHORIZED,
                'inactive_account'
            );
        }

        $passwordIsValid = method_exists($user, 'validateForPassportPasswordGrant')
            ? $user->validateForPassportPasswordGrant($password)
            : $this->hasher->check($password, $user->getAuthPassword());

        if (! $passwordIsValid) {
            throw OAuthServerException::customException(
                trans('passport::passport.messages.invalid_credentials'),
                BaseResponse::HTTP_UNAUTHORIZED,
                'invalid_credentials'
            );
        }

        $this->enforceSingleSession($user, $clientId);

        return new User($user->getAuthIdentifier());
    }

    /**
     * Revoke the user's existing tokens for this client when the customer
     * settings disallow concurrent logins.
     *
     * The setting key is "multi_device_login", declared in
     * Modules/Customer/config/settings.php.
     */
    protected function enforceSingleSession(mixed $user, string $clientId): void
    {
        if (settings('customer', 'multi_device_login') != config('core.no')) {
            return;
        }

        if (! method_exists($user, 'tokens')) {
            return;
        }

        $tokens = $user->tokens()
            ->where('client_id', $clientId)
            ->where('revoked', false)
            ->with('refreshToken')
            ->get();

        foreach ($tokens as $token) {
            // The refresh token must go too, otherwise the previous session
            // can mint a new access token and defeat the setting.
            $token->refreshToken?->revoke();
            $token->revoke();
        }
    }
}
