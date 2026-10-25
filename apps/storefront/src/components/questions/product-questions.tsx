import { getLocale, getTranslations } from "next-intl/server";
import { getProductQuestions } from "@/lib/api/catalog";
import { AskQuestion } from "./ask-question";
import { QuestionList } from "./question-list";

/** Answered shopper questions, plus a form to ask a new one. */
export async function ProductQuestions({ slug }: { slug: string }) {
  const [t, locale] = [await getTranslations("questions"), await getLocale()];
  const first = await getProductQuestions(slug, locale);

  return (
    <section id="questions" className="grid scroll-mt-28 gap-10 border-t border-border pt-12 md:grid-cols-[280px_1fr]">
      <div className="flex flex-col gap-3">
        <h2 className="text-2xl font-semibold">{t("title")}</h2>
        <p className="text-sm text-muted-foreground">{t("intro")}</p>
        <AskQuestion slug={slug} />
      </div>

      {first && first.data.length > 0 ? (
        <QuestionList slug={slug} initial={first} />
      ) : (
        <p className="rounded-lg bg-card px-6 py-12 text-center text-muted-foreground">{t("empty")}</p>
      )}
    </section>
  );
}
