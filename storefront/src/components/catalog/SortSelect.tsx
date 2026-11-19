"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";

const OPTIONS = [
  ["relevance", "Destacados"],
  ["newest", "Más nuevos"],
  ["price_asc", "Menor precio"],
  ["price_desc", "Mayor precio"],
  ["name", "Nombre (A-Z)"],
] as const;

export function SortSelect() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  return (
    <label className="flex items-center gap-2 text-sm">
      <span className="text-muted">Ordenar por</span>
      <select
        value={params.get("sort") ?? "relevance"}
        onChange={(e) => {
          const next = new URLSearchParams(params);
          if (e.target.value === "relevance") next.delete("sort");
          else next.set("sort", e.target.value);
          next.delete("page");
          router.push(`${pathname}${next.size ? `?${next}` : ""}`, { scroll: false });
        }}
        className="h-10 rounded-full border border-border bg-surface px-3 text-sm"
      >
        {OPTIONS.map(([value, label]) => (
          <option key={value} value={value}>
            {label}
          </option>
        ))}
      </select>
    </label>
  );
}
