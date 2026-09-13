export default function LoadingCircles() {
  return (
    <div className="flex flex-1 items-center justify-center">
      <div className="flex items-center gap-2">
        <span className="size-3 rounded-full bg-main animate-help-pulse [animation-delay:-0.32s]" />
        <span className="size-3 rounded-full bg-main animate-help-pulse [animation-delay:-0.16s]" />
        <span className="size-3 rounded-full bg-main animate-help-pulse" />
      </div>
    </div>
  );
}
