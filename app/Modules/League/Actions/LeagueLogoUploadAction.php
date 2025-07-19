<?php

namespace App\Modules\League\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class LeagueLogoUploadAction
{
    public function upload(Request $request)
    {
        $logo = $request->file('logo');
        $extension = $logo->getClientOriginalExtension();
        $logoName = $request->name;
        $filepath = str_replace(' ', '_', $logoName) . '.' . $extension;
        Storage::disk('s3-league-logos')->putFileAs($logo, $filepath);
        return $filepath;
    }
}