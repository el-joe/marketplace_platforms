<?php

namespace App\Services\Delivery;

use App\Models\DeliveryAssignment;
use App\Services\FileService;
use Illuminate\Http\UploadedFile;

class ProofOfDeliveryService
{
    public function __construct(private readonly FileService $fileService) {}

    /** Store proof image and return the files.id. */
    public function store(DeliveryAssignment $assignment, UploadedFile $file): int
    {
        $record = $this->fileService->store(
            $file,
            DeliveryAssignment::class,
            $assignment->id,
            'delivery_proof'
        );

        return $record->id;
    }
}
