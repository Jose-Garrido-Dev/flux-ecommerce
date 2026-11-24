import type { CmsPage } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { BlockRenderer } from "./BlockRenderer";

/** Página institucional o landing: si no empieza con una portada, el título va como encabezado. */
export function CmsPageView({ page }: { page: CmsPage }) {
  const startsWithHero = page.blocks[0]?.type === "hero";
  const hasHero = page.blocks.some((b) => b.type === "hero");

  return (
    <>
      {!hasHero && (
        <Container className="pb-4 pt-14 sm:pt-20">
          <h1 className="mx-auto max-w-3xl font-display text-6xl sm:text-8xl">{page.title}</h1>
        </Container>
      )}
      <div className={startsWithHero ? "" : "pt-8"}>
        <BlockRenderer blocks={page.blocks} />
      </div>
    </>
  );
}
