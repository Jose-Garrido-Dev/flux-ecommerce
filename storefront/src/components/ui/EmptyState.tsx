export function EmptyState({ title, children }: { title: string; children?: React.ReactNode }) {
  return (
    <div role="status" className="rounded-[var(--radius)] border border-dashed border-border px-6 py-16 text-center">
      <p className="font-display text-2xl">{title}</p>
      {children && <div className="mx-auto mt-3 max-w-md text-muted">{children}</div>}
    </div>
  );
}
