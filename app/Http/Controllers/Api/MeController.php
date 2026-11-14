<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileUserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): MobileUserResource
    {
        return new MobileUserResource($request->user());
    }
}