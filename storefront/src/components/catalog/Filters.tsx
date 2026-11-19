import Link from "next/link";
import type { Facet } from "@/lib/types";
import type { SearchParams } from "@/lib/catalog";

/** Construye la URL con un valor de filtro agregado o quitado (los filtros viven en la URL). */
function toggleHref(pathname: string, params: SearchParams, code: string, value: string): string {
  const next = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) if (typeof v === "string" && v !== "") next.set(k, v);
  const current = (next.get(code) ?? "").split(",").filter(Boolean);
  const updated = current.includes(value) ? current.filter((v) => v !== value) : [...current, value];
  if (updated.length) next.set(code, updated.join(","));
  else next.delete(code);
  next.delete("page");
  const qs = next.toString();
  return qs ? `${pathname}?${qs}` : pathname;
}

/** Filtros facetados con enlaces normales: funcionan sin JavaScript y se pueden compartir. */
export function Filters({ facets, pathname, params }: { facets: Facet[]; pathname: string; params: SearchParams }) {
  if (facets.length === 0) return null;

  const active = facets.flatMap((f) => f.values.filter((v) => v.selected).map((v) => ({ facet: f, value: v })));

  return (
    <div className="space-y-8">
      {active.length > 0 && (
        <div>
          <p className="label">Filtros activos</p>
          <ul className="mt-3 flex flex-wrap gap-2">
            {active.map(({ facet, value }) => (
              <li key={`${facet.code}-${value.value}`}>
                <Link
                  href={toggleHref(pathname, params, facet.code, value.value)}
                  className="inline-flex items-center gap-1 rounded-full bg-fg px-3 py-1 text-xs text-bg"
                >
                  {value.label}
                  <span aria-hidden>×</span>
                  <span className="sr-only">(quitar filtro)</span>
                </Link>
              </li>
            ))}
          </ul>
        </div>
      )}
      {facets.map((facet) => (
        <fieldset key={facet.code}>
          <legend className="label">{facet.name}</legend>
          <ul className={`mt-3 ${facet.display === "swatch" ? "flex flex-wrap gap-2" : "space-y-1"}`}>
            {facet.values.map((v) => {
              const href = toggleHref(pathname, params, facet.code, v.value);
              if (facet.display === "swatch" && v.color_hex) {
                return (
                  <li key={v.value}>
                    <Link
                      href={href}
                      title={`${v.label} (${v.count})`}
                      className={`flex h-9 w-9 items-center justify-center rounded-full border ${v.selected ? "border-fg ring-2 ring-fg ring-offset-2 ring-offset-bg" : "border-border"}`}
                    >
                      <span className="h-6 w-6 rounded-full border border-black/10" style={{ background: v.color_hex }} />
                      <span className="sr-only">
                        {v.label}, {v.count} productos{v.selected ? ", seleccionado" : ""}
                      </span>
                    </Link>
                  </li>
                );
              }
              return (
                <li key={v.value}>
                  <Link href={href} className="flex items-center gap-3 py-1 text-sm hover:text-fg">
                    <span
                      aria-hidden
                      className={`flex h-4 w-4 items-center justify-center rounded border ${v.selected ? "border-fg bg-fg text-bg" : "border-border"}`}
                    >
                      {v.selected && "✓"}
                    </span>
                    <span>
                      {v.label}
                      {v.selected && <span className="sr-only"> (seleccionado)</span>}
                    </span>
                    <span className="ml-auto text-muted">{v.count}</span>
                  </Link>
                </li>
              );
            })}
          </ul>
        </fieldset>
      ))}
      {active.length > 0 && (
        <Link href={pathname + (params.q ? `?q=${encodeURIComponent(String(params.q))}` : "")} className="text-sm underline underline-offset-4">
          Limpiar filtros
        </Link>
      )}
    </div>
  );
}
