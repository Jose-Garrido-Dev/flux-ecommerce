/** Mensajes configurados en el panel (Configuración → Datos del negocio → Barra de anuncios). */
export function AnnouncementBar({ messages }: { messages: string[] }) {
  if (messages.length === 0) return null;
  // Se repite para que la cinta sea continua
  const loop = Array.from({ length: Math.max(2, Math.ceil(8 / messages.length)) }, () => messages).flat();

  return (
    <aside aria-label="Anuncios" className="marquee-wrap relative overflow-hidden bg-ink text-ink-fg">
      <p className="sr-only">{messages.join(". ")}</p>
      <div aria-hidden className="marquee py-2.5 text-sm font-medium" style={{ "--marquee-duration": `${loop.length * 5}s` } as React.CSSProperties}>
        {[0, 1].map((half) => (
          <div key={half} className="flex shrink-0">
            {loop.map((m, i) => (
              <span key={`${half}-${i}`} className="flex items-center gap-8 px-4">
                {m}
                <span className="h-1.5 w-1.5 rounded-full bg-hot" />
              </span>
            ))}
          </div>
        ))}
      </div>
    </aside>
  );
}
