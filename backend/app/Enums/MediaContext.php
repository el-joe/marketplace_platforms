<?php

namespace App\Enums;

/**
 * Where an admin media upload belongs (client item #27; blog / help-center / ad-support rich text added in QC).
 *
 * Each context keeps the storage directory and `files.file_type` values the older
 * per-screen upload endpoints already used, so stored URLs / file ids stay compatible
 * and the "recent uploads" library also lists images uploaded before the shared field.
 */
enum MediaContext: string
{
    case Slides = 'slides';
    case AdImages = 'ad-images';
    case PromoTiles = 'promo-tiles';
    case SectionBackgrounds = 'section-backgrounds';
    case VideoPosters = 'video-posters';
    case CustomPages = 'custom-pages';
    case StaticPages = 'static-pages';
    case BlogPosts = 'blog-posts';
    case HelpCenterArticles = 'help-center-articles';
    case AdSupportArticles = 'ad-support-articles';

    /** Extensions accepted by every context (raster images only; SVG can carry scripts). */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

    /** Directory on the `public` disk. */
    public function directory(): string
    {
        return match ($this) {
            self::Slides => 'page-builder/slides',
            self::AdImages => 'page-builder/ad-images',
            self::PromoTiles => 'page-builder/promo-tiles',
            self::SectionBackgrounds => 'page-builder/section-backgrounds',
            self::VideoPosters => 'page-builder/video-posters',
            self::CustomPages => 'custom-pages/library',
            self::StaticPages => 'static-pages/content',
            self::BlogPosts => 'blog/content',
            self::HelpCenterArticles => 'helpcenter/content',
            self::AdSupportArticles => 'adsupport/content',
        };
    }

    /** `files.file_type` for a new upload (slides keep the desktop/mobile split). */
    public function fileType(?string $device = null): string
    {
        return match ($this) {
            self::Slides => $device === 'mobile' ? 'slide_mobile' : 'slide_desktop',
            self::AdImages => 'ad_image',
            self::PromoTiles => 'promo_tile',
            self::SectionBackgrounds => 'section_background',
            self::VideoPosters => 'video_poster',
            self::CustomPages => 'custom_page_image',
            self::StaticPages => 'static_page_image',
            self::BlogPosts => 'blog_post_image',
            self::HelpCenterArticles => 'help_center_article_image',
            self::AdSupportArticles => 'ad_support_article_image',
        };
    }

    /** @return list<string> file types listed in this context's "recent uploads" library */
    public function libraryFileTypes(): array
    {
        return match ($this) {
            self::Slides => ['slide_desktop', 'slide_mobile'],
            default => [$this->fileType()],
        };
    }

    /** @return list<string> any one of these permissions allows uploading in this context */
    public function permissions(): array
    {
        return match ($this) {
            self::CustomPages => ['categories.create', 'categories.edit'],
            self::StaticPages => ['static_pages.create', 'static_pages.edit'],
            // Blog, help-center and ad-support articles are written under the same permission as their routes.
            self::BlogPosts => ['blog.manage'],
            self::HelpCenterArticles => ['help_center.manage'],
            self::AdSupportArticles => ['adsupport.manage'],
            default => ['pages.manage'],
        };
    }

    public function maxKilobytes(): int
    {
        return match ($this) {
            self::CustomPages => 4096,
            default => 8192,
        };
    }

    /** Route gate covering every context; the controller then checks the context's own permissions. */
    public static function routePermissionGate(): string
    {
        return 'admin.permission:'.collect(self::cases())
            ->flatMap(fn (self $context) => $context->permissions())
            ->unique()
            ->implode('|');
    }
}
