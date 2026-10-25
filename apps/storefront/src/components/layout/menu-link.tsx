import { Link } from "@/i18n/navigation";

type Props = Omit<React.ComponentProps<"a">, "href"> & { href: string };

/** Admin-managed menu links: storefront paths go through the locale-aware Link, full URLs open as-is. */
export function MenuLink({ href, ...props }: Props) {
  if (/^https?:\/\//.test(href)) return <a href={href} target="_blank" rel="noopener noreferrer" {...props} />;
  return <Link href={href} {...props} />;
}
