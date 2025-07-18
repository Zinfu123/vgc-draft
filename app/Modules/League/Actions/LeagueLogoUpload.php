<?php

namespace App\Modules\League\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class LeagueLogoUpload
{
    public function upload(Request $request)
    {
        $logo = $request->file('logo');
        $extension = $logo->getClientOriginalExtension();
        $logoName = $request->name . '.' . $extension;
        Storage::disk('s3-league-logos')->put($logoName, file_get_contents($logo));
        return $logoName;
    }
}