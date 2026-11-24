import Link from "next/link";
import type { CategoryNode, MenuLink, PublicStore } from "@/lib/types";
import { CmsLink } from "@/components/cms/CmsLink";
import { Container } from "@/components/ui/Container";
import { NewsletterForm } from "@/components/marketing/NewsletterForm";

const SOCIAL_LABEL = { instagram: "Instagram", facebook: "Facebook", tiktok: "TikTok" } as const;

/** Pie: categorías, enlaces del menú del pie armado en el panel (Contenido → Menús) y contacto. */
export function Footer({ store, menu, links = [] }: { store: PublicStore | null; menu: CategoryNode[]; links?: MenuLink[] }) {
  const year = new Date().getFullYear();
  const name = store?.name ?? "Tienda";
  const social = Object.entries(store?.social ?? {}) as [keyof typeof SOCIAL_LABEL, string][];

  return (
    <footer className="relative mt-32 overflow-hidden bg-ink text-ink-fg">
      <Container className="relative grid gap-12 pb-12 pt-20 md:grid-cols-2 lg:grid-cols-[1.6fr_repeat(3,1fr)]">
        <div>
          <p className="font-display text-5xl leading-none sm:text-6xl">{name}</p>
          {store?.tagline && <p className="mt-5 max-w-sm text-ink-muted">{store.tagline}</p>}
          <div className="mt-8">
            <NewsletterForm />
          </div>
        </div>
        {menu.length > 0 && (
          <nav aria-label="Categorías">
            <p className="text-sm font-semibold text-ink-muted">Comprar</p>
            <ul className="mt-5 space-y-3">
              <li>
                <Link href="/catalogo" className="hover:underline underline-offset-4">Todo el catálogo</Link>
              </li>
              {menu.map((c) => (
                <li key={c.slug}>
                  <Link href={`/categoria/${c.slug}`} className="hover:underline underline-offset-4">{c.name}</Link>
                </li>
              ))}
            </ul>
          </nav>
        )}
        {(
          <nav aria-label="Información">
            <p className="text-sm font-semibold text-ink-muted">Información</p>
            <ul className="mt-5 space-y-3">
              <li>
                <Link href="/blog" className="hover:underline underline-offset-4">Blog</Link>
              </li>
              {links.map((l, i) => (
                <li key={i}>
                  <CmsLink href={l.href} className="hover:underline underline-offset-4">{l.label}</CmsLink>
                </li>
              ))}
            </ul>
          </nav>
        )}
        {(store?.contact.email || store?.contact.phone || social.length > 0) && (
          <div>
            <p className="text-sm font-semibold text-ink-muted">Contacto</p>
            <ul className="mt-5 space-y-3">
              {store?.contact.email && (
                <li><a href={`mailto:${store.contact.email}`} className="hover:underline underline-offset-4">{store.contact.email}</a></li>
              )}
              {store?.contact.phone && <li>{store.contact.phone}</li>}
              {social.map(([k, url]) => (
                <li key={k}>
                  <a href={url} rel="noopener noreferrer" target="_blank" className="hover:underline underline-offset-4">{SOCIAL_LABEL[k]}</a>
                </li>
              ))}
            </ul>
          </div>
        )}
      </Container>
      <Container className="relative mt-4 flex flex-wrap justify-between gap-4 border-t border-white/10 py-6 text-xs text-ink-muted">
        <span>© {year} {name}</span>
        <span>Precios en pesos chilenos con IVA incluido</span>
      </Container>
    </footer>
  );
}
