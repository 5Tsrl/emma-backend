<?php
declare(strict_types=1);

namespace App\Authenticator;

use Authentication\Authenticator\JwtAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;

class CookieJwtAuthenticator extends JwtAuthenticator
{
    use LocatorAwareTrait;

    /**
     * Il payload JWT decodificato corrisponde a un token revocato (denylist)?
     *
     * I token "legacy" privi di `jti` non sono revocabili puntualmente: restano
     * validi fino alla loro (breve) scadenza, finestra di transizione accettata.
     *
     * Fail-closed: se la denylist non è consultabile (tabella `revoked_tokens`
     * mancante, DB irraggiungibile, ecc.) il token viene trattato come revocato
     * invece di essere accettato silenziosamente — una denylist rotta non deve
     * diventare una porta aperta (anti VM1). L'errore viene comunque loggato a
     * livello error, così che il guasto sottostante non passi inosservato.
     *
     * @param object|array|null $payload Payload JWT decodificato.
     * @return bool
     */
    private function isRevoked($payload): bool
    {
        if (empty($payload)) {
            return false;
        }
        $arr = (array)$payload;
        $jti = $arr['jti'] ?? null;
        if (empty($jti)) {
            return false;
        }

        try {
            return $this->getTableLocator()->get('RevokedTokens')->isRevoked((string)$jti);
        } catch (\Throwable $e) {
            Log::error(
                'CookieJwtAuthenticator: impossibile verificare la denylist revoked_tokens, ' .
                'accesso negato per fail-safe (VM1) - ' . $e->getMessage()
            );

            return true;
        }
    }

    /**
     * Esegue l'autenticazione JWT anche da cookie.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        // prova il comportamento standard (header, queryParam, ecc.)
        $result = parent::authenticate($request);

        if ($result->isValid()) {
            // Anti VM1: anche un token con firma/exp validi viene rifiutato se il suo
            // jti è nella denylist (es. dopo il logout).
            if ($this->isRevoked($this->getPayload())) {
                return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                    'message' => 'Token revocato',
                ]);
            }

            return $result;
        }

        //  se non c'è token valido, prova a leggere dal cookie
        $cookieName = $this->getConfig('cookie') ?? 'jwt_token';
        $token = $request->getCookieParams()[$cookieName] ?? null;

        if (empty($token)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING, [
                'message' => 'JWT non trovato né negli header né nel cookie',
            ]);
        }

        // Decodifica il JWT usando firebase/php-jwt
        $secret = $this->getConfig('secretKey') ?? env('JWT_SECRET');
        $algorithm = $this->getConfig('algorithm') ?? 'HS256';

        try {
            // JWT::decode ritorna stdClass -> convertiamo in array
            $decoded = JWT::decode($token, new Key($secret, $algorithm));
            $payload = (array)$decoded;
        } catch (\Throwable $e) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'message' => 'Token JWT non valido o scaduto',
                'exception' => $e->getMessage(),
            ]);
        }

        // Anti VM1: rifiuta il token (dal cookie) se il suo jti è nella denylist.
        if ($this->isRevoked($decoded)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'message' => 'Token revocato',
            ]);
        }

        // 4) Identifica l'utente tramite l'identifier già caricato nel servizio
        //    getIdentifier() dovrebbe essere disponibile nell'Authenticator base
        $identifier = $this->getIdentifier();
        if (!$identifier) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'message' => 'Identifier non configurato nell\'authenticator',
            ]);
        }

        // Identifier->identify si aspetta tipicamente un array di dati; passiamo il payload
        try {
            $user = $identifier->identify($payload);
        } catch (\Throwable $e) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, [
                'message' => 'Errore durante l\'identify',
                'exception' => $e->getMessage(),
            ]);
        }

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, [
                'message' => 'Utente non trovato per il token JWT',
            ]);
        }

        // 5) Ritorna risultato di successo
        return new Result($user, Result::SUCCESS);
    }
}
