import { Mail, MapPin, Phone } from "lucide-react";
import { getLocale, getTranslations } from "next-intl/server";
import { getCategories } from "@/lib/api/catalog";
import { getMenus, getSettings } from "@/lib/api/content";
import type { MenuItem } from "@/lib/api/types";
import { BrandMark } from "./brand-mark";
import { MenuLink } from "./menu-link";
import { SocialIcon } from "./social-icon";

export async function SiteFooter() {
  const [t, locale] = [await getTranslations("footer"), await getLocale()];
  const [allCategories, menus, settings] = await Promise.all([getCategories(locale), getMenus(locale), getSettings(locale)]);
  const categories = allCategories.slice(0, 6).map((c) => ({ id: c.id, label: c.name ?? c.slug, url: `/c/${c.slug}` }));

  return (
    <footer className="site-chrome mt-24 border-t border-border bg-card">
      <div className="mx-auto grid max-w-6xl grid-cols-2 gap-x-6 gap-y-10 px-4 py-12 md:grid-cols-[1.4fr_1fr_1fr_1fr] md:gap-10 md:py-14">
        <div className="col-span-2 flex flex-col gap-4 md:col-span-1">
          <BrandMark brand={settings.brand} className="text-2xl" />
          {settings.footer_about && <p className="max-w-xs text-sm leading-relaxed text-muted-foreground">{settings.footer_about}</p>}
          <ul className="flex flex-col gap-2 text-sm">
            <li>
              <a href={`tel:${settings.hotline_tel}`} className="inline-flex items-center gap-2 tabular-nums transition-colors hover:text-primary">
                <Phone className="size-4 text-muted-foreground" strokeWidth={1.8} />
                {settings.hotline}
              </a>
            </li>
            {settings.email && (
              <li>
                <a href={`mailto:${settings.email}`} className="inline-flex items-center gap-2 transition-colors hover:text-primary">
                  <Mail className="size-4 text-muted-foreground" strokeWidth={1.8} />
                  {settings.email}
                </a>
              </li>
            )}
            {settings.address && (
              <li className="flex items-start gap-2 text-muted-foreground">
                <MapPin className="mt-0.5 size-4 shrink-0" strokeWidth={1.8} />
                <span className="max-w-xs whitespace-pre-line">{settings.address}</span>
              </li>
            )}
          </ul>
          {settings.socials.length > 0 && (
            <ul className="flex gap-2">
              {settings.socials.map((s) => (
                <li key={s.network}>
                  <a
                    href={s.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={t(`social.${s.network}`)}
                    className="grid size-10 place-items-center rounded-full border border-border text-foreground transition-colors hover:border-primary hover:text-primary"
                  >
                    <SocialIcon network={s.network} className="size-[18px]" />
                  </a>
                </li>
              ))}
            </ul>
          )}
        </div>
        <FooterColumn title={t("shop")} links={categories} />
        <FooterColumn title={t("help")} links={menus.footer_help} />
        <FooterColumn title={t("company")} links={menus.footer_company} />
      </div>
      <div className="border-t border-border">
        <p className="mx-auto max-w-6xl px-4 py-5 text-xs text-muted-foreground">{t("rights", { brand: settings.brand.name })}</p>
      </div>
    </footer>
  );
}

function FooterColumn({ title, links }: { title: string; links: Pick<MenuItem, "id" | "label" | "url">[] }) {
  if (links.length === 0) return null;

  return (
    <div className="flex flex-col gap-3">
      <h2 className="font-sans text-sm font-bold">{title}</h2>
      <ul className="flex flex-col gap-2 text-sm text-muted-foreground">
        {links.map((link) => (
          <li key={link.id}>
            <MenuLink href={link.url} className="transition-colors hover:text-foreground">
              {link.label}
            </MenuLink>
          </li>
        ))}
      </ul>
    </div>
  );
}
