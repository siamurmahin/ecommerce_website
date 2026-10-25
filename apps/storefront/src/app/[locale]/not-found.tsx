import { useTranslations } from "next-intl";
import { buttonVariants } from "@/components/ui/button";
import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";

export default function NotFound() {
  const t = useTranslations("system");

  return (
    <main className="mx-auto flex max-w-md flex-1 flex-col items-center justify-center gap-5 px-4 py-24 text-center">
      <p className="font-heading text-7xl font-semibold text-primary">404</p>
      <h1 className="text-2xl font-semibold">{t("notFound")}</h1>
      <Link href="/" className={cn(buttonVariants({ size: "lg" }), "rounded-full px-6")}>
        {t("backHome")}
      </Link>
    </main>
  );
}
