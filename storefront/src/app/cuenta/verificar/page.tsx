import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";
import { VerifyEmail } from "@/components/account/RecoveryForms";

export const metadata: Metadata = { title: "Confirmar correo", robots: { index: false, follow: false } };

export default async function VerifyPage({ searchParams }: { searchParams: Promise<{ token?: string }> }) {
  const { token } = await searchParams;

  return (
    <Container className="max-w-xl py-16">
      <h1 className="mb-8 font-display text-5xl">Confirmar correo</h1>
      {token ? <VerifyEmail token={token} /> : <p className="text-muted">El enlace está incompleto. Pide uno nuevo desde Mi cuenta.</p>}
    </Container>
  );
}
