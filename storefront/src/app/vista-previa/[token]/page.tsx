import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { connection } from "next/server";
import { getPreview } from "@/lib/content";
import { CmsPageView } from "@/components/cms/CmsPageView";
import { BlockRenderer } from "@/components/cms/BlockRenderer";

type Props = { params: Promise<{ token: string }> };

export const metadata: Metadata = { title: "Vista previa", robots: { index: false, follow: false } };

/** Vista previa de un borrador desde el panel. El enlace es firmado y vence en 2 horas. */
export default async function PreviewPage({ params }: Props) {
  await connection();
  const page = await getPreview((await params).token);
  if (!page) notFound();

  return (
    <>
      <div role="status" className="sticky top-[4.25rem] z-30 bg-fg px-4 py-2.5 text-center text-sm text-bg">
        Vista previa de «{page.title}», versión {page.version}. Los clientes aún no ven estos cambios.
      </div>
      {page.type === "home" ? <BlockRenderer blocks={page.blocks} /> : <CmsPageView page={page} />}
    </>
  );
}
