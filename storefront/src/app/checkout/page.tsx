import type { Metadata } from "next";
import Link from "next/link";
import { storeData, hasSession } from "@/lib/store-api";
import type { Cart, CustomerAddress, CustomerProfile, GeoRegion } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { ButtonLink } from "@/components/ui/Button";
import { CheckoutForm } from "@/components/checkout/CheckoutForm";

export const metadata: Metadata = { title: "Finalizar compra", robots: { index: false, follow: false } };

export default async function CheckoutPage() {
  const logged = await hasSession();
  const [cart, regions, profile, addresses] = await Promise.all([
    storeData<Cart>("/cart"),
    storeData<GeoRegion[]>("/geo/regions"),
    logged ? storeData<CustomerProfile>("/me") : null,
    logged ? storeData<CustomerAddress[]>("/me/addresses") : null,
  ]);

  if (!cart || cart.items.length === 0) {
    return (
      <Container className="py-24 text-center">
        <h1 className="font-display text-5xl">Tu carrito está vacío</h1>
        <p className="mt-4 text-muted">Agrega productos para continuar con la compra.</p>
        <ButtonLink href="/catalogo" className="mt-8">
          Ver el catálogo
        </ButtonLink>
      </Container>
    );
  }

  return (
    <Container className="py-12">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <h1 className="font-display text-5xl sm:text-7xl">Finalizar compra</h1>
        {!profile && (
          <p className="text-sm text-muted">
            ¿Tienes cuenta?{" "}
            <Link href="/cuenta?volver=/checkout" className="font-medium text-fg underline underline-offset-4">
              Ingresa
            </Link>{" "}
            para usar tus direcciones guardadas. También puedes comprar como invitado.
          </p>
        )}
      </div>
      <CheckoutForm cart={cart} regions={regions ?? []} profile={profile} addresses={addresses ?? []} />
    </Container>
  );
}
