import { Suspense } from "react";
import type { ProductList } from "@/lib/types";
import type { SearchParams } from "@/lib/catalog";
import { ProductGrid } from "./ProductCard";
import { Filters } from "./Filters";
import { Pagination } from "./Pagination";
import { SortSelect } from "./SortSelect";
import { EmptyState } from "@/components/ui/EmptyState";

/** Listado con filtros, orden y paginación. Usado por catálogo, categoría, marca y búsqueda. */
export function CatalogView({ list, pathname, params }: { list: ProductList; pathname: string; params: SearchParams }) {
  const { total } = list.meta;
  return (
    <div className="grid gap-10 lg:grid-cols-[15rem_1fr]">
      <aside aria-label="Filtros" className="lg:sticky lg:top-24 lg:self-start">
        {list.facets.length > 0 && (
          <details className="group lg:hidden" open={false}>
            <summary className="flex h-11 cursor-pointer list-none items-center justify-between rounded-full border border-border px-5 text-sm [&::-webkit-details-marker]:hidden">
              Filtrar
              <span aria-hidden className="transition-transform group-open:rotate-45">+</span>
            </summary>
            <div className="pt-6">
              <Filters facets={list.facets} pathname={pathname} params={params} />
            </div>
          </details>
        )}
        <div className="hidden lg:block">
          <Filters facets={list.facets} pathname={pathname} params={params} />
        </div>
      </aside>
      <section aria-label="Productos">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
          <p className="text-sm text-muted" aria-live="polite">
            {total === 1 ? "1 producto" : `${total.toLocaleString("es-CL")} productos`}
          </p>
          <Suspense>
            <SortSelect />
          </Suspense>
        </div>
        {list.data.length > 0 ? (
          <ProductGrid products={list.data} priorityCount={4} headingLevel={2} />
        ) : (
          <EmptyState title="No encontramos productos">
            Prueba quitando algún filtro o buscando con otras palabras.
          </EmptyState>
        )}
        <Pagination page={list.meta.page} lastPage={list.meta.last_page} pathname={pathname} params={params} />
      </section>
    </div>
  );
}
