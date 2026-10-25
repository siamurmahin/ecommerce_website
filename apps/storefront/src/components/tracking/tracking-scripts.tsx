import Script from "next/script";
import type { TrackingConfig } from "@/lib/api/types";
import { CustomScripts } from "./custom-scripts";
import { PageScripts } from "./page-scripts";
import { TrackingInit } from "./tracking-init";

/**
 * Loads the tags configured under admin → ট্র্যাকিং ও স্ক্রিপ্ট; each is skipped while its ID is empty.
 * Command queues are created by TrackingInit; these only fetch the vendor libraries.
 */
export function TrackingScripts({ config }: { config: TrackingConfig }) {
  const { gtm_id: gtm, ga4_measurement_id: ga4, meta_pixel_id: pixel, tiktok_pixel_id: tiktok, google_ads_id: ads, clarity_id: clarity } = config;

  return (
    <>
      <TrackingInit
        gtmId={gtm}
        ga4Id={ga4}
        pixelId={pixel}
        tiktokId={tiktok}
        adsId={ads}
        adsPurchaseLabel={config.google_ads_purchase_label}
        serverEvents={config.server_events}
      />
      {gtm && (
        <>
          <noscript>
            <iframe src={`https://www.googletagmanager.com/ns.html?id=${encodeURIComponent(gtm)}`} height="0" width="0" style={{ display: "none", visibility: "hidden" }} />
          </noscript>
          <Script id="gtm" src={`https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtm)}`} strategy="afterInteractive" />
        </>
      )}
      {(ga4 || ads) && <Script id="gtag" src={`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent((ga4 ?? ads)!)}`} strategy="afterInteractive" />}
      {pixel && <Script id="meta-pixel" src="https://connect.facebook.net/en_US/fbevents.js" strategy="afterInteractive" />}
      {tiktok && <Script id="tiktok-pixel" src={`https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=${encodeURIComponent(tiktok)}&lib=ttq`} strategy="afterInteractive" />}
      {clarity && (
        <Script id="clarity" strategy="lazyOnload">
          {`(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script",${JSON.stringify(clarity)});`}
        </Script>
      )}
      <CustomScripts head={config.scripts.head} bodyStart={config.scripts.body_start} bodyEnd={config.scripts.body_end} />
      {config.page_scripts.length > 0 && <PageScripts scripts={config.page_scripts} />}
    </>
  );
}
