<?php

namespace App\Services;

/**
 * Strategy interface for AI feature providers.
 *
 * To add a real provider (OpenAI, Replicate, fal.ai, etc.):
 *   1. Create app/Services/Ai/YourProvider.php implementing this interface
 *   2. Add a case to AiProviderFactory::make()
 *   3. Set AI_PROVIDER=your_key in .env
 */
interface AiProviderInterface
{
    /**
     * Upscale / enhance an image.
     *
     * @param  string $imagePath  Absolute storage path of the source image
     * @return string             Storage path of the enhanced image
     */
    public function enhanceImage(string $imagePath): string;

    /**
     * Composite a person photo with a product image (virtual try-on).
     *
     * @param  string $personPhoto    Storage path of the customer's photo
     * @param  string $productImage   Storage path of the garment/shoe image
     * @return string                 Storage path of the result composite
     */
    public function generateTryOn(string $personPhoto, string $productImage): string;

    /**
     * Generate a promotional video from a text prompt and optional source images.
     *
     * @param  string   $prompt        Natural-language description of the video
     * @param  string[] $sourceImages  Storage paths of images to use as visual context
     * @return string                  Storage path of the generated video file
     */
    public function generateVideo(string $prompt, array $sourceImages): string;
}
