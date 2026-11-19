"use client";

import { Container } from "@/components/ui/Container";

export default function Error({ reset }: { error: Error; reset: () => void }) {
  return (
    <Container className="py-32">
      <h1 className="font-display text-4xl">Algo salió mal</h1>
      <p className="mt-4 max-w-md text-muted">No pudimos cargar esta página. Intenta nuevamente en unos segundos.</p>
      <button type="button" onClick={reset} className="mt-8 rounded-full bg-fg px-6 py-3 text-sm text-bg">
        Reintentar
      </button>
    </Container>
  );
}
