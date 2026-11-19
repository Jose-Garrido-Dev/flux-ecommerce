import Link from "next/link";

export function SectionHeading({
  eyebrow,
  title,
  href,
  linkLabel = "Ver todo",
}: {
  eyebrow?: string;
  title: string;
  href?: string;
  linkLabel?: string;
}) {
  return (
    <div data-reveal className="mb-10 flex items-end justify-between gap-6">
      <div>
        {eyebrow && <p className="label">{eyebrow}</p>}
        <h2 className="font-display text-5xl sm:text-6xl">{title}</h2>
      </div>
      {href && (
        <Link href={href} className="group hidden shrink-0 items-center gap-2 rounded-full border-2 border-fg/15 px-5 py-2.5 text-sm font-semibold transition-colors hover:border-fg sm:flex">
          {linkLabel}
        </Link>
      )}
    </div>
  );
}
