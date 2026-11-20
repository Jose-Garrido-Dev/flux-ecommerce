import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";
import { ButtonLink } from "@/components/ui/Button";

export const metadata: Metadata = { title: "Resultado del pago", robots: { index: false, follow: false } };

/** Cuando el regreso desde la pasarela no corresponde a ningún pedido conocido. */
export default async function PaymentResultPage({ searchParams }: { searchParams: Promise<{ estado?: string }> }) {
  const { estado } = await searchParams;

  return (
    <Container className="max-w-2xl py-24">
      <h1 className="font-display text-5xl">{estado === "pendiente" ? "Estamos revisando tu pago" : "No encontramos este pago"}</h1>
      <p className="mt-4 text-muted">
        {estado === "pendiente"
          ? "Todavía no tenemos la confirmación. Si pagaste, tu pedido se actualiza solo en unos minutos y te llega un correo."
          : "Si pagaste, revisa tu correo: ahí está el enlace de tu pedido. Si tienes dudas, escríbenos."}
      </p>
      <ButtonLink href="/cuenta" className="mt-8">
        Ir a Mi cuenta
      </ButtonLink>
    </Container>
  );
}
