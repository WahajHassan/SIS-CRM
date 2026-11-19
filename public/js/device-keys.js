/*
 * Talking to the fingerprint reader.
 *
 * Everything biometric happens inside the device: it reads the finger or the
 * face, decides whether that is the owner, and only then signs. This file does
 * the plumbing around that — asking the server for a challenge, handing it to
 * the browser, and sending the signature back to be checked.
 *
 * The checking is on the server, where it has to be. Nothing here decides
 * whether somebody is who they say they are; it only carries the proof.
 */
(function (global) {
    'use strict';

    var BASE = '/admin/devices';

    function token() {
        var tag = document.querySelector('meta[name="csrf-token"]');
        return tag ? tag.getAttribute('content') : '';
    }

    function post(path, body) {
        return fetch(BASE + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(body || {}),
        }).then(readOrThrow);
    }

    function readOrThrow(response) {
        return response.json().catch(function () { return {}; }).then(function (body) {
            if (response.ok) return body;
            throw new Error(body.message || 'That did not go through.');
        });
    }

    /* base64url, which is how all of this travels. */
    function toBytes(value) {
        var padded = value.replace(/-/g, '+').replace(/_/g, '/');
        while (padded.length % 4) padded += '=';

        var binary = atob(padded);
        var out = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) out[i] = binary.charCodeAt(i);
        return out;
    }

    function toText(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    /** Does this device have anything to offer? */
    function supported() {
        return !!(global.PublicKeyCredential && navigator.credentials && navigator.credentials.create);
    }

    /** And is it the built-in kind — a fingerprint reader rather than a USB key? */
    function hasBuiltIn() {
        if (!supported() || !PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
            return Promise.resolve(false);
        }
        return PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(function () { return false; });
    }

    function list() {
        return fetch(BASE, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        }).then(readOrThrow);
    }

    /* ---- setting one up ---------------------------------------------------- */

    function setUp(label) {
        return post('/setup/begin', {}).then(function (start) {
            return navigator.credentials.create({
                publicKey: {
                    challenge: toBytes(start.challenge),
                    rp: { name: document.title.split('|').pop().trim() || 'CRM', id: location.hostname },
                    user: {
                        id: toBytes(start.user.id),
                        name: start.user.name,
                        displayName: start.user.displayName,
                    },
                    // ES256, which is what every built-in reader produces.
                    pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
                    authenticatorSelection: {
                        // The reader in this machine, not a key on a lanyard.
                        authenticatorAttachment: 'platform',
                        userVerification: 'required',
                        residentKey: 'preferred',
                    },
                    excludeCredentials: (start.existing || []).map(function (id) {
                        return { type: 'public-key', id: toBytes(id) };
                    }),
                    timeout: 90000,
                    attestation: 'none',
                },
            });
        }).then(function (credential) {
            if (!credential) throw new Error('Nothing came back from the device.');

            return post('/setup/finish', {
                credential_id: toText(credential.rawId),
                attestation: toText(credential.response.attestationObject),
                client_data: toText(credential.response.clientDataJSON),
                label: label || 'This device',
            });
        }).catch(function (e) {
            if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) {
                throw new Error('Cancelled, or your device did not recognise you.');
            }
            if (e && e.name === 'InvalidStateError') {
                throw new Error('This device is already set up on your account.');
            }
            throw e;
        });
    }

    function forget(id) {
        return fetch(BASE + '/' + id, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        }).then(readOrThrow);
    }

    /* ---- proving it -------------------------------------------------------- */

    /**
     * Ask the device to confirm who is here.
     *
     * Resolves when the server has checked the signature and is satisfied; the
     * caller can then do the thing it was guarding.
     */
    function prove() {
        return post('/check/begin', {}).then(function (start) {
            return navigator.credentials.get({
                publicKey: {
                    challenge: toBytes(start.challenge),
                    allowCredentials: (start.devices || []).map(function (id) {
                        return { type: 'public-key', id: toBytes(id) };
                    }),
                    userVerification: 'required',
                    timeout: 90000,
                    rpId: location.hostname,
                },
            });
        }).then(function (assertion) {
            if (!assertion) throw new Error('Nothing came back from the device.');

            return post('/check/finish', {
                credential_id: toText(assertion.rawId),
                authenticator_data: toText(assertion.response.authenticatorData),
                client_data: toText(assertion.response.clientDataJSON),
                signature: toText(assertion.response.signature),
            });
        }).catch(function (e) {
            if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) {
                throw new Error('Cancelled, or your device did not recognise you.');
            }
            throw e;
        });
    }

    global.DeviceKeys = {
        supported: supported,
        hasBuiltIn: hasBuiltIn,
        list: list,
        setUp: setUp,
        forget: forget,
        prove: prove,
    };
})(window);
