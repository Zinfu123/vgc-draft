<?php

namespace App\Modules\Shared\Actions;

/* Define Dependencies */
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
/* End Define Dependencies */

class LogoToUrlAction
{
    public function logoToUrl($logo)
    {
        Log::info('LogoToUrlAction: ' . $logo);
        // If it's already a full URL, return it as-is
        if (filter_var($logo, FILTER_VALIDATE_URL) || strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0) {
            return str_replace('\\', '/', $logo);
        }
        else {
            // Otherwise, convert the path to a URL
            return str_replace('\\', '/', Storage::disk('s3-team-logos')->url($logo));
        }
    }
}