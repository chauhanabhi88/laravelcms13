<?php

namespace Modules\Passport\Grant;

use Laravel\Passport\Bridge\RefreshTokenRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Grant\PasswordGrant as BasePasswordGrant;
use League\OAuth2\Server\RequestEvent;
use Modules\Passport\Http\Exception\OAuthServerException;
use Modules\Passport\Repositories\UserRepository;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Password grant that reports CMS-authored credential errors.
 *
 * Only validateUser() differs from the league implementation; token issuing is
 * inherited unchanged.
 */
class PasswordGrant extends BasePasswordGrant
{
    public function __construct()
    {
        parent::__construct(
            app(UserRepository::class),
            app(RefreshTokenRepository::class)
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws OAuthServerException
     */
    protected function validateUser(ServerRequestInterface $request, ClientEntityInterface $client): UserEntityInterface
    {
        $username = $this->getRequestParameter('username', $request)
            ?? throw OAuthServerException::customException(trans('passport::passport.messages.invalid_credentials'), BaseResponse::HTTP_BAD_REQUEST);

        $password = $this->getRequestParameter('password', $request)
            ?? throw OAuthServerException::customException(trans('passport::passport.messages.invalid_credentials'), BaseResponse::HTTP_BAD_REQUEST);

        $user = $this->userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            $this->getIdentifier(),
            $client
        );

        if ($user instanceof UserEntityInterface === false) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

            throw OAuthServerException::customException(trans('passport::passport.messages.invalid_credentials'), BaseResponse::HTTP_BAD_REQUEST);
        }

        return $user;
    }
}
