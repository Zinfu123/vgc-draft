<?php

namespace App\Modules\Teams\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeamLogoUploadAction
{
    public function upload(Request $request)
    {
        $logo = $request->file('logo');
        $extension = $logo->getClientOriginalExtension();
        $logoName = $request->name;
        $filepath = str_replace(' ', '_', $logoName).'.'.$extension;
        Storage::disk('s3-team-logos')->putFileAs($logo, $filepath);

        return $filepath;
    }
}
