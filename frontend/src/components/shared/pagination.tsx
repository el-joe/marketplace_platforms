import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";

import { Link } from "@/i18n/navigation";

type Props = {
  totalPages: number;
};

const Pagination = ({ totalPages }: Props) => {
  const currentPage = 1;

  return (
    <nav className="flex items-center justify-center gap-2 py-6">
      <Link
        href={""}
        aria-disabled={currentPage === 1}
        className={`flex size-8 items-center justify-center rounded-lg border border-border-color ${
          currentPage === 1
            ? "pointer-events-none opacity-50"
            : "hover:bg-gray-2"
        }`}
      >
        <ChevronLeftIcon className="size-4" />
      </Link>
      {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
        <Link
          key={page}
          href={""}
          className={`bg-transparent border border-border-color flex items-center justify-center text-sm duration-300 rounded-[2px] px-2 py-[10px] ${
            page === currentPage
              ? "border-blue! text-blue! font-bold"
              : "hover:border-black"
          }`}
        >
          {page}
        </Link>
      ))}
      <Link
        href={""}
        aria-disabled={currentPage === totalPages}
        className={`flex size-8 items-center justify-center rounded-lg border border-border-color ${
          currentPage === totalPages
            ? "pointer-events-none opacity-50"
            : "hover:bg-gray-2"
        }`}
      >
        <ChevronRightIcon className="size-4" />
      </Link>
    </nav>
  );
};

export default Pagination;
