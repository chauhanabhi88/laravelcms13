<?php

namespace Modules\Passport\Repositories;

use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Passport\ClientRepository as ClientModelRepository;
use Laravel\Passport\Bridge\ClientRepository as PassportClientRepository;

class ClientRepository extends PassportClientRepository
{
    /**
     * Create a new repository instance.
     */
    public function __construct(
        protected ClientModelRepository $clients,
        protected Hasher $hasher
    ) {
    }

     /**
     * {@inheritdoc}
     */
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $record = $this->clients->findActive($clientIdentifier);
        
        return $record && ! empty($clientSecret) && $this->hasher->check($clientSecret, $record->secret);
    }
}
