import Link from "next/link";
import type { SearchParams } from "@/lib/catalog";

export function Pagination({
  page,
  lastPage,
  pathname,
  params,
}: {
  page: number;
  lastPage: number;
  pathname: string;
  params: SearchParams;
}) {
  if (lastPage <= 1) return null;

  const href = (p: number) => {
    const next = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) if (typeof v === "string" && v !== "") next.set(k, v);
    if (p > 1) next.set("page", String(p));
    else next.delete("page");
    const qs = next.toString();
    return qs ? `${pathname}?${qs}` : pathname;
  };

  const pages = Array.from({ length: lastPage }, (_, i) => i + 1).filter(
    (p) => p === 1 || p === lastPage || Math.abs(p - page) <= 1,
  );

  const item = "flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm";

  return (
    <nav aria-label="Paginación" className="mt-14 flex justify-center">
      <ul className="flex items-center gap-1">
        {page > 1 && (
          <li>
            <Link href={href(page - 1)} className={`${item} hover:bg-subtle`} rel="prev">
              Anterior
            </Link>
          </li>
        )}
        {pages.map((p, i) => (
          <li key={p} className="flex items-center">
            {i > 0 && p - pages[i - 1] > 1 && <span className="px-1 text-muted">…</span>}
            <Link
              href={href(p)}
              aria-current={p === page ? "page" : undefined}
              className={`${item} ${p === page ? "bg-fg text-bg" : "hover:bg-subtle"}`}
            >
              {p}
            </Link>
          </li>
        ))}
        {page < lastPage && (
          <li>
            <Link href={href(page + 1)} className={`${item} hover:bg-subtle`} rel="next">
              Siguiente
            </Link>
          </li>
        )}
      </ul>
    </nav>
  );
}
