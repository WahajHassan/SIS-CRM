<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\DeviceKey;
use App\Models\HrSetting;
use App\Models\User;
use App\Services\DeviceKeyVerifier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fingerprint at the clock.
 *
 * What makes this worth anything is the checking. A page can be made to say
 * "verified" by anybody with a browser console; a signature over a challenge
 * this server made, against a key this server stored, cannot be. So that is
 * what is tested — including the ways somebody would try to get around it:
 *
 *   · signing with the wrong key;
 *   · answering a challenge the server did not make;
 *   · replaying a signature that worked before;
 *   · using somebody else's registered device;
 *   · and a device that did not actually check who was holding it.
 */
class DeviceKeyTest extends TestCase
{
    use RefreshDatabase;

    private User $ali;
    private User $bilal;
    private DeviceKeyVerifier $verifier;

    /** A key pair standing in for the one inside a laptop. */
    private $privateKey;
    private string $publicKeyPem;

    private const A_DEVICES_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQg7Eajzkme91UxQqPX
2ERg3tO+g3V4g+hAaorSAv3Ppq+hRANCAATQRxr4dlDRQCjjfcAwyXJ+GJO5T92M
KPd6T7lw4liXeTmzo/wvhho7+xSWBzxDaMWF0JVhh8iGQMaiYZVIfuYm
-----END PRIVATE KEY-----
PEM;

    private const ITS_PUBLIC_HALF = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE0Eca+HZQ0UAo433AMMlyfhiTuU/d
jCj3ek+5cOJYl3k5s6P8L4YaO/sUlgc8Q2jFhdCVYYfIhkDGomGVSH7mJg==
-----END PUBLIC KEY-----
PEM;

    /** A different device entirely, for the impostor. */
    private const SOMEBODY_ELSES_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgv8JgEc/aOP93RXwQ
/e0Ra1wt1zWN5+6rK3p/5OXqBLOhRANCAARC1MeYYigo1clvc6Aj7IFVQ888CiBO
6w/LDpB2fi6PHcVUp4DbSzmlZID8uYcaWR1WLYaHqkAuAt7XKwMaPZ2z
-----END PRIVATE KEY-----
PEM;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-09 09:00'));

        $this->verifier = app(DeviceKeyVerifier::class);

        $this->ali = $this->person('Ali Agent');
        $this->bilal = $this->person('Bilal Agent');

