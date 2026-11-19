import { apiGet, type PublicStore } from "@/lib/api";

// La página se regenera cuando Laravel avisa cambios con la etiqueta "store".
export const revalidate = 300;

async function getStore(): Promise<PublicStore | null> {
  try {
    return await apiGet<PublicStore>("/store", { tags: ["store"] });
  } catch {
    return null;
  }
}

export default async function Home() {
  const store = await getStore();

  return (
    <main className="mx-auto flex min-h-screen max-w-5xl flex-col justify-center px-6 py-24">
      {store ? (
        <section className="reveal">
          <p className="text-sm uppercase tracking-[0.3em] text-muted">Muy pronto</p>
          <h1 className="mt-4 font-display text-5xl leading-tight sm:text-7xl">{store.name}</h1>
          {store.tagline && <p className="mt-6 max-w-xl text-lg text-muted">{store.tagline}</p>}
          {(store.contact.email || store.contact.phone) && (
            <p className="mt-10 text-sm text-muted">
              Contacto:{" "}
              {store.contact.email && (
                <a className="underline underline-offset-4" href={`mailto:${store.contact.email}`}>
                  {store.contact.email}
                </a>
              )}
              {store.contact.email && store.contact.phone && " · "}
              {store.contact.phone}
            </p>
          )}
        </section>
      ) : (
        <section role="status" className="reveal">
          <h1 className="font-display text-4xl">Tienda en mantenimiento</h1>
          <p className="mt-4 text-muted">
            No pudimos conectar con el servidor de la tienda. Intenta nuevamente en unos minutos.
          </p>
        </section>
      )}
    </main>
  );
}
