<?php

namespace App\Traits;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait HandlesValidation
{
    public function HandlesValidation(Request $request, array $rules, array $messages = [], ?Closure $after = null, bool $fromApp = false)
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($after) {
            $validator->after($after);
        }

        if ($validator->fails()) {
            if ($fromApp) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->errors()->first(),
                    'code' => 102,
                ]);
            }
            return $request->ajax()
                ? response()->json(['errors' => $validator->errors()->all()], 422)
                : redirect()->back()->withErrors($validator)->withInput();
        }

        return null;
    }
}
