import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";
import { NewsletterAction } from "@/components/marketing/NewsletterAction";

export const metadata: Metadata = { title: "Darse de baja", robots: { index: false, follow: false } };

type Props = { searchParams: Promise<{ token?: string }> };

export default async function UnsubscribePage({ searchParams }: Props) {
  const { token } = await searchParams;
  return (
    <Container className="max-w-2xl py-20 text-center">
      <h1 className="font-display text-5xl sm:text-6xl">Darse de baja</h1>
      {token && token.length <= 400 ? (
        <>
          <p className="mt-6 text-muted">Dejarás de recibir novedades y ofertas por correo. Los correos de tus pedidos (confirmación, despacho) se siguen enviando.</p>
          <NewsletterAction action="unsubscribe" token={token} />
        </>
      ) : (
        <p className="mt-6 text-sale">El enlace está incompleto. Ábrelo directamente desde el correo que recibiste.</p>
      )}
    </Container>
  );
}
