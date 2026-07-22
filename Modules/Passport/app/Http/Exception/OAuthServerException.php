<?php

namespace Modules\Passport\Http\Exception;

use League\OAuth2\Server\Exception\OAuthServerException as LeagueOAuthServerException;

/**
 * OAuth error carrying a CMS-authored message.
 *
 * Must stay a subclass of the league exception: Passport's HandlesOAuthErrors
 * only converts LeagueOAuthServerException into an HTTP response, so anything
 * thrown from a grant that does not extend it escapes as an unhandled error.
 */
class OAuthServerException extends LeagueOAuthServerException
{
    /**
     * Build an error from a translated, user-facing message.
     */
    public static function customException(string $message, int $httpStatusCode = 400, string $errorType = 'invalid_request'): static
    {
        return new static($message, 3, $errorType, $httpStatusCode);
    }

    /**
     * Alias "error_description" to "message" for API clients that expect it.
     *
     * @return array<string, string>
     */
    public function getPayload(): array
    {
        $payload = parent::getPayload();

        if (isset($payload['error_description']) && ! isset($payload['message'])) {
            $payload['message'] = $payload['error_description'];
        }

        return $payload;
    }
}
