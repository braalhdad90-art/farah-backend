<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Support\Facades\Storage;

trait ImageUrlHelper
{
    protected function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return filter_var($path, FILTER_VALIDATE_URL)
            ? $path
            : Storage::disk('public')->url($path);
    }
}
