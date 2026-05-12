<?php

namespace App\Services;

use App\Models\Organization;

class CredentialExportService
{
    public function __construct(private CredentialService $credentials) {}

    public function toCsv(Organization $organization): \Generator
    {
        $headers = ['id', 'name', 'username', 'password', 'type', 'category', 'division', 'notes'];

        yield implode(',', $headers) . "\n";

        $organization->credentials()
            ->with(['category.division'])
            ->lazyById()
            ->each(function ($credential) {
                $row = [
                    $credential->id,
                    $this->escapeCsv($credential->name),
                    $this->escapeCsv($credential->username ?? ''),
                    $this->escapeCsv($this->credentials->decrypt($credential)),
                    $credential->type,
                    $this->escapeCsv($credential->category->name ?? ''),
                    $this->escapeCsv($credential->category->division->name ?? ''),
                    $this->escapeCsv($this->credentials->decryptNotes($credential) ?? ''),
                ];

                yield implode(',', $row) . "\n";
            });
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
