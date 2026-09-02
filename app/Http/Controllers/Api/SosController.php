<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sos\StoreSosAlertRequest;
use App\Http\Requests\Sos\StoreSosResponseRequest;
use App\Http\Resources\Sos\SosAlertResource;
use App\Http\Resources\Sos\SosResponseResource;
use App\Jobs\SendSosNotificationJob;
use App\Models\SosAlert;
use App\Services\SosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SosController extends Controller
{
    public function __construct(
        private readonly SosService $sosService,
    ) {}

    public function store(StoreSosAlertRequest $request): JsonResponse
    {
        $alert = $this->sosService->createAlert(
            $request->validated(),
            $request->user()
        );

        SendSosNotificationJob::dispatch($alert->id)->afterCommit();

        $alert->load([
            'triggerer.resident.households' => function ($q) {
                $q->wherePivot('is_current', true);
            },
            'resolver',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SOS alert created successfully',
            'data' => new SosAlertResource($alert),
            'meta' => null,
        ], 201);
    }

    public function active(Request $request): JsonResponse
    {
        $alerts = $this->sosService->getActiveAlerts(10);

        return response()->json([
            'success' => true,
            'message' => 'Active SOS alerts retrieved successfully',
            'data' => SosAlertResource::collection($alerts->items()),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
            ],
        ]);
    }

    public function show(SosAlert $sos): JsonResponse
    {
        $sos = $this->sosService->getAlert($sos);

        return response()->json([
            'success' => true,
            'message' => 'SOS alert retrieved successfully',
            'data' => new SosAlertResource($sos),
            'meta' => null,
        ]);
    }

    public function resolve(Request $request, SosAlert $sos): JsonResponse
    {
        if (! $request->user()->hasRole('rt')) {
            abort(403, 'Hanya RT yang dapat menyelesaikan SOS.');
        }

        $sos = $this->sosService->resolveAlert($sos, $request->user());

        $sos->load([
            'triggerer.resident.households' => function ($q) {
                $q->wherePivot('is_current', true);
            },
            'resolver',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SOS alert resolved successfully',
            'data' => new SosAlertResource($sos),
            'meta' => null,
        ]);
    }

    public function storeResponse(StoreSosResponseRequest $request, SosAlert $sos): JsonResponse
    {
        $response = $this->sosService->respondToAlert(
            $sos,
            $request->validated(),
            $request->user()
        );

        $response->load('user');

        return response()->json([
            'success' => true,
            'message' => 'SOS response recorded successfully',
            'data' => new SosResponseResource($response),
            'meta' => null,
        ], 201);
    }
}
