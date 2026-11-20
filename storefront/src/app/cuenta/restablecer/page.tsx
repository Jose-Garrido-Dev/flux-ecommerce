import type { Metadata } from "next";
import Link from "next/link";
import { Container } from "@/components/ui/Container";
import { ResetForm } from "@/components/account/RecoveryForms";

export const metadata: Metadata = { title: "Nueva contraseña", robots: { index: false, follow: false } };

export default async function ResetPage({ searchParams }: { searchParams: Promise<{ token?: string; email?: string }> }) {
  const { token, email } = await searchParams;

  return (
    <Container className="max-w-xl py-16">
      <h1 className="mb-8 font-display text-5xl">Elige una nueva contraseña</h1>
      {token && email ? (
        <ResetForm token={token} email={email} />
      ) : (
        <p className="text-muted">
          El enlace está incompleto.{" "}
          <Link href="/cuenta/recuperar" className="text-fg underline">
            Pide uno nuevo
          </Link>
          .
        </p>
      )}
    </Container>
  );
}
