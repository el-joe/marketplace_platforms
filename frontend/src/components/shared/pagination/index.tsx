"use client";

import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";

import usePagination from "./helpers/use-pagination";

type Props = {
  totalPages: number;
};

const Pagination = ({ totalPages }: Props) => {
  const { currentPage, goToPage } = usePagination();

  return (
    <nav className="flex items-center justify-center gap-2 py-6">
      <button
        type="button"
        onClick={() => goToPage(currentPage - 1)}
        disabled={currentPage === 1}
        aria-disabled={currentPage === 1}
        className={`flex size-8 items-center justify-center rounded-lg border border-border-color ${
          currentPage === 1
            ? "pointer-events-none opacity-50"
            : "hover:bg-gray-2"
        }`}
      >
        <ChevronLeftIcon className="size-4" />
      </button>
      {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
        <button
          key={page}
          type="button"
          onClick={() => goToPage(page)}
          className={`bg-transparent border border-border-color flex items-center justify-center text-sm duration-300 rounded-[2px] px-2 py-[10px] ${
            page === currentPage
              ? "border-blue! text-blue! font-bold"
              : "hover:border-black"
          }`}
        >
          {page}
        </button>
      ))}
      <button
        type="button"
        onClick={() => goToPage(currentPage + 1)}
        disabled={currentPage === totalPages}
        aria-disabled={currentPage === totalPages}
        className={`flex size-8 items-center justify-center rounded-lg border border-border-color ${
          currentPage === totalPages
            ? "pointer-events-none opacity-50"
            : "hover:bg-gray-2"
        }`}
      >
        <ChevronRightIcon className="size-4" />
      </button>
    </nav>
  );
};

export default Pagination;
