import { Link } from "@/i18n/navigation";

/** Admin links can be storefront paths (/c/men) or full URLs; only paths go through the locale router. */
export function BannerLink({ href, className, children }: { href: string | null; className?: string; children: React.ReactNode }) {
  if (!href) return <div className={className}>{children}</div>;
  if (/^https?:\/\//.test(href)) {
    return (
      <a href={href} className={className} target="_blank" rel="noopener noreferrer">
        {children}
      </a>
    );
  }
  return (
    <Link href={href} className={className}>
      {children}
    </Link>
  );
}
