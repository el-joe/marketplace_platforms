"use client";
import { Loader2Icon, SearchIcon, TrendingUpIcon, XIcon } from "lucide-react";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "../../../components/ui/input-group";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { useSearchParams } from "next/navigation";
import React, { useEffect, useRef, useState } from "react";
import { useSearchHistory } from "@/src/layout/noon/header/helpers/use-search-history";
import Image from "next/image";
import { ISearchSuggestionsData } from "./types/search.type";
import { getSearchSuggestionsService } from "./api/get";
import { cn } from "@/src/lib/utils";

const SearchField = () => {
  // const locale = useLocale();
  const t = useTranslations("header");
  const router = useRouter();
  const searchParams = useSearchParams();

  const [query, setQuery] = useState(() => searchParams.get("q") ?? "");
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [suggestions, setSuggestions] = useState<ISearchSuggestionsData | null>(
    null,
  );

  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const abortControllerRef = useRef<AbortController | null>(null);

  const { history, addSearch, removeSearch } = useSearchHistory();

  // Handle outside click to close suggestions dropdown
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent | TouchEvent) => {
      if (
        containerRef.current &&
        !containerRef.current.contains(e.target as Node)
      ) {
        setIsOpen(false);
      }
    };

    document.addEventListener("mousedown", handleClickOutside);
    document.addEventListener("touchstart", handleClickOutside);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
      document.removeEventListener("touchstart", handleClickOutside);
    };
  }, []);

  // Debounced search suggestions fetching
  useEffect(() => {
    const trimmed = query.trim();

    if (!trimmed) {
      abortControllerRef.current?.abort();
      return;
    }

    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
    const controller = new AbortController();
    abortControllerRef.current = controller;

    const timer = setTimeout(async () => {
      setIsLoading(true);
      try {
        const res = await getSearchSuggestionsService(
          trimmed,
          controller.signal,
        );
        if (res?.data) {
          setSuggestions(res.data);
        }
      } catch (err: unknown) {
        if ((err as Error)?.name !== "AbortError") {
          setSuggestions(null);
        }
      } finally {
        setIsLoading(false);
      }
    }, 250);

    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [query]);

  // Debounced trending search fetching
  useEffect(() => {
    if (!isOpen || !!query.length) return;
    (async () => {
      try {
        const res = await getSearchSuggestionsService("");
        if (res?.data) {
          setSuggestions(res.data);
        }
      } catch (err: unknown) {
        if ((err as Error)?.name !== "AbortError") {
          setSuggestions(null);
        }
      }
    })();
  }, [isOpen, query.length]);

  const handleSearch = (searchTerm: string) => {
    const term = searchTerm.trim();
    if (!term) return;

    addSearch(term);
    setIsOpen(false);
    inputRef.current?.blur();
    router.push(`/search/?q=${encodeURIComponent(term)}`);
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Enter") {
      e.preventDefault();
      handleSearch(query);
    } else if (e.key === "Escape") {
      setIsOpen(false);
    }
  };

  const visibleSuggestions = query.trim() ? suggestions : null;
  const hasSuggestions =
    visibleSuggestions &&
    (visibleSuggestions.queries?.length > 0 ||
      visibleSuggestions.products?.length > 0 ||
      visibleSuggestions.categories?.length > 0 ||
      visibleSuggestions.vendors?.length > 0);

  return (
    <div ref={containerRef} className="flex-1 relative min-w-35">
      <InputGroup
        className={cn(
          "h-10 md:h-11 text-base bg-white! shadow-xs focus-within:ring-2 focus-within:ring-primary/20 transition-all rounded-lg border-border",
          isOpen && "rounded-b-none border-b-0 outline-0",
        )}
      >
        <InputGroupAddon align="inline-start">
          <button
            type="button"
            onClick={() => handleSearch(query)}
            aria-label={t("searchFor")}
            className="text-primary hover:text-primary/80 transition-colors lg:p-1 cursor-pointer flex items-center justify-center"
          >
            <SearchIcon className="size-4 lg:size-5" />
          </button>
        </InputGroupAddon>

        <InputGroupInput
          ref={inputRef}
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder={t("searchPlaceholder")}
          className="text-xs md:text-base h-full font-normal placeholder:text-muted-foreground/70"
        />

        {isLoading && (
          <InputGroupAddon align="inline-end">
            <Loader2Icon className="size-4 animate-spin text-muted-foreground me-1" />
          </InputGroupAddon>
        )}

        {!isLoading && query && (
          <InputGroupAddon align="inline-end">
            <button
              type="button"
              onClick={() => {
                setQuery("");
                setSuggestions(null);
                inputRef.current?.focus();
              }}
              className="text-muted-foreground hover:text-foreground transition-colors p-1 rounded-full hover:bg-muted/80 cursor-pointer me-1"
              aria-label="Clear input"
            >
              <XIcon className="size-4" />
            </button>
          </InputGroupAddon>
        )}
      </InputGroup>

      {/* Dropdown for history and suggestions */}
      {isOpen && (
        <div className="max-w-[320px] lg:max-w-full absolute top-full lg:inset-s-0 inset-e-0 mt-1.5s bg-white dark:bg-card rounded-b-xl border border-border shadow-xl z-50 overflow-hidden max-h-120 overflow-y-auto divide-y divide-border/60 animate-in fade-in-50 zoom-in-95 duration-150">
          {/* Query is empty: Show search history */}
          {!query.trim() && (
            <div className="p-4">
              {history.length > 0 ? (
                <div>
                  <p className="text-lg font-semibold mb-2">
                    {t("recentlySearched")}
                  </p>
                  <ul className="py-1 flex gap-3 flex-wrap">
                    {history.map((item) => (
                      <li
                        key={item}
                        className="flex items-center justify-between pe-2 text-sm hover:bg-muted/60 transition-colors group cursor-pointer border border-border rounded-lg overflow-hidden"
                        onClick={() => {
                          setQuery(item);
                          handleSearch(item);
                        }}
                      >
                        <span className="bg-muted/60 w-8">
                          <Image
                            src={"/images/no-image-available-icon.jpg"}
                            alt="Oops"
                            width={50}
                            height={40}
                            className="min-w-8"
                          />
                        </span>
                        <p className="text-sm flex-1 line-clamp-2">{item}</p>
                        {/* </span> */}
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            removeSearch(item);
                          }}
                          className="p-1 text-muted-foreground cursor-pointer"
                          aria-label="Remove item"
                        >
                          <XIcon className="size-5" />
                        </button>
                      </li>
                    ))}
                  </ul>
                  {/* trending searches */}
                  <p className="text-lg font-semibold mb-2 mt-4">
                    {t("trendingSearches")}
                  </p>
                  <ul className="py-1 flex gap-3 flex-wrap">
                    {suggestions?.trending.map((item) => (
                      <li
                        key={item}
                        className="flex items-center justify-between px-2 py-1 gap-2 text-sm hover:bg-muted/60 transition-colors group cursor-pointer border border-border rounded-lg"
                        onClick={() => {
                          setQuery(item);
                          handleSearch(item);
                        }}
                      >
                        <TrendingUpIcon className="size-4 text-gray" />
                        <p className="line-clamp-2">{item}</p>
                        {/* </span> */}
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            removeSearch(item);
                          }}
                          className="p-1 text-muted-foreground cursor-pointer"
                          aria-label="Remove item"
                        >
                          <XIcon className="size-5" />
                        </button>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : (
                <div className="px-4 py-6 text-center text-sm text-muted-foreground">
                  {t("searchPlaceholder")}
                </div>
              )}
            </div>
          )}

          {/* Query is not empty: Show suggestions and direct search */}
          {query.trim().length > 0 && (
            <div>
              {/* Direct search option */}
              {/* <button
                type="button"
                onClick={() => handleSearch(query)}
                className="w-full flex items-center gap-3 px-4 py-3 hover:bg-primary/5 text-primary text-start font-medium text-sm transition-colors border-b border-border/40 cursor-pointer"
              >
                <SearchIcon className="size-4 shrink-0" />
                <span className="truncate">
                  {t("searchFor")}: &ldquo;
                  <span className="font-semibold">{query}</span>&rdquo;
                </span>
              </button> */}

              {/* Suggestions */}
              {hasSuggestions ? (
                <div className="">
                  {/* Suggested Query terms */}
                  {suggestions?.queries && suggestions?.queries.length > 0 && (
                    <div className="py-1">
                      {suggestions?.queries.map((suggestedQuery, idx) => (
                        <button
                          key={`query-${idx}-${suggestedQuery}`}
                          type="button"
                          onClick={() => {
                            setQuery(suggestedQuery);
                            handleSearch(suggestedQuery);
                          }}
                          className="w-full flex items-center gap-3 px-4 py-2 hover:bg-muted/60 text-start text-sm text-foreground transition-colors cursor-pointer"
                        >
                          <Image
                            src={"/images/no-image-available-icon.jpg"}
                            alt="Oops"
                            width={50}
                            height={60}
                            className="border border-border rounded-lg"
                          />
                          <p className="line-clamp-1 font-medium">
                            {suggestedQuery}
                          </p>
                        </button>
                      ))}
                    </div>
                  )}

                  {/* Suggested Categories */}
                  {/* {suggestions?.categories &&
                    suggestions?.categories.length > 0 && (
                      <div className="py-2">
                        <div className="px-4 pb-1 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                          {t("categories")}
                        </div>
                        {suggestions?.categories.map((category) => (
                          <button
                            key={`cat-${category.id}-${category.slug}`}
                            type="button"
                            onClick={() => {
                              setIsOpen(false);
                              router.push(`/${category.slug}`);
                            }}
                            className="w-full flex items-center gap-3 px-4 py-2 hover:bg-muted/60 text-start text-sm text-foreground transition-colors cursor-pointer"
                          >
                            <TagIcon className="size-4 text-primary shrink-0" />
                            <span className="truncate">
                              {category.name[locale]}
                            </span>
                          </button>
                        ))}
                      </div>
                    )} */}

                  {/* Suggested Products */}
                  {/* {suggestions?.products &&
                    suggestions?.products.length > 0 && (
                      <div className="py-2">
                        <div className="px-4 pb-1 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                          {t("products")}
                        </div>
                        {suggestions?.products.map((product) => (
                          <button
                            key={`prod-${product.id}-${product.slug}`}
                            type="button"
                            onClick={() => {
                              setIsOpen(false);
                              router.push(`/products/${product.slug}`);
                            }}
                            className="w-full flex items-center justify-between gap-3 px-4 py-2 hover:bg-muted/60 text-start text-sm transition-colors cursor-pointer"
                          >
                            <div className="flex items-center gap-3 min-w-0">
                              <SearchIcon className="size-4 text-muted-foreground shrink-0" />
                              <span className="truncate text-foreground font-medium text-xs lg:text-base">
                                {product.name}
                              </span>
                            </div>
                            {product.vendor && (
                              <span className="text-[9px] lg:text-xs text-muted-foreground shrink-0 bg-muted px-2 py-0.5 rounded">
                                {product.vendor}
                              </span>
                            )}
                          </button>
                        ))}
                      </div>
                    )} */}

                  {/* Suggested Vendors / Stores */}
                  {/* {suggestions?.vendors && suggestions?.vendors.length > 0 && (
                    <div className="py-2">
                      <div className="px-4 pb-1 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        {t("stores")}
                      </div>
                      {suggestions?.vendors.map((vendor) => (
                        <button
                          key={`vendor-${vendor.id}-${vendor.slug}`}
                          type="button"
                          onClick={() => {
                            setIsOpen(false);
                            router.push(`/vendors/${vendor.id}`);
                          }}
                          className="w-full flex items-center justify-between gap-3 px-4 py-2 hover:bg-muted/60 text-start text-sm transition-colors cursor-pointer"
                        >
                          <div className="flex items-center gap-3 min-w-0">
                            <StoreIcon className="size-4 text-primary shrink-0" />
                            <span className="truncate text-foreground font-medium">
                              {vendor.store_name}
                            </span>
                          </div>
                          {vendor.rating !== undefined && (
                            <span className="text-xs text-amber-600 dark:text-amber-400 font-semibold shrink-0">
                              ★ {vendor.rating}
                            </span>
                          )}
                        </button>
                      ))}
                    </div>
                  )} */}
                </div>
              ) : (
                !isLoading && (
                  <div className="px-4 py-3 text-xs text-muted-foreground">
                    Press <span className="font-semibold">Enter</span> or click
                    above to search for &ldquo;{query}&rdquo;
                  </div>
                )
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default SearchField;
