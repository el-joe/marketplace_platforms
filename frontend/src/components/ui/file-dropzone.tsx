"use client";

import { useId, useRef } from "react";
import { PaperclipIcon, XIcon } from "lucide-react";
import { cn } from "@/src/lib/utils";

type FileDropzoneProps = {
  files: File[];
  onFilesChange: (files: File[]) => void;
  label?: string;
  className?: string;
  accept?: string;
  multiple?: boolean;
};

/** Minimal, reusable attach-file control: click-to-browse + a removable file chip list. */
export function FileDropzone({
  files,
  onFilesChange,
  label = "Attach files",
  className,
  accept,
  multiple = true,
}: FileDropzoneProps) {
  const inputId = useId();
  const inputRef = useRef<HTMLInputElement>(null);

  return (
    <div className={cn("flex flex-col gap-2", className)}>
      <input
        ref={inputRef}
        id={inputId}
        type="file"
        accept={accept}
        multiple={multiple}
        className="sr-only"
        onChange={(e) => {
          const picked = Array.from(e.target.files ?? []);
          if (picked.length) onFilesChange([...files, ...picked]);
          e.target.value = "";
        }}
      />
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        className="inline-flex items-center gap-1.5 w-fit text-sm font-medium text-light hover:text-blue-3 cursor-pointer"
      >
        <PaperclipIcon className="size-4" />
        {label}
      </button>

      {files.length > 0 && (
        <ul className="flex flex-wrap gap-2">
          {files.map((file, i) => (
            <li
              key={`${file.name}-${i}`}
              className="inline-flex items-center gap-1.5 bg-gray-2 rounded-full ps-3 pe-2 py-1 text-xs text-light"
            >
              <span className="max-w-40 truncate">{file.name}</span>
              <button
                type="button"
                aria-label={`Remove ${file.name}`}
                onClick={() => onFilesChange(files.filter((_, idx) => idx !== i))}
                className="flex items-center justify-center rounded-full hover:bg-gray-4 cursor-pointer p-0.5"
              >
                <XIcon className="size-3" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
