<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Folds one customer into another once the same person has proved owning both (an email account verifying a phone
 * that already has an account): addresses, wishlist, reviews, questions, points and signed-in orders move over, the
 * verified email comes along, and the emptied account is deleted.
 */
final class MergeCustomers
{
    public function handle(Customer $from, Customer $into): Customer
    {
        return DB::transaction(function () use ($from, $into) {
            $id = $from->id;

            DB::table('customer_addresses')->where('customer_id', $id)->update(['customer_id' => $into->id]);
            DB::table('product_questions')->where('customer_id', $id)->update(['customer_id' => $into->id]);
            DB::table('point_transactions')->where('customer_id', $id)->update(['customer_id' => $into->id]);
            Order::withTrashed()->where('customer_id', $id)->update(['customer_id' => $into->id]);
            Order::withTrashed()->where('referrer_id', $id)->update(['referrer_id' => $into->id]);
            Customer::where('referred_by_id', $id)->update(['referred_by_id' => $into->id]);

            // One row per product is allowed: keep what the surviving account already has.
            $wished = DB::table('wishlist_items')->where('customer_id', $into->id)->pluck('product_id');
            DB::table('wishlist_items')->where('customer_id', $id)->whereIn('product_id', $wished)->delete();
            DB::table('wishlist_items')->where('customer_id', $id)->update(['customer_id' => $into->id]);
            $reviewed = DB::table('reviews')->where('customer_id', $into->id)->pluck('product_id');
            DB::table('reviews')->where('customer_id', $id)->whereIn('product_id', $reviewed)->delete();
            DB::table('reviews')->where('customer_id', $id)->update(['customer_id' => $into->id]);

            $into->forceFill([
                'name' => $into->name ?? $from->name,
                'points_balance' => (int) $into->points_balance + (int) $from->points_balance,
                'referred_by_id' => $into->referred_by_id ?? $from->referred_by_id,
                'is_blocked' => $into->is_blocked || $from->is_blocked,
            ]);
            if ($from->email_verified_at && ! $into->email_verified_at) {
                $into->forceFill(['email' => $from->email, 'email_verified_at' => $from->email_verified_at]);
            }

            $from->tokens()->delete();
            $from->delete();
            $into->save();

            return $into;
        });
    }
}
