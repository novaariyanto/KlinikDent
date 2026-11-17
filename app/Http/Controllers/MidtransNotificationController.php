<?php

namespace App\Http\Controllers;

use App\Models\SaasAddonInvoice;
use App\Models\SaasInvoice;
use App\Support\Saas\MidtransException;
use App\Support\Saas\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransNotificationController extends Controller
{
    public function store(Request $request, MidtransService $midtrans): JsonResponse
    {
        try {
            $invoice = $midtrans->handleNotification($request->all());
        } catch (MidtransException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        if ($invoice instanceof SaasInvoice && $invoice->status->value === 'paid') {
            activity_log(
                'updated',
                $invoice,
                ['status' => 'paid', 'channel' => 'midtrans'],
                'Invoice '.$invoice->number.' lunas via Midtrans.',
                'subscription'
            );
        }

        if ($invoice instanceof SaasAddonInvoice && $invoice->status->value === 'paid') {
            activity_log(
                'updated',
                $invoice,
                ['status' => 'paid', 'channel' => 'midtrans'],
                'Invoice Add-on '.$invoice->number.' lunas via Midtrans.',
                'addon'
            );
        }

        return response()->json(['message' => 'ok']);
    }
}
