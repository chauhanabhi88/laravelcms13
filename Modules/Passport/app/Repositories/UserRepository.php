<?php

namespace Modules\Passport\Repositories;

use RuntimeException;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\ClientRepository;
use Illuminate\Contracts\Hashing\Hasher;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
// use Modules\Passport\Http\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    /**
     * The hasher implementation.
     *
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    protected $hasher;

    protected $clientRepository;

    /**
     * Create a new repository instance.
     *
     * @param  \Illuminate\Hashing\HashManager  $hasher
     * @return void
     */
    public function __construct(
        Hasher $hasher,
        ClientRepository $clientRepository
    ) {
        $this->hasher = $hasher;
        $this->clientRepository = $clientRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserEntityByUserCredentials( 
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        $allowMultipleLoginForCustomer = settings('customer', 'allow_multiple_login');
        $clientId = $clientEntity->getIdentifier();
        $client = $this->clientRepository->find($clientId);
        $provider = config('auth.guards.api.provider');
        if($client && $client->provider) {
            $provider = config('auth.guards.'.$client->provider.'.provider');
        }
        
        if (is_null($model = config('auth.providers.'.$provider.'.model'))) {
            throw new RuntimeException('Unable to determine authentication model from configuration.');
        }

        if (method_exists($model, 'findAndValidateForPassport')) {
            $user = (new $model)->findAndValidateForPassport($username, $password);

            if (! $user) {
                return null;
            }

            return new User($user->getAuthIdentifier());
        }
        if (method_exists($model, 'findForPassport')) {
            $user = (new $model)->findForPassport($username);
        } else {
            $userModel = new $model;
            $user = (new $model)->where('email', $username)->first();
        }

        if (! $user) {
            throw new OAuthServerException(trans("passport::passport.messages.invalid_credentials"), 6, "invalid_credentials", 401);
            return null;
        } elseif ($user->status == config("core.disabled")) { // if user account disabled then do not allow to login
            throw new OAuthServerException(trans("passport::passport.messages.disabled_account"), 6, "inactive_account", 401);
        } elseif (method_exists($user, 'validateForPassportPasswordGrant')) {
            if (! $user->validateForPassportPasswordGrant($password)) {
                throw new OAuthServerException(trans("passport::passport.messages.invalid_credentials"), 6, "invalid_credentials", 401);
                return null;
            }
        } elseif (! $this->hasher->check($password, $user->getAuthPassword())) {
            return null;
        }

        if( $allowMultipleLoginForCustomer == config("core.no")) {
            $refreshTokenRepository = app(\Laravel\Passport\Bridge\RefreshTokenRepository::class);
            $tokens = $userModel->find($user->id)->tokens;

            $userInfo = $userModel->where("id", $user->id)->with("tokens", function($q) use($clientId) {
                $q->where("client_id", $clientId);
            })->first();

            if(!empty($userInfo) && !empty($userInfo->tokens)){
                foreach ($userInfo->tokens as $token) {
                    $token->revoke();
                    $refreshTokenRepository->revokeRefreshToken($token->id);
                }
            }
        }

        return $this->hasher->check($password, $user->getAuthPassword())
            ? new User($user->getAuthIdentifier())
            : null;
    }
}