        // A fixed pair rather than a generated one: making a key needs an
        // openssl.cnf, which a developer machine may not have, and a test that
        // only runs on some machines is not a test.
        $this->privateKey = openssl_pkey_get_private(self::A_DEVICES_KEY);
        $this->publicKeyPem = self::ITS_PUBLIC_HALF;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $name): User
    {
        $handle = strtolower(str_replace(' ', '', $name));

        return User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => $handle,
            'email' => $handle . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
            'monthly_salary' => 60000,
        ]);
    }

    private function register(User $who, string $credentialId = 'device-one'): DeviceKey
    {
        return DeviceKey::create([
            'user_id' => $who->id,
            'credential_id' => $credentialId,
            'public_key' => $this->publicKeyPem,
            'label' => 'Their laptop',
        ]);
    }

    /** What an authenticator sends: a hashed origin, flags, and a counter. */
    private function authenticatorData(int $counter = 1, bool $verified = true): string
    {
        return hash('sha256', 'localhost', true)
            . chr($verified ? 0x05 : 0x01)          // present, and verified when asked
            . pack('N', $counter);
    }

    private function clientData(string $challenge, string $type = 'webauthn.get'): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => $this->verifier->toUrlSafe($challenge),
            'origin' => 'http://localhost',
        ]);
    }

    private function sign(string $authData, string $clientData, $key = null): string
    {
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $key ?: $this->privateKey, OPENSSL_ALGO_SHA256);

        return $signature;
    }

    /** Ask the server for a challenge, the way the browser does. */
    private function challengeFor(User $who): string
    {
        $reply = $this->actingAs($who)->postJson(route('admin.devices.check.begin'))->assertOk();

        return $this->verifier->fromUrlSafe($reply->json('challenge'));
    }

    // ---- the checking -------------------------------------------------------

    public function test_a_proper_signature_passes(): void
    {
        $this->register($this->ali);
        $challenge = $this->challengeFor($this->ali);

        $authData = $this->authenticatorData(5);
        $clientData = $this->clientData($challenge);

        $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.finish'), [
                'credential_id' => 'device-one',
                'authenticator_data' => $this->verifier->toUrlSafe($authData),
                'client_data' => $this->verifier->toUrlSafe($clientData),
                'signature' => $this->verifier->toUrlSafe($this->sign($authData, $clientData)),
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        // The counter moved on, which is how a replay is spotted next time.
        $this->assertSame(5, DeviceKey::where('credential_id', 'device-one')->value('counter'));
    }

    public function test_a_signature_from_the_wrong_key_is_refused(): void
    {
        $this->register($this->ali);
        $challenge = $this->challengeFor($this->ali);

        // Somebody else's device, signing the right challenge.
        $impostor = openssl_pkey_get_private(self::SOMEBODY_ELSES_KEY);

        $authData = $this->authenticatorData(2);
        $clientData = $this->clientData($challenge);

        $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.finish'), [
                'credential_id' => 'device-one',
                'authenticator_data' => $this->verifier->toUrlSafe($authData),
                'client_data' => $this->verifier->toUrlSafe($clientData),
                'signature' => $this->verifier->toUrlSafe($this->sign($authData, $clientData, $impostor)),
            ])
            ->assertStatus(422);
    }

    public function test_answering_a_challenge_the_server_never_made_is_refused(): void
    {
        $this->register($this->ali);
        $this->challengeFor($this->ali);        // made, then ignored

        $invented = random_bytes(32);
        $authData = $this->authenticatorData(2);
        $clientData = $this->clientData($invented);

        $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.finish'), [
                'credential_id' => 'device-one',
                'authenticator_data' => $this->verifier->toUrlSafe($authData),
                'client_data' => $this->verifier->toUrlSafe($clientData),
                'signature' => $this->verifier->toUrlSafe($this->sign($authData, $clientData)),
            ])
            ->assertStatus(422);
    }

    public function test_the_same_signature_cannot_be_used_twice(): void
    {
        $this->register($this->ali);

        $challenge = $this->challengeFor($this->ali);
        $authData = $this->authenticatorData(7);
        $clientData = $this->clientData($challenge);
        $signature = $this->verifier->toUrlSafe($this->sign($authData, $clientData));

        $body = [
            'credential_id' => 'device-one',
            'authenticator_data' => $this->verifier->toUrlSafe($authData),
            'client_data' => $this->verifier->toUrlSafe($clientData),
            'signature' => $signature,
        ];

        $this->actingAs($this->ali)->postJson(route('admin.devices.check.finish'), $body)->assertOk();

        // The challenge is spent, so sending the whole thing again fails.
        $this->actingAs($this->ali)->postJson(route('admin.devices.check.finish'), $body)->assertStatus(422);
    }

    public function test_a_counter_that_did_not_move_is_refused(): void
    {
        $device = $this->register($this->ali);
        $device->update(['counter' => 9]);

        $challenge = $this->challengeFor($this->ali);
        $authData = $this->authenticatorData(9);       // same as last time
        $clientData = $this->clientData($challenge);

        $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.finish'), [
                'credential_id' => 'device-one',
                'authenticator_data' => $this->verifier->toUrlSafe($authData),
                'client_data' => $this->verifier->toUrlSafe($clientData),
                'signature' => $this->verifier->toUrlSafe($this->sign($authData, $clientData)),
            ])
            ->assertStatus(422);
    }

    public function test_a_device_that_did_not_check_who_was_holding_it_is_refused(): void
    {
        $this->register($this->ali);
        $challenge = $this->challengeFor($this->ali);

        // Present, but nobody's finger on it.
        $authData = $this->authenticatorData(3, verified: false);
        $clientData = $this->clientData($challenge);

        $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.finish'), [
                'credential_id' => 'device-one',
                'authenticator_data' => $this->verifier->toUrlSafe($authData),
                'client_data' => $this->verifier->toUrlSafe($clientData),
                'signature' => $this->verifier->toUrlSafe($this->sign($authData, $clientData)),
            ])
            ->assertStatus(422);
    }

    public function test_somebody_cannot_use_a_device_registered_to_another_person(): void
    {
        $this->register($this->bilal, 'bilals-laptop');

        $challenge = $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.begin'))
            ->assertStatus(422);   // Ali has nothing set up at all

        $this->register($this->ali, 'alis-laptop');
        $challenge = $this->challengeFor($this->ali);

        $authData = $this->authenticatorData(4);
        $clientData = $this->clientData($challenge);

        // Ali, signing correctly, but naming Bilal's device.
        $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.finish'), [
                'credential_id' => 'bilals-laptop',
                'authenticator_data' => $this->verifier->toUrlSafe($authData),
                'client_data' => $this->verifier->toUrlSafe($clientData),
                'signature' => $this->verifier->toUrlSafe($this->sign($authData, $clientData)),
            ])
            ->assertStatus(422);
    }

    public function test_a_signature_made_for_setting_up_cannot_be_used_at_the_clock(): void
    {
        $this->register($this->ali);
        $challenge = $this->challengeFor($this->ali);

        $authData = $this->authenticatorData(4);
        // The right challenge, the wrong purpose.
        $clientData = $this->clientData($challenge, 'webauthn.create');

        $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.finish'), [
                'credential_id' => 'device-one',
                'authenticator_data' => $this->verifier->toUrlSafe($authData),
                'client_data' => $this->verifier->toUrlSafe($clientData),
                'signature' => $this->verifier->toUrlSafe($this->sign($authData, $clientData)),
            ])
            ->assertStatus(422);
    }

    // ---- what it guards -----------------------------------------------------

    public function test_the_clock_is_open_when_hr_does_not_ask_for_it(): void
    {
        HrSetting::query()->first()?->update(['require_biometric_clock' => false]);
        $this->register($this->ali);

        $this->actingAs($this->ali)
            ->post(route('admin.attendance.clock-in'))
            ->assertRedirect();

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_when_hr_asks_for_it_the_clock_wants_the_check_first(): void
    {
        $this->requireBiometrics();
        $this->register($this->ali);

        $this->actingAs($this->ali)
            ->postJson(route('admin.attendance.clock-in'))
            ->assertStatus(422)
            ->assertJsonPath('needs_biometric', true);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_and_lets_them_through_right_after_one(): void
    {
        $this->requireBiometrics();
        $this->register($this->ali);

        $challenge = $this->challengeFor($this->ali);
        $authData = $this->authenticatorData(11);
        $clientData = $this->clientData($challenge);

        $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.finish'), [
                'credential_id' => 'device-one',
                'authenticator_data' => $this->verifier->toUrlSafe($authData),
                'client_data' => $this->verifier->toUrlSafe($clientData),
                'signature' => $this->verifier->toUrlSafe($this->sign($authData, $clientData)),
            ])
            ->assertOk();

        $this->actingAs($this->ali)
            ->post(route('admin.attendance.clock-in'))
            ->assertRedirect();

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_one_check_does_not_open_the_clock_all_day(): void
    {
        $this->requireBiometrics();
        $this->register($this->ali);

        $challenge = $this->challengeFor($this->ali);
        $authData = $this->authenticatorData(12);
        $clientData = $this->clientData($challenge);

        $this->actingAs($this->ali)
            ->postJson(route('admin.devices.check.finish'), [
                'credential_id' => 'device-one',
                'authenticator_data' => $this->verifier->toUrlSafe($authData),
                'client_data' => $this->verifier->toUrlSafe($clientData),
                'signature' => $this->verifier->toUrlSafe($this->sign($authData, $clientData)),
            ])
            ->assertOk();

        // Five minutes later is too late for that check to still count.
        Carbon::setTestNow(now()->addMinutes(5));

        $this->actingAs($this->ali)
            ->postJson(route('admin.attendance.clock-in'))
            ->assertStatus(422);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_somebody_with_nothing_set_up_is_not_locked_out(): void
    {
        $this->requireBiometrics();
        // Ali has registered nothing.

        $this->actingAs($this->ali)
            ->post(route('admin.attendance.clock-in'))
            ->assertRedirect();

        $this->assertDatabaseCount('attendances', 1);
    }

    // ---- looking after your own devices -------------------------------------

    public function test_a_device_belongs_to_one_person_and_only_they_can_forget_it(): void
    {
        $device = $this->register($this->ali);

        $this->actingAs($this->bilal)
            ->deleteJson(route('admin.devices.forget', $device))
            ->assertForbidden();

        $this->actingAs($this->ali)
            ->deleteJson(route('admin.devices.forget', $device))
            ->assertOk();

        $this->assertDatabaseCount('device_keys', 0);
    }

    public function test_the_list_shows_only_their_own(): void
    {
        $this->register($this->ali, 'alis');
        $this->register($this->bilal, 'bilals');

        $this->actingAs($this->ali)
            ->getJson(route('admin.devices'))
            ->assertOk()
            ->assertJsonCount(1, 'devices');
    }

    public function test_no_fingerprint_is_stored_anywhere(): void
    {
        $device = $this->register($this->ali);

        // What is kept is a public key and nothing else resembling a person.
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $device->public_key);

        // And it is not handed back out over the wire.
        $this->actingAs($this->ali)
            ->getJson(route('admin.devices'))
            ->assertOk()
            ->assertJsonMissing(['public_key' => $device->public_key]);
    }

    private function requireBiometrics(): void
    {
        $rules = HrSetting::query()->first();

        if ($rules) {
            $rules->update(['require_biometric_clock' => true]);
            return;
        }

        HrSetting::create(['company_id' => $this->company->id, 'require_biometric_clock' => true]);
    }
}
