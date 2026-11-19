export function SearchForm({
  id = "q",
  defaultValue = "",
  className = "",
}: {
  id?: string;
  defaultValue?: string;
  className?: string;
}) {
  return (
    <form action="/buscar" role="search" className={`relative ${className}`}>
      <label htmlFor={id} className="sr-only">
        Buscar productos
      </label>
      <input
        id={id}
        name="q"
        type="search"
        defaultValue={defaultValue}
        placeholder="Buscar productos"
        maxLength={100}
        className="h-10 w-full rounded-full border border-border bg-surface pl-4 pr-10 text-sm placeholder:text-muted focus:border-fg focus:outline-none"
      />
      <button type="submit" className="absolute right-1 top-1 flex h-8 w-8 items-center justify-center rounded-full hover:bg-subtle">
        <span className="sr-only">Buscar</span>
        <svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-3.5-3.5" />
        </svg>
      </button>
    </form>
  );
}
