"use client";

import { configureTracking, type TrackingSetup } from "@/lib/tracking";

/** Runs during render (not in an effect) so the tag queues exist before any page effect fires an event. */
export function TrackingInit(props: TrackingSetup) {
  configureTracking(props);
  return null;
}
