export function Badge({ children, tone = "neutral" }: { children: React.ReactNode; tone?: "neutral" | "sale" }) {
  const tones = {
    neutral: "bg-surface text-fg border border-border",
    sale: "bg-sale text-bg",
  };
  return (
    <span className={`inline-block rounded-[var(--radius-pill)] px-2.5 py-1 text-xs font-medium ${tones[tone]}`}>
      {children}
    </span>
  );
}
