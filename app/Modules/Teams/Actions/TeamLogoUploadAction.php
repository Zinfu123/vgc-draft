<?php

namespace App\Modules\Teams\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeamLogoUploadAction
{
    public function upload(UploadedFile $logo, int $leagueId, string $name): string
    {
        $extension = strtolower((string) $logo->getClientOriginalExtension());
        $slug = Str::slug($name, '_') ?: 'team';
        $filename = $slug.'-'.Str::lower(Str::random(8)).'.'.$extension;

        Storage::disk('s3-team-logos')->putFileAs((string) $leagueId, $logo, $filename);

        return $leagueId.'/'.$filename;
    }
}
