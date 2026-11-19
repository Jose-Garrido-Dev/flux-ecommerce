import Image from "next/image";
import Link from "next/link";
import type { HomeData } from "@/lib/types";
import { mediaUrl } from "@/lib/format";

/** Mosaico de categorías: la primera ocupa el doble de espacio. */
export function CategoryBento({ categories }: { categories: HomeData["categories"] }) {
  const items = categories.slice(0, 5);
  return (
    <ul className="grid auto-rows-[15rem] gap-4 sm:auto-rows-[18rem] md:grid-cols-3">
      {items.map((c, i) => (
        <li
          key={c.slug}
          data-reveal
          style={{ "--reveal-delay": `${i * 90}ms` } as React.CSSProperties}
          className={i === 0 ? "md:col-span-2 md:row-span-2" : ""}
        >
          <Link href={`/categoria/${c.slug}`} className="group relative block h-full overflow-hidden rounded-[var(--radius)] bg-subtle">
            {c.image && (
              <Image
                src={mediaUrl(c.image.path)}
                alt=""
                fill
                sizes={i === 0 ? "(min-width: 768px) 66vw, 100vw" : "(min-width: 768px) 33vw, 100vw"}
                className="object-cover transition-transform duration-[1.4s] ease-[var(--ease-out)] group-hover:scale-[1.06]"
              />
            )}
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/5 to-transparent" />
            <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-4 p-6 text-white sm:p-8">
              <div>
                <p className={`font-display ${i === 0 ? "text-6xl sm:text-8xl" : "text-5xl"}`}>{c.name}</p>
              </div>
              <span
                aria-hidden
                className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-fg transition-transform duration-[var(--motion)] group-hover:-rotate-45"
              >
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
              </span>
            </div>
          </Link>
        </li>
      ))}
    </ul>
  );
}
