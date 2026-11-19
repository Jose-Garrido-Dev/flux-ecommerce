import { Container } from "@/components/ui/Container";
import { ButtonLink } from "@/components/ui/Button";

export default function NotFound() {
  return (
    <Container className="py-32">
      <p className="label">Error 404</p>
      <h1 className="mt-4 font-display text-4xl">No encontramos esta página</h1>
      <p className="mt-4 max-w-md text-muted">Puede que el producto ya no esté disponible o que el enlace tenga un error.</p>
      <div className="mt-8">
        <ButtonLink href="/catalogo">Ir al catálogo</ButtonLink>
      </div>
    </Container>
  );
}
