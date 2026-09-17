/** Raw shape returned by `GET {country}/help-center/tree` — see backend
 * `App\Services\Customer\HelpCenterService::getTree()`. */
export interface BilingualText {
  ar: string | null;
  en: string | null;
}

export interface HelpCenterArticleDto {
  id: string;
  slug: string;
  title: BilingualText;
  excerpt: BilingualText;
  body: BilingualText;
}

export interface HelpCenterCategoryDto {
  id: string;
  slug: string;
  title: BilingualText;
  description: BilingualText;
  icon: string | null;
  children: HelpCenterCategoryDto[];
  articles: HelpCenterArticleDto[];
}
