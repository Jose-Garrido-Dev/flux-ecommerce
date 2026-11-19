import Link from "next/link";
import type { CategoryNode, PublicStore } from "@/lib/types";
import { Container } from "@/components/ui/Container";

export function Footer({ store, menu }: { store: PublicStore | null; menu: CategoryNode[] }) {
  const year = new Date().getFullYear();
  return (
    <footer className="mt-24 border-t border-border bg-subtle">
      <Container className="grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div className="lg:col-span-2">
          <p className="font-display text-2xl">{store?.name ?? "Tienda"}</p>
          {store?.tagline && <p className="mt-3 max-w-sm text-muted">{store.tagline}</p>}
        </div>
        {menu.length > 0 && (
          <nav aria-label="Categorías">
            <p className="label">Comprar</p>
            <ul className="mt-4 space-y-2 text-sm">
              {menu.map((c) => (
                <li key={c.slug}>
                  <Link href={`/categoria/${c.slug}`} className="hover:underline underline-offset-4">
                    {c.name}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        )}
        {(store?.contact.email || store?.contact.phone) && (
          <div>
            <p className="label">Contacto</p>
            <ul className="mt-4 space-y-2 text-sm">
              {store.contact.email && (
                <li>
                  <a href={`mailto:${store.contact.email}`} className="hover:underline underline-offset-4">
                    {store.contact.email}
                  </a>
                </li>
              )}
              {store.contact.phone && <li>{store.contact.phone}</li>}
            </ul>
          </div>
        )}
      </Container>
      <Container className="border-t border-border py-6 text-xs text-muted">
        © {year} {store?.name ?? "Tienda"}. Precios en pesos chilenos con IVA incluido.
      </Container>
    </footer>
  );
}
