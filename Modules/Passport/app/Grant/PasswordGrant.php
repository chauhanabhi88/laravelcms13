<?php

namespace Modules\Passport\Grant;

use DateInterval;
use League\OAuth2\Server\RequestEvent;
use Psr\Http\Message\ServerRequestInterface;
use Modules\Passport\Repositories\UserRepository;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Modules\Passport\Http\Exception\OAuthServerException;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Symfony\Component\HttpFoundation\Response AS BaseResponse;
use League\OAuth2\Server\Grant\PasswordGrant AS BasePasswordGrand;

/**
 * Password grant class.
 */
class PasswordGrant extends BasePasswordGrand
{
    public function __construct()
    {
        $userRepository = app(UserRepository::class);
        $refreshTokenRepository = app(RefreshTokenRepository::class);

        parent::__construct($userRepository, $refreshTokenRepository);

        $this->setRefreshTokenTTL(new DateInterval('P1M')); // Optional: Customize TTL
    }

    /**
     * {@inheritdoc}
     */
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        // Validate request
        $client = $this->validateClient($request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));
        $user = $this->validateUser($request, $client);

        $finalizedScopes = $this->scopeRepository->finalizeScopes(
            $scopes,
            $this->getIdentifier(),
            $client,
            $user->getIdentifier()
        );

        // Issue and persist new access token
        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $user->getIdentifier(), $finalizedScopes);
        $this->getEmitter()->emit(new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken));
        $responseType->setAccessToken($accessToken);

        // Issue and persist new refresh token if given
        $refreshToken = $this->issueRefreshToken($accessToken);

        if ($refreshToken !== null) {
            $this->getEmitter()->emit(new RequestRefreshTokenEvent(RequestEvent::REFRESH_TOKEN_ISSUED, $request, $refreshToken));
            $responseType->setRefreshToken($refreshToken);
        }

        return $responseType;
    }

    /**
     * @throws OAuthServerException
     */
    protected function validateUser(ServerRequestInterface $request, ClientEntityInterface $client): UserEntityInterface
    {
        $username = $this->getRequestParameter('username', $request)
            ?? throw OAuthServerException::customException(trans("passport::passport.messages.invalid_credentials"), BaseResponse::HTTP_BAD_REQUEST);

        $password = $this->getRequestParameter('password', $request)
            ?? throw OAuthServerException::customException(trans("passport::passport.messages.invalid_credentials"), BaseResponse::HTTP_BAD_REQUEST);

        $user = $this->userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            $this->getIdentifier(),
            $client
        );

        if ($user instanceof UserEntityInterface === false) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

            throw OAuthServerException::customException(trans("passport::passport.messages.invalid_credentials"), BaseResponse::HTTP_BAD_REQUEST);
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return 'password';
    }
}
