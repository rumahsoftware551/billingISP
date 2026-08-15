<?php
namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
class VersionController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'product'=>'Jaringanku',
            'version'=>config('jaringanku.version'),
            'channel'=>config('jaringanku.release_channel'),
        ]);
    }
}
