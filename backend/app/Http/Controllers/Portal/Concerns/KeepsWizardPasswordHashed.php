<?php

namespace App\Http\Controllers\Portal\Concerns;

use Illuminate\Support\Facades\Hash;

/**
 * Multi-step registration wizards keep each validated step in the session until the final submit.
 * The password must never sit there (or be echoed back into the page) in plain text: step 1 stores
 * only its hash, and the page gets the saved data without it.
 */
trait KeepsWizardPasswordHashed
{
    /**
     * Password rules for step 1. Once a hash is saved the applicant may re-save step 1 (e.g. after
     * going back to fix the phone) without retyping the password; a new one replaces the hash.
     *
     * @param  array<string, mixed>  $savedStep1
     * @return array<string, mixed>
     */
    protected function wizardPasswordRules(array $savedStep1): array
    {
        $presence = filled($savedStep1['password_hash'] ?? null) ? 'nullable' : 'required';

        return [
            'password' => [$presence, 'string', 'min:8', 'confirmed'],
            'password_confirmation' => [$presence],
        ];
    }

    /**
     * Validated step-1 data with the plaintext password replaced by its hash.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $savedStep1
     * @return array<string, mixed>
     */
    protected function hashWizardPassword(array $validated, array $savedStep1): array
    {
        $password = $validated['password'] ?? null;
        unset($validated['password'], $validated['password_confirmation']);

        $validated['password_hash'] = filled($password)
            ? Hash::make((string) $password)
            : ($savedStep1['password_hash'] ?? null);

        return $validated;
    }

    /**
     * The hash to store on the new account (sessions saved before this change still hold plain text).
     *
     * @param  array<string, mixed>  $step1
     */
    protected function wizardPasswordHash(array $step1): ?string
    {
        if (filled($step1['password_hash'] ?? null)) {
            return (string) $step1['password_hash'];
        }

        return filled($step1['password'] ?? null) ? Hash::make((string) $step1['password']) : null;
    }

    /**
     * Saved wizard data safe to render into the page (no password, no hash).
     *
     * @param  array<string, mixed>  $savedData
     * @return array<string, mixed>
     */
    protected function wizardDataForView(array $savedData): array
    {
        if (isset($savedData['step1']) && is_array($savedData['step1'])) {
            unset($savedData['step1']['password'], $savedData['step1']['password_confirmation'], $savedData['step1']['password_hash']);
        }

        return $savedData;
    }
}
