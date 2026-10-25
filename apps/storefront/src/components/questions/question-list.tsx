"use client";

import { useInfiniteQuery } from "@tanstack/react-query";
import { Loader2, Store } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { buttonVariants } from "@/components/ui/button";
import { getQuestionPage } from "@/lib/api/customer";
import type { ProductQuestion, QuestionPage } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** Server-rendered first page, further pages fetched on demand. */
export function QuestionList({ slug, initial }: { slug: string; initial: QuestionPage }) {
  const t = useTranslations("questions");
  const locale = useLocale();
  const { data, fetchNextPage, hasNextPage, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ["questions", slug, locale],
    queryFn: ({ pageParam }) => getQuestionPage(slug, pageParam, locale),
    initialPageParam: 1,
    initialData: { pages: [initial], pageParams: [1] },
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
    staleTime: 60_000,
  });
  const questions = data.pages.flatMap((p) => p.data);

  return (
    <div className="flex flex-col gap-4">
      <ul className="flex flex-col gap-3">
        {questions.map((q) => (
          <QuestionItem key={q.id} question={q} />
        ))}
      </ul>
      {hasNextPage && (
        <button type="button" onClick={() => fetchNextPage()} disabled={isFetchingNextPage} className={cn(buttonVariants({ variant: "outline" }), "h-11 self-center rounded-full px-6")}>
          {isFetchingNextPage && <Loader2 className="size-4 animate-spin" />}
          {t("loadMore")}
        </button>
      )}
    </div>
  );
}

function QuestionItem({ question }: { question: ProductQuestion }) {
  const t = useTranslations("questions");
  const locale = useLocale();
  const date = new Intl.DateTimeFormat(locale === "bn" ? "bn-BD" : "en-GB", { day: "numeric", month: "short", year: "numeric" }).format(new Date(question.created_at));

  return (
    <li className="flex flex-col gap-3 rounded-lg bg-card p-5">
      <div className="flex gap-3">
        <span aria-hidden className="grid size-7 shrink-0 place-items-center rounded-full bg-secondary text-xs font-bold">
          {t("q")}
        </span>
        <div className="flex min-w-0 flex-col gap-1">
          <p className="font-semibold">{question.question}</p>
          <p className="text-xs text-muted-foreground">
            {question.name} · {date}
          </p>
        </div>
      </div>
      {question.answer && (
        <div className="flex gap-3 rounded-md bg-secondary/60 p-3.5 text-sm">
          <Store className="mt-0.5 size-4 shrink-0 text-primary" strokeWidth={1.8} aria-label={t("shopAnswer")} />
          <p className="whitespace-pre-line">{question.answer}</p>
        </div>
      )}
    </li>
  );
}
