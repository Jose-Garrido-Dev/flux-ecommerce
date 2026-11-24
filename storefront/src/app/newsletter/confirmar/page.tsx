import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";
import { NewsletterAction } from "@/components/marketing/NewsletterAction";

export const metadata: Metadata = { title: "Confirmar suscripción", robots: { index: false, follow: false } };

type Props = { searchParams: Promise<{ token?: string }> };

export default async function ConfirmPage({ searchParams }: Props) {
  const { token } = await searchParams;
  return (
    <Container className="max-w-2xl py-20 text-center">
      <h1 className="font-display text-5xl sm:text-6xl">Confirma tu suscripción</h1>
      {token && token.length <= 100 ? (
        <>
          <p className="mt-6 text-muted">Recibirás novedades y ofertas de la tienda. Puedes darte de baja cuando quieras desde cualquier correo.</p>
          <NewsletterAction action="confirm" token={token} />
        </>
      ) : (
        <p className="mt-6 text-sale">El enlace está incompleto. Ábrelo directamente desde el correo que te enviamos.</p>
      )}
    </Container>
  );
}
