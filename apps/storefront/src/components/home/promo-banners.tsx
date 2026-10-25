import Image from "next/image";
import { Reveal } from "@/components/motion/reveal";
import type { Banner } from "@/lib/api/types";
import { BannerLink } from "./banner-link";

export function PromoBanners({ banners }: { banners: Banner[] }) {
  if (banners.length === 0) return null;

  return (
    <section className="mx-auto grid w-full max-w-6xl gap-4 px-4 sm:grid-cols-2">
      {banners.map((banner, i) => (
        <Reveal key={banner.id} delay={i * 0.06}>
          <BannerLink href={banner.link} className="group relative block aspect-[2/1] overflow-hidden rounded-lg bg-placeholder">
            <Image
              src={banner.image}
              alt={banner.title}
              fill
              sizes="(min-width: 640px) 50vw, 100vw"
              className="object-cover transition-transform duration-700 ease-out group-hover:scale-[1.03]"
            />
          </BannerLink>
        </Reveal>
      ))}
    </section>
  );
}
