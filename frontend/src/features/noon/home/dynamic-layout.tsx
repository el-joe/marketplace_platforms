import { cn } from "@/src/lib/utils";
import { blocks } from "./helpers/blocks-catalog";
import { PageBuilderSection } from "./types";

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

  return (
    <div className="container">
      <section
        className="w-full"
        style={{
          backgroundColor: section.background_color ?? undefined,
          backgroundImage: section.background_image_url
            ? `url(${section.background_image_url})`
            : undefined,
          // backgroundSize: "contain",
          backgroundPositionX: "center",
          backgroundRepeat: "no-repeat",
          maxWidth: section.max_width || "100%",
          paddingTop: `${section.padding_top}px`,
          paddingBottom: `${section.padding_bottom}px`,
        }}
      >
        {section.layout === "columns" && (
          <div className="flex w-full gap-2">
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
                className={cn(
                  "w-full",
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
