import type { CustomerRewards } from "@/lib/api/types";

type PointsRules = NonNullable<CustomerRewards["points"]>;

/** Mirrors RewardSettings::redeemable on the backend: capped by balance and the max share of the products' price. */
export function redeemablePoints(rules: PointsRules, productsTotal: number): number {
  if (rules.point_value <= 0) return 0;
  const cap = Math.floor(Math.floor((productsTotal * Math.min(100, rules.max_redeem_percent)) / 100) / rules.point_value);
  const points = Math.min(rules.balance, cap);
  return points >= rules.min_redeem ? points : 0;
}
