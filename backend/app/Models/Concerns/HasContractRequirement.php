<?php

namespace App\Models\Concerns;

use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared by Category and ClassifiedCategory: the "requires individual contract" switch, the legacy
 * all-countries template link (contract_template_id) and the per-country assignments.
 *
 * requires_contract is tri-state. NULL is a legacy row that never used the switch and means
 * "required when a template is linked", which is how categories behaved before the switch existed.
 */
trait HasContractRequirement
{
    public function initializeHasContractRequirement(): void
    {
        $this->mergeFillable(['requires_contract']);
        $this->mergeCasts(['requires_contract' => 'boolean']);
    }

    public function requiresIndividualContract(): bool
    {
        return $this->requires_contract ?? ($this->contract_template_id !== null);
    }

    public function contractAssignments(): HasMany
    {
        return $this->hasMany(ContractCountryAssignment::class, $this->contractAssignmentColumn());
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassifiedContractTemplate::class, 'contract_template_id');
    }

    abstract public function contractAssignmentColumn(): string;
}
