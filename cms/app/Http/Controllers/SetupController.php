<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SetupController
{
    private function allowed(string $token): void
    {
        $hash = config('cms.setup_hash');
        $expires = config('cms.setup_expires');
        abort_unless($hash && $expires && now()->lt($expires) && hash_equals($hash, hash('sha256', $token)) && ! User::where('is_admin', true)->exists(), 404);
    }

    public function show(string $token)
    {
        $this->allowed($token);

        return response()->view('setup', compact('token'))->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, string $token)
    {
        $this->allowed($token);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:254|unique:users,email', 'password' => ['required', 'string', 'min:12', 'confirmed']]);
        // One administrator only; a transactional lock protects concurrent submissions.
        $user = Cache::lock('first-administrator', 30)->block(5, function () use ($data, $token) {
            $this->allowed($token);
            $user = new User($data);
            $user->is_admin = true;
            $user->save();

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
