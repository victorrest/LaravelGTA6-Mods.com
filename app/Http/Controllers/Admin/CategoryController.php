<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', [
            'categories' => ModCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:mod_categories,slug'],
            'icon' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        ModCategory::create($data);

        Cache::forget('navigation:categories');

        return redirect()->route('admin.categories.index')->with('status', 'Category created successfully.');
    }

    public function update(Request $request, ModCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('mod_categories', 'slug')->ignore($category->id)],
            'icon' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category->update($data);

        Cache::forget('navigation:categories');

        return redirect()->route('admin.categories.index')->with('status', 'Category updated successfully.');
    }

    public function destroy(ModCategory $category): RedirectResponse
    {
        $category->mods()->detach();
        $category->delete();

        Cache::forget('navigation:categories');

        return redirect()->route('admin.categories.index')->with('status', 'Category removed successfully.');
    }
}
