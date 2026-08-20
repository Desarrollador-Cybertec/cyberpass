<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CredentialService
{
    public function __construct(private EncryptionService $encryption) {}

    public function create(Category $category, Organization $organization, User $creator, array $data): Credential
    {
        ['ciphertext' => $encPwd, 'iv' => $iv] = $this->encryption->encrypt($data['password']);

        return Credential::create([
            'category_id'        => $category->id,
            'organization_id'    => $organization->id,
            'created_by'         => $creator->id,
            'image_id'           => $data['image_id'] ?? null,
            'name'               => $data['name'],
            'username'           => $data['username'] ?? null,
            'email'              => $data['email'] ?? null,
            'nextcloud_account'  => $data['nextcloud_account'] ?? null,
            'encrypted_password' => $encPwd,
            'iv'                 => $iv,
            'url'                => $data['url'] ?? null,
            'type'               => $data['type'] ?? 'password',
        ]);
    }

    public function createPersonal(Category $category, User $creator, array $data): Credential
    {
        ['ciphertext' => $encPwd, 'iv' => $iv] = $this->encryption->encrypt($data['password']);

        return Credential::create([
            'category_id'        => $category->id,
            'user_id'            => $creator->id,
            'created_by'         => $creator->id,
            'image_id'           => $data['image_id'] ?? null,
            'name'               => $data['name'],
            'username'           => $data['username'] ?? null,
            'email'              => $data['email'] ?? null,
            'nextcloud_account'  => $data['nextcloud_account'] ?? null,
            'encrypted_password' => $encPwd,
            'iv'                 => $iv,
            'url'                => $data['url'] ?? null,
            'type'               => $data['type'] ?? 'password',
        ]);
    }

    public function update(Credential $credential, array $data, ?User $actor = null): Credential
    {
        $credential->versions()->create([
            'changed_by'         => $this->resolveActor($actor)->id,
            'username'           => $credential->username,
            'encrypted_password' => $credential->encrypted_password,
            'iv'                 => $credential->iv,
        ]);

        // Semantica de clave presente, no de valor no-nulo. Antes esto era un
        // array_filter(..., fn ($v) => $v !== null), que volvia a descartar el
        // username nulo que la linea de arriba se molestaba en conservar: no
        // habia forma de limpiar un username, ni desde el SPA ni desde el API,
        // aunque las reglas de validacion lo declaran nullable.
        $updates = [];

        foreach (['name', 'type'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('username', $data)) {
            $updates['username'] = $data['username'];
        }

        if (array_key_exists('email', $data)) {
            $updates['email'] = $data['email'];
        }

        if (array_key_exists('nextcloud_account', $data)) {
            $updates['nextcloud_account'] = $data['nextcloud_account'];
        }

        if (array_key_exists('image_id', $data)) {
            $updates['image_id'] = $data['image_id'];
        }

        if (isset($data['password'])) {
            ['ciphertext' => $encPwd, 'iv' => $iv] = $this->encryption->encrypt($data['password']);
            $updates['encrypted_password'] = $encPwd;
            $updates['iv']                 = $iv;
        }

        if (array_key_exists('url', $data)) {
            $updates['url'] = $data['url'] ?: null;
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

    public function decryptVersion(CredentialVersion $version): string
    {
        return $this->encryption->decrypt($version->encrypted_password, $version->iv);
    }

    public function restoreVersion(Credential $credential, CredentialVersion $version, ?User $actor = null): Credential
    {
        // Snapshot current state before overwriting
        $credential->versions()->create([
            'changed_by'         => $this->resolveActor($actor)->id,
            'username'           => $credential->username,
            'encrypted_password' => $credential->encrypted_password,
            'iv'                 => $credential->iv,
        ]);

        $credential->update([
            'username'           => $version->username,
            'encrypted_password' => $version->encrypted_password,
            'iv'                 => $version->iv,
        ]);

        return $credential->fresh();
    }

    private function resolveActor(?User $actor = null): User
    {
        $authenticatedUser = $actor ?? Auth::user();

        if (! $authenticatedUser instanceof User) {
            throw new \RuntimeException('No authenticated user available for credential versioning.');
        }

        return $authenticatedUser;
    }
}
