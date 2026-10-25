import { Reveal } from "@/components/motion/reveal";
import { SectionHeading } from "./section-heading";

/** Free-form block from the homepage builder; `content` is sanitized by the API. */
export function TextSection({ title, content }: { title: string | null; content: string | null }) {
  if (!title && !content) return null;

  return (
    <section className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4">
      {title && <SectionHeading title={title} />}
      {content && (
        <Reveal>
          <div className="prose-content max-w-[70ch] leading-relaxed text-foreground/85" dangerouslySetInnerHTML={{ __html: content }} />
        </Reveal>
      )}
    </section>
  );
}
