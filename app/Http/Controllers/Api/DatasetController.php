<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DatasetItemRequest;
use App\Http\Resources\DatasetItemResource;
use App\Http\Resources\DatasetResource;
use App\Services\DatasetItemService;
use App\Services\DatasetService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetController extends Controller
{
    public function __construct(
        private readonly DatasetService $datasetService,
        private readonly DatasetItemService $itemService
    ) {}

    public function index(): JsonResponse
    {
        $datasets = $this->datasetService->getAll();

        return response()->json([
            'success' => true,
            'data' => DatasetResource::collection($datasets),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        try {
            $summary = $this->datasetService->getSummary($slug);

            return response()->json([
                'success' => true,
                'data' => [
                    'dataset' => new DatasetResource($summary['dataset']),
                    'total_items' => $summary['total_items'],
                    'total_value' => $summary['total_value'],
                    'status_counts' => $summary['status_counts'],
                    'category_counts' => $summary['category_counts'],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function items(Request $request, string $slug): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->query('search'),
                'category' => $request->query('category'),
                'status' => $request->query('status'),
                'sort_by' => $request->query('sort_by', 'id'),
                'sort_order' => $request->query('sort_order', 'asc'),
                'per_page' => $request->query('per_page', 15),
            ];

            $result = $this->itemService->listItems($slug, $filters);
            $paginator = $result['paginator'];

            return response()->json([
                'success' => true,
                'dataset' => new DatasetResource($result['dataset']),
                'data' => DatasetItemResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function storeItem(DatasetItemRequest $request, string $slug): JsonResponse
    {
        try {
            $item = $this->itemService->createItem($slug, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil ditambahkan.',
                'data' => new DatasetItemResource($item),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function updateItem(DatasetItemRequest $request, int $id): JsonResponse
    {
        try {
            $item = $this->itemService->updateItem($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diperbarui.',
                'data' => new DatasetItemResource($item),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function deleteItem(int $id): JsonResponse
    {
        try {
            $this->itemService->deleteItem($id);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dihapus.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }
}
