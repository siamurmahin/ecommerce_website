import { Link } from "@/i18n/navigation";

type Crumb = { href: string; label: string };

export function PageHeader({ title, description, crumbs = [] }: { title: string; description?: string | null; crumbs?: Crumb[] }) {
  return (
    <header className="flex flex-col gap-3 pt-8 pb-2 md:pt-12">
      {crumbs.length > 0 && (
        <nav aria-label="breadcrumb">
          <ol className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
            {crumbs.map((c) => (
              <li key={c.href} className="flex items-center gap-1.5">
                <Link href={c.href} className="hover:text-foreground">
                  {c.label}
                </Link>
                <span aria-hidden>/</span>
              </li>
            ))}
          </ol>
        </nav>
      )}
      <h1 className="text-3xl font-semibold tracking-tight md:text-5xl">{title}</h1>
      {description && <p className="max-w-2xl text-muted-foreground">{description}</p>}
    </header>
  );
}
