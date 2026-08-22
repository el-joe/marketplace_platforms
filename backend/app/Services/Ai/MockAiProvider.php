<?php

namespace App\Services\Ai;

use App\Services\AiProviderInterface;

/**
 * Stub provider used during development and testing.
 *
 * All methods return the input unchanged (or a placeholder path) so that
 * the full UI/job/notification flow can be exercised without a live AI API.
 *
 * SWAP THIS OUT: replace with OpenAiProvider, ReplicateProvider, FalAiProvider, etc.
 * once a vendor is selected. See AiProviderFactory::make() for the wiring point.
 */
class MockAiProvider implements AiProviderInterface
{
    public function enhanceImage(string $imagePath): string
    {
        // In a real provider: call the upscaling API, save result to storage, return new path.
        // Mock: return the original path so "before" and "after" are identical during dev.
        return $imagePath;
    }

    public function generateTryOn(string $personPhoto, string $productImage): string
    {
        // In a real provider: send both images to the try-on model, poll for result, return path.
        // Mock: return a static placeholder so the result modal renders.
        return 'placeholders/tryon_placeholder.jpg';
    }

    public function generateVideo(string $prompt, array $sourceImages): string
    {
        // In a real provider: submit the job to a video generation API, poll until done, return path.
        // Mock: return a placeholder video so the download link renders.
        return 'placeholders/video_placeholder.mp4';
    }
}
