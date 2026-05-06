<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryRepository
{
    public function getAllCategories(int $perPage = 10): LengthAwarePaginator
    {
        return Category::select('id', 'name')->paginate($perPage);
    }

    public function createCategory(array $data): Category
    {
        return Category::create($data);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    public function deleteCategory(Category $category): bool
    {
        return $category->delete();
    }
}
