"use client";

import { motion, useReducedMotion } from "motion/react";
import type { ReactNode } from "react";

type Props = {
  children: ReactNode;
  className?: string;
  delay?: number;
  as?: "div" | "section" | "li";
};

/**
 * Fade-up once when scrolled into view. With reduced motion it stays put: `[data-reveal]` in globals.css
 * overrides the hidden starting style, which the server has already rendered before the preference is known.
 */
export function Reveal({ children, className, delay = 0, as = "div" }: Props) {
  const reduce = useReducedMotion();
  const Tag = motion[as];

  return (
    <Tag
      data-reveal
      className={className}
      initial={reduce ? false : { opacity: 0, y: 18 }}
      whileInView={{ opacity: 1, y: 0 }}
      // Start just before the element enters the screen so fast scrolling never shows empty sections.
      viewport={{ once: true, margin: "0px 0px 12% 0px" }}
      transition={{ duration: 0.45, delay, ease: [0.22, 1, 0.36, 1] }}
    >
      {children}
    </Tag>
  );
}
