import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";
import { ForgotForm } from "@/components/account/RecoveryForms";

export const metadata: Metadata = { title: "Recuperar contraseña", robots: { index: false } };

export default function ForgotPage() {
  return (
    <Container className="max-w-xl py-16">
      <h1 className="font-display text-5xl">Recuperar contraseña</h1>
      <p className="mb-8 mt-4 text-muted">Escribe el correo de tu cuenta y te enviaremos un enlace para elegir una nueva contraseña.</p>
      <ForgotForm />
    </Container>
  );
}
