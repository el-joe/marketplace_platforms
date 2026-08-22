import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@/src/lib/utils";

const badgeVariants = cva(
  "inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap w-fit",
  {
    variants: {
      variant: {
        gray: "bg-gray-2 text-light",
        blue: "bg-light-blue text-blue-3",
        green: "bg-light-green text-green",
        red: "bg-light-red text-red",
        orange: "bg-orange/10 text-orange",
        yellow: "bg-main/30 text-primary",
      },
    },
    defaultVariants: {
      variant: "gray",
    },
  },
);

type BadgeProps = React.ComponentProps<"span"> & VariantProps<typeof badgeVariants>;

function Badge({ className, variant, ...props }: BadgeProps) {
  return (
    <span
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  );
}

export { Badge, badgeVariants };
