import { cn } from "@/src/lib/utils";
import Image from "next/image";
import { PageBuilderSection } from "./types";
import { blocks } from "./helpers/blocks-catalog";

const widthClasses: Record<string, string> = {
  "1/3": "w-1/3 flex-1",
  "2/3": "w-2/3 flex-1",
  "1/2": "w-1/2 flex-1",
  "1/4": "w-1/4 flex-1",
  "3/4": "w-3/4 flex-1",
  full: "w-full flex-1",
};

export function DynamicLayout({ section }: { section: PageBuilderSection }) {
  const widths = section.columns_config?.widths?.split(" ") ?? [];

  const hasHeaderBg =
    section.background_image_type === "header" && section.background_image_url;
  const hasSectionBg =
    section.background_image_type === "section" && section.background_image_url;

  return (
    <div className="container">
      <section
        className={cn(
          "w-full mb-6a",
          section?.position > 0 && "mb-1",
          section.position === 0 && "mb-1 lg:mb-0",
        )}
        style={{
          backgroundColor: section.background_color ?? undefined,
          backgroundImage: hasSectionBg
            ? `url(${section.background_image_url})`
            : undefined,
          backgroundSize: "cover",
          backgroundPositionX: "center",
          backgroundRepeat: "no-repeat",
          // maxWidth: section.max_width || "100%",
          // paddingTop: `${section.padding_top}px`,
          // paddingBottom: `${section.padding_bottom}px`,
        }}
      >
        {hasHeaderBg && (
          <div className="relative h-20 lg:h-28">
            <Image
              src={section?.background_image_url || ""}
              alt={section?.name}
              fill
              className="object-cover"
            />
          </div>
        )}
        {section.layout === "columns" && (
          <div
            className={cn(
              "flex w-full",
              section?.columns.length > 2 && "gap-2",
              section.position === 1 &&
                (hasHeaderBg ? "py-2 lg:pb-0" : "py-3 lg:py-0"),
              section.position > 1 &&
                (hasHeaderBg ? "pb-3 xl:pb-6 pt-1" : "py-6"),
              section.background_color && "px-4",
            )}
          >
            {section.columns.map((block, i) => {
              const b = block[0];
              const BlockComponent =
                blocks[b.block_type as keyof typeof blocks];
              return (
                <div
                  key={b.id}
                  className={cn(
                    widthClasses[widths[i]] ?? "w-full",
                    b.device_target === "desktop"
                      ? "hidden lg:block"
                      : b.device_target === "mobile"
                        ? "block lg:hidden"
                        : b.device_target === "app"
                          ? "hidden"
                          : "block",
                  )}
                >
                  {BlockComponent ? <BlockComponent data={b} /> : null}
                </div>
              );
            })}
          </div>
        )}
        {section.layout === "stack" &&
          section.blocks.map((b) => {
            const BlockComponent = blocks[b.block_type as keyof typeof blocks];
            return BlockComponent ? (
              <div
                key={b.id}
                className={cn(
                  "w-full",
                  section.position === 1 &&
                    (hasHeaderBg
                      ? "pb-3 lg:pb-0 pt-3 lg:pt-0"
                      : "py-6 lg:py-0"),
                  section.position > 1 &&
                    (hasHeaderBg ? "pb-3 xl:pb-6 pt-1" : "py-6"),
                  section.background_color && "px-4",
                  b.device_target === "desktop"
                    ? "hidden lg:block"
                    : b.device_target === "mobile"
                      ? "block lg:hidden"
                      : "block",
                )}
              >
                <BlockComponent data={b} />
              </div>
            ) : null;
          })}
      </section>
    </div>
  );
}
