<?php

namespace App\Services;

/**
 * Checking that a fingerprint really was the right person's.
 *
 * The browser does the biometrics: the laptop reads the finger or the face,
 * decides it is the owner, and only then signs a challenge with a key it keeps
 * to itself. None of that reaches us and none of it could — what arrives is a
 * signature, and this class is where it is checked.
 *
 * It matters that it is checked. A page can be made to say anything; a
 * signature over a challenge this server made, verified against a key this
 * server stored, cannot. Skip this step and the whole feature is a button that
 * means nothing.
 *
 * Only ES256 is accepted, which is what every platform authenticator — Windows
 * Hello, Touch ID, Android — produces by default.
 */
class DeviceKeyVerifier
{
    /** The COSE identifier for ECDSA over P-256 with SHA-256. */
    private const ES256 = -7;

    /**
     * Pull the public key out of what the browser sent when the device was
     * registered.
     *
     * Returns the key as PEM, ready to be stored and used later.
     */
    public function publicKeyFrom(string $attestationObject): ?string
    {
        // fromUrlSafe already hands back raw bytes; decoding them again would
        // turn the registration into rubbish.
        $decoded = $this->cbor($this->fromUrlSafe($attestationObject));

        if (!is_array($decoded) || !isset($decoded['authData'])) {
            return null;
        }

        return $this->keyFromAuthenticatorData($decoded['authData']);
    }

    /**
     * Is this signature the real thing?
     *
     * What the browser signs is the authenticator's own data followed by the
     * hash of everything the page was told — which includes the challenge. So a
     * signature cannot be reused for a different challenge, and cannot be made
     * at all without the key inside the device.
     */
    public function signatureIsGood(
        string $publicKeyPem,
        string $authenticatorData,
        string $clientDataJson,
        string $signature
    ): bool {
        $key = openssl_pkey_get_public($publicKeyPem);

        if (!$key) {
            return false;
        }

        $signed = $authenticatorData . hash('sha256', $clientDataJson, true);

        return openssl_verify($signed, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * What the page said it was doing when it signed.
     *
     * The challenge has to be the one this server just made, and the type has
     * to be the right one — a signature made for logging in must not be usable
     * for clocking in.
     */
    public function clientDataIsGood(string $clientDataJson, string $expectedChallenge, string $expectedType): bool
    {
        $data = json_decode($clientDataJson, true);

        if (!is_array($data)) {
            return false;
        }

        if (($data['type'] ?? '') !== $expectedType) {
            return false;
        }

        $challenge = $this->fromUrlSafe((string) ($data['challenge'] ?? ''));

        return hash_equals($expectedChallenge, $challenge);
    }

    /** How many times this key says it has been used. */
    public function counterFrom(string $authenticatorData): int
    {
        if (strlen($authenticatorData) < 37) {
            return 0;
        }

        return (int) unpack('N', substr($authenticatorData, 33, 4))[1];
    }

    /** Did the device actually check who was holding it? */
    public function wasVerified(string $authenticatorData): bool
    {
        if (strlen($authenticatorData) < 33) {
            return false;
        }

        $flags = ord($authenticatorData[32]);

        // Bit 2 is "user verified" — a finger, a face or a PIN, rather than
        // merely a key that was present.
        return (bool) ($flags & 0x04);
    }

    // ---- taking the key out of the registration -----------------------------

    /**
     * The authenticator's data ends with the new key, in COSE form.
     *
     * Its layout is fixed: 32 bytes of hashed origin, one of flags, four of
     * counter, then — when a key is being handed over — 16 bytes naming the
     * device, two giving the length of its id, the id, and the key.
     */
    private function keyFromAuthenticatorData(string $authData): ?string
    {
        if (strlen($authData) < 55) {
            return null;
        }

        $idLength = unpack('n', substr($authData, 53, 2))[1];
        $cose = substr($authData, 55 + $idLength);

        $key = $this->cbor($cose);

        if (!is_array($key)) {
            return null;
        }

        // 3 is the algorithm, -1 the curve, -2 and -3 the two halves of the point.
        if ((int) ($key[3] ?? 0) !== self::ES256) {
            return null;
        }

        $x = $key[-2] ?? null;
        $y = $key[-3] ?? null;

        if (!is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
            return null;
        }

        return $this->pemFor($x, $y);
    }

    /**
     * A P-256 point, written as PEM so openssl will take it.
     *
     * The prefix is the fixed DER preamble for an uncompressed public key on
     * prime256v1; only the 64 bytes of the point itself change.
     */
    private function pemFor(string $x, string $y): string
    {
        $der = base64_decode('MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE') . $x . $y;

        return "-----BEGIN PUBLIC KEY-----\n" .
               chunk_split(base64_encode($der), 64, "\n") .
               "-----END PUBLIC KEY-----\n";
    }

    // ---- just enough CBOR ---------------------------------------------------

    /**
     * Read a CBOR value.
     *
     * WebAuthn sends two things in CBOR and nothing else: the registration
     * wrapper and the key inside it. Both use a handful of the format's types,
     * so this reads those and refuses the rest rather than pulling in a library
     * for the sake of thirty lines.
     */
    private function cbor(string $bytes)
    {
        $at = 0;
        $value = $this->readValue($bytes, $at);

        return $value;
    }

    private function readValue(string $bytes, int &$at)
    {
        if ($at >= strlen($bytes)) {
            return null;
        }

        $first = ord($bytes[$at++]);
        $major = $first >> 5;
        $small = $first & 0x1F;

        $length = $this->readLength($bytes, $at, $small);

        switch ($major) {
            case 0: return $length;                       // a number
            case 1: return -1 - $length;                  // a negative number
            case 2:                                       // bytes
                $out = substr($bytes, $at, $length);
                $at += $length;
                return $out;
            case 3:                                       // text
                $out = substr($bytes, $at, $length);
                $at += $length;
                return $out;
            case 4:                                       // a list
                $out = [];
                for ($i = 0; $i < $length; $i++) {
                    $out[] = $this->readValue($bytes, $at);
                }
                return $out;
            case 5:                                       // a map
                $out = [];
                for ($i = 0; $i < $length; $i++) {
                    $key = $this->readValue($bytes, $at);
                    $out[is_string($key) || is_int($key) ? $key : (string) $key] = $this->readValue($bytes, $at);
                }
                return $out;
            case 7:
                // true, false, null — the only simple values that turn up here.
                if ($small === 20) return false;
                if ($small === 21) return true;
                return null;
            default:
                return null;
        }
    }

    private function readLength(string $bytes, int &$at, int $small): int
    {
        if ($small < 24) {
            return $small;
        }

        $sizes = [24 => 1, 25 => 2, 26 => 4, 27 => 8];

        if (!isset($sizes[$small])) {
            return 0;
        }

        $size = $sizes[$small];
        $slice = substr($bytes, $at, $size);
        $at += $size;

        $length = 0;
        foreach (str_split($slice) as $byte) {
            $length = ($length << 8) | ord($byte);
        }

        return $length;
    }

    public function fromUrlSafe(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;

        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($value) ?: '';
    }

    public function toUrlSafe(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
