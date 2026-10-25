import { ImageResponse } from "next/og";
import { notFound } from "next/navigation";
import { getSettings } from "@/lib/api/content";
import { routing } from "@/i18n/routing";

const FILES = { "192.png": [192, false], "512.png": [512, false], "apple.png": [180, false], "maskable-512.png": [512, true] } as const;

export function generateStaticParams() {
  return Object.keys(FILES).map((file) => ({ file }));
}

/**
 * Home-screen icons. The brand logo when it is a format the renderer reads (png/jpg),
 * otherwise the name's first letter on the primary colour. Maskable icons keep a safe margin.
 */
export async function GET(_request: Request, { params }: RouteContext<"/pwa-icons/[file]">) {
  const { file } = await params;
  if (!(file in FILES)) notFound();
  const [size, maskable] = FILES[file as keyof typeof FILES];
  const { brand, theme } = await getSettings(routing.defaultLocale);
  const logo = brand.logo && /\.(png|jpe?g)(\?|$)/i.test(brand.logo) ? brand.logo : null;
  const letter = brand.name.match(/[A-Za-z0-9]/)?.[0]?.toUpperCase() ?? "•";

  return new ImageResponse(
    logo ? (
      <div style={{ width: "100%", height: "100%", display: "flex", alignItems: "center", justifyContent: "center", background: theme.colors.surface }}>
        {/* eslint-disable-next-line @next/next/no-img-element -- rendered to PNG by Satori */}
        <img src={logo} alt="" style={{ width: maskable ? "62%" : "80%", height: maskable ? "62%" : "80%", objectFit: "contain" }} />
      </div>
    ) : (
      <div
        style={{
          width: "100%",
          height: "100%",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          background: theme.colors.primary,
          color: "#FFFFFF",
          fontSize: size * (maskable ? 0.42 : 0.56),
          fontWeight: 700,
          borderRadius: maskable || file === "apple.png" ? 0 : size * 0.22, // iOS rounds its own
        }}
      >
        {letter}
      </div>
    ),
    { width: size, height: size, headers: { "Cache-Control": "public, max-age=86400" } },
  );
}
