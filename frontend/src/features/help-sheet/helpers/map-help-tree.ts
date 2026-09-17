import { MessageCircleIcon } from "lucide-react";
import type { HelpFaqItem, HelpNode } from "../types";
import type {
  BilingualText,
  HelpCenterArticleDto,
  HelpCenterCategoryDto,
} from "../api/help-center-tree.types";
import { resolveHelpIcon } from "./resolve-help-icon";

function pick(text: BilingualText, locale: "ar" | "en"): string {
  return text[locale] ?? text.en ?? text.ar ?? "";
}

function mapArticleToFaqItem(
  article: HelpCenterArticleDto,
  locale: "ar" | "en",
): HelpFaqItem {
  return {
    id: article.id,
    question: pick(article.title, locale),
    description: pick(article.excerpt, locale),
    answer: pick(article.body, locale),
  };
}

function mapCategory(
  category: HelpCenterCategoryDto,
  locale: "ar" | "en",
): HelpNode {
  const title = pick(category.title, locale);
  const description = pick(category.description, locale);
  const icon = resolveHelpIcon(category.slug);

  if (category.children.length > 0) {
    return {
      id: category.id,
      title,
      description,
      icon,
      type: "nested",
      children: category.children.map((child) => mapCategory(child, locale)),
    };
  }

  if (category.articles.length > 0) {
    return {
      id: category.id,
      title,
      description,
      icon,
      type: "action",
      viewType: "faq",
      faqItems: category.articles.map((article) =>
        mapArticleToFaqItem(article, locale),
      ),
    };
  }

  return {
    id: category.id,
    title,
    description,
    icon,
    type: "action",
    viewType: "items-list",
  };
}

/**
 * Maps the `GET {country}/help-center/tree` response into the `HelpNode`
 * tree the help-sheet UI already knows how to render (unchanged from when
 * it consumed the static mock in `mockData.ts`).
 */
export function mapHelpCenterTreeToHelpNode(
  categories: HelpCenterCategoryDto[],
  locale: "ar" | "en",
  rootTitle: string,
): HelpNode {
  return {
    id: "root",
    title: rootTitle,
    description: "",
    icon: MessageCircleIcon,
    type: "nested",
    children: categories.map((category) => mapCategory(category, locale)),
  };
}
