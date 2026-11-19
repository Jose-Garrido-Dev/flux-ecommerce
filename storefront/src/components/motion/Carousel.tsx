"use client";

import { useRef } from "react";

/** Carrusel horizontal con scroll nativo (táctil y teclado) y flechas para escritorio. */
export function Carousel({ label, children }: { label: string; children: React.ReactNode }) {
  const track = useRef<HTMLUListElement>(null);
  const move = (dir: 1 | -1) => {
    const el = track.current;
    if (el) el.scrollBy({ left: dir * el.clientWidth * 0.8, behavior: "smooth" });
  };
  const btn =
    "flex h-11 w-11 items-center justify-center rounded-full border border-border bg-surface transition-colors hover:bg-fg hover:text-bg";

  return (
    <div className="relative">
      <div className="absolute -top-16 right-0 hidden gap-2 md:flex">
        <button type="button" onClick={() => move(-1)} className={btn} aria-label={`Anterior: ${label}`}>
          <svg aria-hidden width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <button type="button" onClick={() => move(1)} className={btn} aria-label={`Siguiente: ${label}`}>
          <svg aria-hidden width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M9 6l6 6-6 6" /></svg>
        </button>
      </div>
      <ul
        ref={track}
        aria-label={label}
        className="no-scrollbar -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-px-4 px-4 pb-2 sm:-mx-6 sm:scroll-px-6 sm:px-6 lg:-mx-8 lg:scroll-px-8 lg:px-8"
      >
        {children}
      </ul>
    </div>
  );
}
