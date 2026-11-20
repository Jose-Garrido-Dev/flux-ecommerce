import type { Metadata } from "next";
import Link from "next/link";
import { hasSession, storeData } from "@/lib/store-api";
import type { CustomerAddress, CustomerProfile, GeoRegion, OrderSummary } from "@/lib/types";
import { formatMoney } from "@/lib/format";
import { Container } from "@/components/ui/Container";
import { AuthPanel } from "@/components/account/AuthPanel";
import { ProfileForm, PasswordForm, LogoutButton, VerifyBanner } from "@/components/account/AccountForms";
import { AddressBook } from "@/components/account/AddressBook";
import { formatDate } from "@/components/order/OrderView";

export const metadata: Metadata = { title: "Mi cuenta", robots: { index: false, follow: false } };

export default async function AccountPage({ searchParams }: { searchParams: Promise<{ volver?: string }> }) {
  const { volver } = await searchParams;
  const profile = (await hasSession()) ? await storeData<CustomerProfile>("/me") : null;

  if (!profile) {
    return (
      <Container className="py-12">
        <h1 className="font-display text-6xl sm:text-7xl">Mi cuenta</h1>
        <AuthPanel redirectTo={safeRedirect(volver)} />
      </Container>
    );
  }

  const [addresses, orders, regions] = await Promise.all([
    storeData<CustomerAddress[]>("/me/addresses"),
    storeData<OrderSummary[]>("/me/orders"),
    storeData<GeoRegion[]>("/geo/regions"),
  ]);

  return (
    <Container className="py-12">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="label">Mi cuenta</p>
          <h1 className="mt-3 font-display text-5xl sm:text-7xl">Hola, {profile.first_name}</h1>
        </div>
        <LogoutButton />
      </div>
      {!profile.email_verified && (
        <div className="mt-8">
          <VerifyBanner email={profile.email} />
        </div>
      )}

      <div className="mt-12 grid gap-14 lg:grid-cols-[1.3fr_1fr]">
        <section aria-labelledby="pedidos-titulo">
          <h2 id="pedidos-titulo" className="font-display text-4xl">
            Mis pedidos
          </h2>
          {!orders || orders.length === 0 ? (
            <p className="mt-6 text-muted">
              Aún no tienes pedidos.{" "}
              <Link href="/catalogo" className="text-fg underline underline-offset-4">
                Ver el catálogo
              </Link>
            </p>
          ) : (
            <ul className="mt-6 divide-y divide-border border-y border-border">
              {orders.map((o) => (
                <li key={o.uuid}>
                  <Link href={`/cuenta/pedidos/${o.uuid}`} className="flex flex-wrap items-center justify-between gap-3 py-4 hover:bg-subtle/60">
                    <span>
                      <span className="font-semibold">Pedido {o.number}</span>
                      <span className="block text-sm text-muted">{formatDate(o.placed_at)}</span>
                    </span>
                    <span className="text-right text-sm">
                      <span className="block font-semibold tabular-nums">{formatMoney(o.grand_total)}</span>
                      <span className="text-muted">
                        {o.status_label} · Pago {o.payment_status_label.toLowerCase()}
                      </span>
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          )}

          <h2 className="mt-16 font-display text-4xl">Mis direcciones</h2>
          <AddressBook addresses={addresses ?? []} regions={regions ?? []} />
        </section>

        <div className="space-y-14">
          <section aria-labelledby="datos-titulo">
            <h2 id="datos-titulo" className="font-display text-4xl">
              Mis datos
            </h2>
            <ProfileForm profile={profile} />
          </section>
          <section aria-labelledby="clave-titulo">
            <h2 id="clave-titulo" className="font-display text-4xl">
              Contraseña
            </h2>
            <PasswordForm />
          </section>
        </div>
      </div>
    </Container>
  );
}

/** Solo rutas internas de la tienda (evita redirecciones a otros sitios). */
function safeRedirect(path?: string): string {
  return path && path.startsWith("/") && !path.startsWith("//") && !path.startsWith("/\\") ? path : "/cuenta";
}
