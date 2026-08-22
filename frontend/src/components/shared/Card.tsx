import { cn } from "@/src/lib/utils";

const Card = ({ className, ...props }: React.ComponentProps<"div">) => {
  return (
    <div
      data-slot="card"
      className={cn("bg-white rounded-2xl", className)}
      {...props}
    />
  );
};

export default Card;
