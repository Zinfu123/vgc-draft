<?php

namespace App\Kernel\Support;

use Illuminate\Http\RedirectResponse;

final class DraftRedirectHelper
{
    /**
     * @param  array{league_id: int, errors?: array<string, string>, back?: bool}  $result
     */
    public static function fromActionResult(array $result, string $detailRouteName): RedirectResponse
    {
        if (! empty($result['back']) && ! empty($result['errors'])) {
            return redirect()->back()->withErrors($result['errors']);
        }

        $redirect = redirect()->route($detailRouteName, ['league_id' => $result['league_id']]);

        if (! empty($result['errors'])) {
            $redirect = $redirect->withErrors($result['errors']);
        }

        return $redirect;
    }
}
