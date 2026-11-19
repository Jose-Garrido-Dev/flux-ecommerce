import Image from "next/image";
import Link from "next/link";
import type { CategoryNode } from "@/lib/types";
import { mediaUrl } from "@/lib/format";
import { Container } from "@/components/ui/Container";
import { SearchForm } from "./SearchForm";

const iconBtn = "flex h-10 w-10 items-center justify-center rounded-full transition-colors hover:bg-subtle";

/**
 * Encabezado sin JavaScript: el mega menú de escritorio se abre con hover/foco
 * (CSS) y el menú móvil es un <details>, accesible por teclado y lector de pantalla.
 */
export function Header({ storeName, menu }: { storeName: string; menu: CategoryNode[] }) {
  return (
    <header className="sticky top-0 z-40 border-b border-border/70 bg-bg/85 backdrop-blur-xl supports-[backdrop-filter]:bg-bg/70">
      <a
        href="#contenido"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-2 focus:z-50 focus:rounded focus:bg-fg focus:px-3 focus:py-2 focus:text-bg"
      >
        Saltar al contenido
      </a>
      <Container className="flex h-[4.25rem] items-center gap-4 lg:gap-8">
        <details className="group/menu lg:hidden">
          <summary className={`${iconBtn} cursor-pointer list-none [&::-webkit-details-marker]:hidden`}>
            <span className="sr-only">Abrir menú</span>
            <svg aria-hidden width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className="group-open/menu:hidden">
              <path d="M4 8h16M4 16h16" />
            </svg>
            <svg aria-hidden width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className="hidden group-open/menu:block">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </summary>
          <nav aria-label="Categorías" className="fixed inset-x-0 top-[4.25rem] h-[calc(100dvh-4.25rem)] overflow-y-auto bg-bg px-5 pb-10 pt-6">
            <SearchForm id="q-movil" className="mb-8" />
            <ul className="divide-y divide-border">
              <li>
                <Link href="/catalogo" className="flex items-center justify-between py-4 font-display text-3xl">
                  Todo
                </Link>
              </li>
              {menu.map((c) => (
                <li key={c.slug} className="py-4">
                  <Link href={`/categoria/${c.slug}`} className="flex items-center justify-between font-display text-3xl">
                    {c.name}
                  </Link>
                  {c.children.length > 0 && (
                    <ul className="mt-3 flex flex-wrap gap-2">
                      {c.children.map((s) => (
                        <li key={s.slug}>
                          <Link href={`/categoria/${s.slug}`} className="inline-block rounded-full border border-border px-3 py-1.5 text-sm">
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

        <Link href="/" className="font-display text-[1.6rem] leading-none tracking-tight">
          {storeName}
        </Link>

        <nav aria-label="Categorías principales" className="hidden h-full flex-1 lg:block">
          <ul className="flex h-full items-center gap-1">
            <li>
              <Link href="/catalogo" className="rounded-full px-4 py-2 text-sm font-medium transition-colors hover:bg-subtle">
                Catálogo
              </Link>
            </li>
            {menu.map((c) => (
              <li key={c.slug} className="group/item flex h-full items-center">
                <Link
                  href={`/categoria/${c.slug}`}
                  className="flex items-center gap-1 rounded-full px-4 py-2 text-sm font-medium transition-colors hover:bg-subtle"
                >
                  {c.name}
                  {c.children.length > 0 && (
                    <svg aria-hidden width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="transition-transform group-hover/item:rotate-180">
                      <path d="M6 9l6 6 6-6" />
                    </svg>
                  )}
                </Link>
                {c.children.length > 0 && (
                  <div className="invisible absolute inset-x-0 top-full -translate-y-2 border-b border-border bg-bg opacity-0 shadow-[0_30px_60px_-30px_rgb(0_0_0/0.25)] transition-all duration-[var(--motion)] ease-[var(--ease-out)] group-focus-within/item:visible group-focus-within/item:translate-y-0 group-focus-within/item:opacity-100 group-hover/item:visible group-hover/item:translate-y-0 group-hover/item:opacity-100">
                    <Container className="grid grid-cols-[1fr_1fr_22rem] gap-10 py-10">
                      <div>
                        <p className="label">{c.name}</p>
                        <ul className="mt-5 space-y-3">
                          {c.children.map((s) => (
                            <li key={s.slug}>
                              <Link href={`/categoria/${s.slug}`} className="font-display text-2xl transition-colors hover:text-accent">
                                {s.name}
                              </Link>
                            </li>
                          ))}
                        </ul>
                      </div>
                      <div>
                        <p className="label">Explorar</p>
                        <ul className="mt-5 space-y-3 text-sm">
                          <li>
                            <Link href={`/categoria/${c.slug}`} className="underline-offset-4 hover:underline">
                              Ver todo {c.name}
                            </Link>
                          </li>
                          <li>
                            <Link href={`/categoria/${c.slug}?sort=newest`} className="underline-offset-4 hover:underline">
                              Lo más nuevo
                            </Link>
                          </li>
                          <li>
                            <Link href={`/categoria/${c.slug}?on_sale=1`} className="underline-offset-4 hover:underline">
                              En oferta
                            </Link>
                          </li>
                        </ul>
                      </div>
                      {c.image && (
                        <Link href={`/categoria/${c.slug}`} className="group/img relative block aspect-[4/3] overflow-hidden rounded-[var(--radius)] bg-subtle">
                          <Image src={mediaUrl(c.image.path)} alt="" fill sizes="22rem" className="object-cover transition-transform duration-[1.2s] group-hover/img:scale-105" />
                          <span className="absolute bottom-3 left-3 rounded-full bg-surface/90 px-3 py-1.5 text-xs font-medium backdrop-blur">
                            Comprar {c.name}
                          </span>
                        </Link>
                      )}
                    </Container>
                  </div>
                )}
              </li>
            ))}
          </ul>
        </nav>

        <div className="ml-auto hidden w-64 xl:block">
          <SearchForm id="q-escritorio" />
        </div>
        <Link href="/buscar" className={`${iconBtn} ml-auto xl:hidden`}>
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
