<?php

namespace App\Modules\League\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeagueLogoUploadAction
{
    public function upload(Request $request)
    {
        $logo = $request->file('logo');
        $extension = $logo->getClientOriginalExtension();
        $logoName = str_replace('\'', '', $request->name);
        $filepath = str_replace(' ', '_', $logoName).'.'.$extension;
        Storage::disk('s3-league-logos')->putFileAs($logo, $filepath);

        return $filepath;
    }
}
