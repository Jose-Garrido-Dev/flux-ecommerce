/** Campo de formulario con etiqueta, ayuda y error accesibles. */
export function Field({
  id,
  label,
  error,
  hint,
  className = "",
  children,
}: {
  id: string;
  label: string;
  error?: string;
  hint?: string;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <div className={`field ${className}`}>
      <label htmlFor={id}>{label}</label>
      {children}
      {hint && !error && (
        <p id={`${id}-ayuda`} className="field-hint">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} className="field-error" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}

/** Atributos aria para el input de un Field. */
export function fieldAria(id: string, error?: string, hint?: string) {
  return {
    id,
    "aria-invalid": error ? (true as const) : undefined,
    "aria-describedby": error ? `${id}-error` : hint ? `${id}-ayuda` : undefined,
  };
}

export function Alert({ children, tone = "error" }: { children: React.ReactNode; tone?: "error" | "ok" | "info" }) {
  const cls =
    tone === "ok" ? "border-ok/40 bg-ok/10 text-ok" : tone === "info" ? "border-border bg-subtle text-fg" : "border-sale/40 bg-sale/10 text-sale";
  return (
    <div role={tone === "error" ? "alert" : "status"} className={`rounded-[var(--radius)] border px-4 py-3 text-sm ${cls}`}>
      {children}
    </div>
  );
}
