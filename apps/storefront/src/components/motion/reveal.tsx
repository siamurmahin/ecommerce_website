"use client";

import { motion, useReducedMotion } from "motion/react";
import type { ReactNode } from "react";

type Props = {
  children: ReactNode;
  className?: string;
  delay?: number;
  as?: "div" | "section" | "li";
};

/** Fade-up once when scrolled into view. Renders static when the user prefers reduced motion. */
export function Reveal({ children, className, delay = 0, as = "div" }: Props) {
  const reduce = useReducedMotion();
  const Tag = motion[as];

  if (reduce) {
    const Static = as;
    return <Static className={className}>{children}</Static>;
  }

  return (
    <Tag
      className={className}
      initial={{ opacity: 0, y: 18 }}
      whileInView={{ opacity: 1, y: 0 }}
      // Start just before the element enters the screen so fast scrolling never shows empty sections.
      viewport={{ once: true, margin: "0px 0px 12% 0px" }}
      transition={{ duration: 0.45, delay, ease: [0.22, 1, 0.36, 1] }}
    >
      {children}
    </Tag>
  );
}
