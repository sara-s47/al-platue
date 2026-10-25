<?php

namespace App\Services\DayCatalog;

use App\Enums\BookingStatus;
use App\Enums\RentalOrderStatus;
use App\Exceptions\BusinessException;
use App\Support\DayCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DayCatalogService
{
    public function paginate(string $type, int $perPage = 15): LengthAwarePaginator
    {
        $modelClass = DayCatalog::modelClass($type);
        $paginator = $modelClass::query()
            ->with('images')
            ->orderByDesc('id')
            ->paginate($perPage);
        $this->attachStats($type, $paginator->getCollection()->all());

        return $paginator;
    }

    public function find(string $type, int $id): Model
    {
        $modelClass = DayCatalog::modelClass($type);
        /** @var Model $product */
        $product = $modelClass::query()->with('images')->findOrFail($id);
        $this->attachStats($type, [$product]);

        return $product;
    }

    /**
     * @param  list<string>  $imagePaths
     */
    public function create(string $type, array $data, array $imagePaths = []): Model
    {
        return DB::transaction(function () use ($type, $data, $imagePaths) {
            $modelClass = DayCatalog::modelClass($type);
            $product = $modelClass::query()->create($data);
            $this->syncImages($type, $product->id, $imagePaths);

            return $this->find($type, $product->id);
        });
    }

    /**
     * @param  list<string>|null  $imagePaths  null keeps current images; an array replaces them.
     */
    public function update(string $type, int $id, array $data, ?array $imagePaths = null): Model
    {
        return DB::transaction(function () use ($type, $id, $data, $imagePaths) {
            $modelClass = DayCatalog::modelClass($type);
            /** @var Model $product */
            $product = $modelClass::query()->findOrFail($id);
            $product->update($data);

            if ($imagePaths !== null) {
                $this->syncImages($type, $id, $imagePaths);
            }

            return $this->find($type, $id);
        });
    }

    public function delete(string $type, int $id): bool
    {
        return DB::transaction(function () use ($type, $id) {
            $modelClass = DayCatalog::modelClass($type);
            $orderItemClass = DayCatalog::orderItemClass($type);
            $fk = DayCatalog::productFk($type);

            $modelClass::query()->findOrFail($id);

            if ($orderItemClass::query()->where($fk, $id)->exists()) {
                throw new BusinessException(
                    'Item has order history and cannot be deleted. Deactivate it instead.',
                    'day_catalog_has_orders',
                );
            }

            $this->syncImages($type, $id, []);

            return (bool) $modelClass::query()->whereKey($id)->delete();
        });
    }

    /**
     * Order history rows for a catalog product, newest first.
     */
    public function orderHistory(string $type, int $productId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        DayCatalog::modelClass($type)::query()->findOrFail($productId);

        $orderItemClass = DayCatalog::orderItemClass($type);
        $fk = DayCatalog::productFk($type);
        $status = $filters['status'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        return $orderItemClass::query()
            ->where($fk, $productId)
            ->with(['order.user'])
            ->when($status, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('status', $status)))
            ->when($from, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('end_date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('start_date', '<=', $to)))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Sets a `stats` attribute on each product. Cancelled orders are excluded.
     *
     * @param  list<Model>  $products
     */
    public function attachStats(string $type, array $products): void
    {
        $ids = array_map(fn (Model $p) => $p->id, $products);

        if ($ids === []) {
            return;
        }

        $orderItemClass = DayCatalog::orderItemClass($type);
        $table = (new $orderItemClass)->getTable();
        $fk = DayCatalog::productFk($type);

        $rows = DB::table($table)
            ->join('orders', 'orders.id', '=', "{$table}.order_id")
            ->whereIn("{$table}.{$fk}", $ids)
            ->where('orders.status', '!=', RentalOrderStatus::Cancelled->value)
            ->where('orders.status', '!=', BookingStatus::Cancelled->value)
            ->groupBy("{$table}.{$fk}")
            ->select([
                "{$table}.{$fk} as product_id",
                DB::raw('COUNT(*) as times_rented'),
                DB::raw("SUM({$table}.quantity) as total_quantity_rented"),
                DB::raw("SUM({$table}.total_price) as total_revenue"),
            ])
            ->get()
            ->keyBy('product_id');

        foreach ($products as $product) {
            $row = $rows->get($product->id);

            $product->setAttribute('stats', [
                'times_rented' => (int) ($row->times_rented ?? 0),
                'total_quantity_rented' => (int) ($row->total_quantity_rented ?? 0),
                'total_revenue' => round((float) ($row->total_revenue ?? 0), 2),
            ]);
        }
    }

    /**
     * @param  list<string>  $imagePaths
     */
    protected function syncImages(string $type, int $productId, array $imagePaths): void
    {
        $modelClass = DayCatalog::modelClass($type);
        /** @var Model $product */
        $product = $modelClass::query()->findOrFail($productId);
        $imagesRelation = $product->images();
        $fk = $imagesRelation->getForeignKeyName();
        $existing = $imagesRelation->get();

        foreach ($existing as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $imagesRelation->delete();

        $related = $imagesRelation->getRelated();

        foreach (array_values($imagePaths) as $index => $path) {
            $related->newQuery()->create([
                $fk => $productId,
                'path' => $path,
                'sort_order' => $index,
            ]);
        }
    }
}
