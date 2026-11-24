import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getPage } from "@/lib/content";
import { CmsPageView } from "@/components/cms/CmsPageView";

type Props = { params: Promise<{ pagina: string }> };

/** Páginas armadas en el panel: institucionales (Nosotros, Términos) y landings de campaña. */
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const page = await getPage((await params).pagina);
  if (!page) return {};
  return {
    title: page.seo.title,
    description: page.seo.description ?? undefined,
    alternates: { canonical: `/${page.slug}` },
    openGraph: { title: page.seo.title, description: page.seo.description ?? undefined },
  };
}

export default async function CmsPageRoute({ params }: Props) {
  const page = await getPage((await params).pagina);
  if (!page) notFound();
  return <CmsPageView page={page} />;
}
