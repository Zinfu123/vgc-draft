<?php

namespace App\Modules\Teams\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class TeamLogoUpload
{
    public function upload(UploadedFile $logo)
    {
        $logoName = $logo->hashName();
        Storage::disk('s3-team-logos')->put($logoName, file_get_contents($logo));
        return $logoName;
    }
}