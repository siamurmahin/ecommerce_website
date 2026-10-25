<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Courier\Actions\ApplyShipmentStatus;
use App\Domain\Courier\CourierManager;
use App\Domain\Courier\Models\Shipment;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Status pushes from couriers. Each driver verifies its own auth (token header or query). */
class CourierWebhookController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function __invoke(Request $request, string $courier, CourierManager $couriers, ApplyShipmentStatus $apply): Response
    {
        abort_unless(array_key_exists($courier, config('courier.couriers')), 404);
        $driver = $couriers->driver($courier);

        try {
            $update = $driver->parseWebhook($request);
        } catch (AuthorizationException) {
            abort(401);
        }

        if ($update && $shipment = Shipment::with('order')->where('courier', $courier)->where('consignment_id', $update->consignmentId)->first()) {
            $apply->handle($shipment, $update->rawStatus);
        }

        return $driver->webhookResponse();
    }
}
