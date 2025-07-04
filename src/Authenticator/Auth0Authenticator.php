<?php
declare(strict_types=1);

//<editor-fold desc="Preamble">
/**
 * EMMA(tm) : Electronic Mobility Management Applications
 * Copyright (c) 5T Torino, Regione Piemonte, Città Metropolitana di Torino
 *
 * SPDX-License-Identifier: EUPL-1.2
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) 5T - https://5t.torino.it
 * @link      https://emma.5t.torino.it
 * @author    Massimo INFUNTI - https://github.com/impronta48
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>
namespace App\Authenticator;

use ArrayObject;
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Helpers\JWKFetcher;
use Auth0\SDK\Helpers\Tokens\AsymmetricVerifier;
use Auth0\SDK\Helpers\Tokens\TokenVerifier;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Authentication\Authenticator\TokenAuthenticator;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\Identifier\IdentifierInterface;
use Cake\Utility\Security;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class Auth0Authenticator extends TokenAuthenticator
{
    /**
     * @inheritDoc
     */
    protected $_defaultConfig = [
    'header' => 'Authorization',
    'queryParam' => 'token',
    'tokenPrefix' => 'bearer',
    'algorithms' => ['HS256'],
    'returnPayload' => true,
    'secretKey' => null,
    'jwksEndpoint' => null,
    'issuer' => '',
    'audience' => '',
    'subjectKey' => IdentifierInterface::CREDENTIAL_JWT_SUBJECT,
    ];

    /**
     * Payload data.
     *
     * @var object|null
     */
    protected $payload;

    /**
     * @inheritDoc
     */
    public function __construct(IdentifierInterface $identifier, array $config = [])
    {
        parent::__construct($identifier, $config);

        if (empty($this->_config['secretKey'])) {
            if (!class_exists(Security::class)) {
                throw new RuntimeException('You must set the `secretKey` config key for JWT authentication.');
            }
            $this->setConfig('secretKey', \Cake\Utility\Security::getSalt());
        }
    }

    /**
     * Authenticates the identity based on a JWT token contained in a request.
     *
     * @link https://jwt.io/
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        try {
            $result = $this->getPayload($request);
        } catch (Exception $e) {
            return new Result(
                null,
                Result::FAILURE_CREDENTIALS_INVALID,
                [
                'message' => $e->getMessage(),
                'exception' => $e,
                ]
            );
        }

        if (!is_array($result)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID);
        }

        $subjectKey = $this->getConfig('subjectKey');
        if (empty($result[$subjectKey])) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
        }

        if ($this->getConfig('returnPayload')) {
            $user = new ArrayObject($result);

            return new Result($user, Result::SUCCESS);
        }

        $user = $this->_identifier->identify([
        $subjectKey => $result[$subjectKey],
        ]);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->_identifier->getErrors());
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * Get payload data.
     *
     * @param \Psr\Http\Message\ServerRequestInterface|null $request Request to get authentication information from.
     * @return object|null Payload object on success, null on failure
     */
    public function getPayload(?ServerRequestInterface $request = null): ?array
    {
        if (!$request) {
            return $this->payload;
        }

        $payload = null;
        $token = $this->getToken($request);

        if ($token !== null) {
            $payload = $this->decodeToken($token);
        }

        $this->payload = $payload;

        return $this->payload;
    }

    /**
     * Decode JWT token.
     *
     * @param string $token JWT token to decode.
     * @return object|null The JWT's payload as a PHP object, null on failure.
     */
    protected function decodeToken($token): ?array
    {
        $cacheHandler = null;

        if (empty($token)) {
            return [];
        }

        $jwksFetcher   = new JWKFetcher(
            $cacheHandler,
            [
                'base_uri' => $this->getConfig('jwksEndpoint'),
            ]
        );

        if (!empty($this->getConfig('issuer'))) {
            $sigVerifier   = new AsymmetricVerifier($jwksFetcher);
            $tokenVerifier = new TokenVerifier($this->getConfig('issuer'), $this->getConfig('audience'), $sigVerifier);
        }

        try {
            $this->tokenInfo = $tokenVerifier->verify($token);

            return $this->tokenInfo;
        } catch (InvalidTokenException $e) {
            // Handle invalid JWT exception ...
            throw new UnauthenticatedException('Token JWT non valido');
        }
    }
}
