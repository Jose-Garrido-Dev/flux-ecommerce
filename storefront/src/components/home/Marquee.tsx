import Link from "next/link";

/** Cinta con nombres (marcas, categorías) en movimiento continuo. */
export function Marquee({ items, size = "lg" }: { items: { label: string; href: string }[]; size?: "lg" | "sm" }) {
  if (items.length === 0) return null;
  const loop = Array.from({ length: Math.max(2, Math.ceil(10 / items.length)) }, () => items).flat();

  return (
    <div className="marquee-wrap relative overflow-hidden border-y border-border py-6 [mask-image:linear-gradient(90deg,transparent,#000_8%,#000_92%,transparent)]">
      <ul className="sr-only">
        {items.map((it) => (
          <li key={it.href}>
            <Link href={it.href}>{it.label}</Link>
          </li>
        ))}
      </ul>
      <div aria-hidden className="marquee" style={{ "--marquee-duration": `${loop.length * 4}s` } as React.CSSProperties}>
        {[0, 1].map((half) => (
          <div key={half} className="flex shrink-0 items-center">
            {loop.map((it, i) => (
              <Link
                key={`${half}-${i}`}
                href={it.href}
                tabIndex={-1}
                className={`flex items-center gap-10 px-5 font-display transition-colors hover:text-accent ${size === "lg" ? "text-6xl sm:text-8xl" : "text-2xl"}`}
              >
                <span>{it.label}</span>
                <span className="h-3 w-3 rounded-full bg-accent" />
              </Link>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}
