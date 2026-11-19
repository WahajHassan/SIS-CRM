<?php

namespace App\Http\Controllers;

use App\Models\DeviceKey;
use App\Models\HrSetting;
use App\Services\DeviceKeyVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Setting up a fingerprint or a face, and proving it at the clock.
 *
 * The browser does the biometrics. The laptop reads the finger, satisfies
 * itself that it is the owner, and signs a challenge with a key it will not
 * give up. None of that arrives here — a signature does, and the job of this
 * controller is to check it properly rather than to take the page's word for
 * anything.
 *
 * The challenge is made here and kept in the session, so a signature made for
 * one clock-in cannot be saved up and used for the next.
 */
class DeviceKeyController extends Controller
{
    /** A challenge is worth a couple of minutes, not a day. */
    private const CHALLENGE_LASTS_SECONDS = 180;

    public function index()
    {
        $me = auth()->user();

        return response()->json([
            'devices' => DeviceKey::where('user_id', $me->id)
                ->orderBy('created_at')
                ->get(['id', 'label', 'last_used_at', 'created_at'])
                ->map(fn (DeviceKey $d) => [
                    'id' => $d->id,
                    'label' => $d->label,
                    'lastUsed' => $d->last_used_at?->diffForHumans(),
                    'added' => $d->created_at->format('j M Y'),
                ]),
            'required' => (bool) HrSetting::current()->require_biometric_clock,
        ]);
    }

    /* ---- setting one up ---------------------------------------------------- */

    public function beginSetup(Request $request, DeviceKeyVerifier $verifier)
    {
        $me = auth()->user();

        $challenge = random_bytes(32);
        $this->keepChallenge($request, 'setup', $challenge);

        return response()->json([
            'challenge' => $verifier->toUrlSafe($challenge),
            'user' => [
                // Their row id, not their name: the id is what this is about and
                // it does not change when somebody is renamed.
                'id' => $verifier->toUrlSafe((string) $me->id),
                'name' => $me->email ?: $me->username,
                'displayName' => $me->display_name,
            ],
            // Devices already set up, so the browser does not offer to add the
            // same one twice.
            'existing' => DeviceKey::where('user_id', $me->id)->pluck('credential_id'),
        ]);
    }

    public function finishSetup(Request $request, DeviceKeyVerifier $verifier)
    {
        $me = auth()->user();

        $data = $request->validate([
            'credential_id' => ['required', 'string', 'max:512'],
            'attestation' => ['required', 'string'],
            'client_data' => ['required', 'string'],
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        $challenge = $this->takeChallenge($request, 'setup');

        if (!$challenge) {
            return response()->json(['message' => 'That took too long. Try again.'], 422);
        }

        $clientData = $verifier->fromUrlSafe($data['client_data']);

        if (!$verifier->clientDataIsGood($clientData, $challenge, 'webauthn.create')) {
            return response()->json(['message' => 'That did not come from this page.'], 422);
        }

        $publicKey = $verifier->publicKeyFrom($data['attestation']);

        if (!$publicKey) {
            return response()->json([
                'message' => 'This device did not offer a key we can check. Try a laptop or phone with a fingerprint reader.',
            ], 422);
        }

        if (DeviceKey::where('credential_id', $data['credential_id'])->exists()) {
            return response()->json(['message' => 'This device is already set up.'], 422);
        }

        DeviceKey::create([
            'user_id' => $me->id,
            'credential_id' => $data['credential_id'],
            'public_key' => $publicKey,
            'label' => $data['label'] ?: 'This device',
        ]);

        return response()->json(['ok' => true]);
    }

    public function forget(Request $request, DeviceKey $device)
    {
        abort_unless($device->user_id === auth()->id(), 403);

        $device->delete();

        return response()->json(['ok' => true]);
    }

    /* ---- proving it at the clock ------------------------------------------- */

    public function beginCheck(Request $request, DeviceKeyVerifier $verifier)
    {
        $me = auth()->user();

        $devices = DeviceKey::where('user_id', $me->id)->pluck('credential_id');

        if ($devices->isEmpty()) {
            return response()->json(['message' => 'Nothing set up on this account yet.'], 422);
        }

        $challenge = random_bytes(32);
        $this->keepChallenge($request, 'check', $challenge);

        return response()->json([
            'challenge' => $verifier->toUrlSafe($challenge),
            'devices' => $devices,
        ]);
    }

    /**
     * The check itself.
     *
     * Everything that could be a lie is checked: that the challenge is the one
     * this server just made, that the signature is over it, that the key is one
     * this person registered, that the device actually verified who was holding
     * it, and that the counter moved. Any of those failing is a no.
     */
    public function finishCheck(Request $request, DeviceKeyVerifier $verifier)
    {
        $me = auth()->user();

        $data = $request->validate([
            'credential_id' => ['required', 'string', 'max:512'],
            'authenticator_data' => ['required', 'string'],
            'client_data' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $challenge = $this->takeChallenge($request, 'check');

        if (!$challenge) {
            return response()->json(['message' => 'That took too long. Try again.'], 422);
        }

        $device = DeviceKey::where('user_id', $me->id)
            ->where('credential_id', $data['credential_id'])
            ->first();

        if (!$device) {
            return response()->json(['message' => 'That device is not set up on this account.'], 422);
        }

        $clientData = $verifier->fromUrlSafe($data['client_data']);
        $authData = $verifier->fromUrlSafe($data['authenticator_data']);
        $signature = $verifier->fromUrlSafe($data['signature']);

        if (!$verifier->clientDataIsGood($clientData, $challenge, 'webauthn.get')) {
            return response()->json(['message' => 'That did not come from this page.'], 422);
        }

        if (!$verifier->wasVerified($authData)) {
            return response()->json([
                'message' => 'Your device did not check who you are. Use your fingerprint, face or PIN.',
            ], 422);
        }

        if (!$verifier->signatureIsGood($device->public_key, $authData, $clientData, $signature)) {
            return response()->json(['message' => 'That did not check out.'], 422);
        }

        // A counter that did not move is an old signature being played back.
        $counter = $verifier->counterFrom($authData);

        if ($device->counter > 0 && $counter > 0 && $counter <= $device->counter) {
            return response()->json(['message' => 'That has been used before.'], 422);
        }

        $device->update(['counter' => $counter, 'last_used_at' => now()]);

        // The clock reads this, and only within the next couple of minutes.
        $request->session()->put('device_check_passed_at', now()->timestamp);

        return response()->json(['ok' => true, 'device' => $device->label]);
    }

    // ---- the plumbing -------------------------------------------------------

    private function keepChallenge(Request $request, string $what, string $challenge): void
    {
        $request->session()->put("webauthn.$what", [
            'challenge' => $challenge,
            'at' => now()->timestamp,
        ]);
    }

    /** Takes it and puts it out of use, so one challenge answers once. */
    private function takeChallenge(Request $request, string $what): ?string
    {
        $kept = $request->session()->pull("webauthn.$what");

        if (!is_array($kept) || !isset($kept['challenge'], $kept['at'])) {
            return null;
        }

        if (now()->timestamp - (int) $kept['at'] > self::CHALLENGE_LASTS_SECONDS) {
            return null;
        }

        return $kept['challenge'];
    }
}
