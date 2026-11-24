/** Datos estructurados (schema.org). Se escapa "<" para que el contenido no pueda cerrar la etiqueta. */
export function JsonLd({ data }: { data: unknown }) {
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, "\\u003c") }} />;
}
