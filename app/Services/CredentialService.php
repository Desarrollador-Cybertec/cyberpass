<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;

class CredentialService
{
    public function __construct(private EncryptionService $encryption) {}

    public function create(Asset $asset, Organization $organization, User $creator, array $data): Credential
    {
        ['ciphertext' => $encPwd, 'iv' => $iv] = $this->encryption->encrypt($data['password']);

        $encNotes = null;
        $ivNotes  = null;

        if (! empty($data['notes'])) {
            ['ciphertext' => $encNotes, 'iv' => $ivNotes] = $this->encryption->encrypt($data['notes']);
        }

        return Credential::create([
            'asset_id'           => $asset->id,
            'organization_id'    => $organization->id,
            'created_by'         => $creator->id,
            'name'               => $data['name'],
            'username'           => $data['username'] ?? null,
            'encrypted_password' => $encPwd,
            'iv'                 => $iv,
            'notes_encrypted'    => $encNotes,
            'iv_notes'           => $ivNotes,
            'type'               => $data['type'] ?? 'password',
        ]);
    }

    public function update(Credential $credential, array $data): Credential
    {
        // Snapshot de la versión actual antes de modificar
        $credential->versions()->create([
            'changed_by'         => $credential->created_by,
            'username'           => $credential->username,
            'encrypted_password' => $credential->encrypted_password,
            'iv'                 => $credential->iv,
        ]);

        $updates = array_filter([
            'name'     => $data['name'] ?? null,
            'username' => array_key_exists('username', $data) ? $data['username'] : null,
            'type'     => $data['type'] ?? null,
        ], fn ($v) => $v !== null);

        if (isset($data['password'])) {
            ['ciphertext' => $encPwd, 'iv' => $iv] = $this->encryption->encrypt($data['password']);
            $updates['encrypted_password'] = $encPwd;
            $updates['iv']                 = $iv;
        }

        if (array_key_exists('notes', $data)) {
            if ($data['notes']) {
                ['ciphertext' => $encNotes, 'iv' => $ivNotes] = $this->encryption->encrypt($data['notes']);
                $updates['notes_encrypted'] = $encNotes;
                $updates['iv_notes']        = $ivNotes;
            } else {
                $updates['notes_encrypted'] = null;
                $updates['iv_notes']        = null;
            }
        }

        $credential->update($updates);

        return $credential->fresh();
    }

    public function delete(Credential $credential): void
    {
        $credential->delete();
    }

    public function decrypt(Credential $credential): string
    {
        return $this->encryption->decrypt($credential->encrypted_password, $credential->iv);
    }

    public function decryptNotes(Credential $credential): ?string
    {
        if (! $credential->notes_encrypted) {
            return null;
        }

        return $this->encryption->decrypt($credential->notes_encrypted, $credential->iv_notes);
    }
}
