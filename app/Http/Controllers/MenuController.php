<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        $menus = Menu::withCount('allItems')->orderBy('name')->get();

        return response()->json($menus->map(fn (Menu $m) => [
            'id' => (string) $m->id,
            'name' => $m->name,
            'handle' => $m->handle,
            'itemCount' => (int) $m->all_items_count,
        ])->values());
    }

    public function show(string $id): JsonResponse
    {
        $menu = Menu::with('items.children.children')->find($id);

        if (! $menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        return response()->json($this->detail($menu));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $menu = Menu::create([
            'name' => $validated['name'],
            'handle' => $this->uniqueHandle($validated['name']),
        ]);

        return response()->json($this->detail($menu->load('items.children.children')), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $menu = Menu::find($id);

        if (! $menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'items' => ['array'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.type' => ['required', 'in:custom,collection'],
            'items.*.url' => ['required', 'string', 'max:2048'],
            'items.*.children' => ['array'],
            'items.*.children.*.label' => ['required', 'string', 'max:255'],
            'items.*.children.*.type' => ['required', 'in:custom,collection'],
            'items.*.children.*.url' => ['required', 'string', 'max:2048'],
        ]);

        DB::transaction(function () use ($menu, $validated) {
            if ($validated['name'] !== $menu->name) {
                $menu->update([
                    'name' => $validated['name'],
                    'handle' => $this->uniqueHandle($validated['name'], $menu->id),
                ]);
            }

            // Replace the whole item tree from what was submitted, rather
            // than diffing — simpler and matches how the editor always
            // submits its complete current state.
            $menu->allItems()->delete();

            foreach ($validated['items'] ?? [] as $position => $item) {
                $this->createItem($menu, $item, null, $position);
            }
        });

        return response()->json($this->detail($menu->fresh('items.children.children')));
    }

    public function destroy(string $id): JsonResponse
    {
        $menu = Menu::find($id);

        if (! $menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        $menu->delete();

        return response()->json(null, 204);
    }

    private function createItem(Menu $menu, array $item, ?int $parentId, int $position): void
    {
        $menuItem = MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $parentId,
            'label' => $item['label'],
            'type' => $item['type'],
            'url' => $item['url'],
            'position' => $position,
        ]);

        foreach ($item['children'] ?? [] as $childPosition => $child) {
            $this->createItem($menu, $child, $menuItem->id, $childPosition);
        }
    }

    private function uniqueHandle(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'menu';
        $handle = $base;
        $suffix = 1;

        while (
            Menu::where('handle', $handle)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $suffix++;
            $handle = "{$base}-{$suffix}";
        }

        return $handle;
    }

    private function detail(Menu $menu): array
    {
        return [
            'id' => (string) $menu->id,
            'name' => $menu->name,
            'handle' => $menu->handle,
            'itemCount' => $menu->allItems()->count(),
            'items' => $menu->items->map(fn (MenuItem $item) => $this->itemNode($item))->values(),
        ];
    }

    private function itemNode(MenuItem $item): array
    {
        return [
            'id' => (string) $item->id,
            'label' => $item->label,
            'type' => $item->type,
            'url' => $item->url,
            'children' => $item->children->map(fn (MenuItem $child) => $this->itemNode($child))->values(),
        ];
    }
}
