"use client";

import { Check } from "lucide-react";
import { motion, useReducedMotion } from "motion/react";

export function OrderSuccessMark() {
  const reduce = useReducedMotion();

  return (
    <motion.span
      initial={reduce ? false : { scale: 0.6, opacity: 0 }}
      animate={{ scale: 1, opacity: 1 }}
      transition={{ type: "spring", stiffness: 320, damping: 18 }}
      className="grid size-16 place-items-center rounded-full bg-primary text-primary-foreground"
    >
      <Check className="size-8" strokeWidth={2.4} />
    </motion.span>
  );
}
