#!/usr/bin/env bash
#
# Regenerates the GPG fixtures used by the signature verification tests.
#
# Two throwaway signing keys ("test" and "other") are created in a temporary
# GnuPG home. Only their public keys, fingerprints and signatures are kept; the
# secret keys are discarded along with the home directory.
#
# Set GNUPGHOME_FIXTURES to use a specific (empty) directory as the GnuPG home,
# e.g. if the default temporary path is too long for gpg-agent's socket.

set -euo pipefail

FIXTURES="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD="$FIXTURES/../build"

export GNUPGHOME="${GNUPGHOME_FIXTURES:-$(mktemp -d)}"
chmod 700 "$GNUPGHOME"
trap 'gpgconf --kill gpg-agent; rm -rf "$GNUPGHOME"' EXIT

gpg_batch=(gpg --quiet --batch --yes --pinentry-mode loopback --passphrase '')

for name in test other; do
    "${gpg_batch[@]}" --quick-gen-key "phar-updater $name <$name@example.com>" ed25519 sign never
    gpg --with-colons --fingerprint "$name@example.com" | awk -F: '$1 == "fpr" { print $10; exit }' > "$FIXTURES/$name.fingerprint"
done

gpg --armor --export test@example.com > "$FIXTURES/test.pubkey.asc"
gpg --export test@example.com > "$FIXTURES/test.pubkey.gpg"
gpg --armor --export test@example.com other@example.com > "$FIXTURES/bundle.pubkey.asc"

# A valid signature, published next to the phar as the strategies expect.
"${gpg_batch[@]}" --local-user test@example.com --armor --detach-sign --output "$BUILD/new.phar.asc" "$BUILD/new.phar"

# A signature by a key other than the pinned one.
"${gpg_batch[@]}" --local-user other@example.com --armor --detach-sign --output "$FIXTURES/new.phar.other.asc" "$BUILD/new.phar"

# A signature file containing signatures from both keys.
"${gpg_batch[@]}" --local-user test@example.com --local-user other@example.com --armor --detach-sign --output "$FIXTURES/new.phar.multiple.asc" "$BUILD/new.phar"

# A signed message (not a detached signature) wrapping unrelated content.
printf 'not the phar' | "${gpg_batch[@]}" --local-user test@example.com --armor --sign --output "$FIXTURES/attached.asc"

# Garbage that only looks like a signature.
printf -- '-----BEGIN PGP SIGNATURE-----\n\nbm90IGEgc2lnbmF0dXJl\n-----END PGP SIGNATURE-----\n' > "$FIXTURES/garbage.asc"
