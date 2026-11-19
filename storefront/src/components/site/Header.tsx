import Link from "next/link";
import type { CategoryNode } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { SearchForm } from "./SearchForm";

/**
 * Encabezado sin JavaScript: el menú de escritorio se abre con hover/foco (CSS)
 * y el menú móvil es un <details>, accesible por teclado y lector de pantalla.
 */
export function Header({ storeName, menu }: { storeName: string; menu: CategoryNode[] }) {
  return (
    <header className="sticky top-0 z-40 border-b border-border bg-bg/90 backdrop-blur supports-[backdrop-filter]:bg-bg/75">
      <a
        href="#contenido"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-2 focus:z-50 focus:rounded focus:bg-fg focus:px-3 focus:py-2 focus:text-bg"
      >
        Saltar al contenido
      </a>
      <Container className="flex h-16 items-center gap-6">
        <details className="group/menu relative lg:hidden">
          <summary className="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-full hover:bg-subtle [&::-webkit-details-marker]:hidden">
            <span className="sr-only">Abrir menú</span>
            <svg aria-hidden width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6">
              <path d="M4 7h16M4 12h16M4 17h16" />
            </svg>
          </summary>
          <nav
            aria-label="Categorías"
            className="fixed inset-x-0 top-16 max-h-[calc(100dvh-4rem)] overflow-y-auto border-b border-border bg-bg px-4 pb-8 pt-4"
          >
            <SearchForm id="q-movil" className="mb-6" />
            <ul className="space-y-4">
              <li>
                <Link href="/catalogo" className="font-display text-2xl">
                  Todo el catálogo
                </Link>
              </li>
              {menu.map((c) => (
                <li key={c.slug}>
                  <Link href={`/categoria/${c.slug}`} className="font-display text-2xl">
                    {c.name}
                  </Link>
                  {c.children.length > 0 && (
                    <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-muted">
                      {c.children.map((s) => (
                        <li key={s.slug}>
                          <Link href={`/categoria/${s.slug}`} className="hover:text-fg">
                            {s.name}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </li>
              ))}
            </ul>
          </nav>
        </details>

        <Link href="/" className="font-display text-xl tracking-tight sm:text-2xl">
          {storeName}
        </Link>

        <nav aria-label="Categorías principales" className="hidden flex-1 lg:block">
          <ul className="flex items-center gap-1">
            <li>
              <Link href="/catalogo" className="rounded-full px-3 py-2 text-sm hover:bg-subtle">
                Catálogo
              </Link>
            </li>
            {menu.map((c) => (
              <li key={c.slug} className="group/item relative">
                <Link href={`/categoria/${c.slug}`} className="block rounded-full px-3 py-2 text-sm hover:bg-subtle">
                  {c.name}
                </Link>
                {c.children.length > 0 && (
                  <div className="invisible absolute left-0 top-full pt-2 opacity-0 transition-opacity duration-[var(--motion-fast)] group-focus-within/item:visible group-focus-within/item:opacity-100 group-hover/item:visible group-hover/item:opacity-100">
                    <ul className="min-w-56 rounded-[var(--radius)] border border-border bg-surface p-2 shadow-[var(--shadow-card)]">
                      {c.children.map((s) => (
                        <li key={s.slug}>
                          <Link href={`/categoria/${s.slug}`} className="block rounded-[var(--radius-sm)] px-3 py-2 text-sm hover:bg-subtle">
                            {s.name}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </li>
            ))}
          </ul>
        </nav>

        <div className="ml-auto hidden w-72 lg:block">
          <SearchForm id="q-escritorio" />
        </div>
        <Link href="/buscar" className="ml-auto flex h-10 w-10 items-center justify-center rounded-full hover:bg-subtle lg:hidden">
          <span className="sr-only">Buscar</span>
          <svg aria-hidden width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
          </svg>
        </Link>
      </Container>
    </header>
  );
}
