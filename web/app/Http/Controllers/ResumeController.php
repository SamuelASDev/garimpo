<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResumeController extends Controller
{
    public function edit(Request $request): View
    {
        return view('resume.edit', ['resume' => $request->user()->resume]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'skills' => ['nullable', 'string', 'max:3000'],
            'experience' => ['nullable', 'string', 'max:15000'],
            'education' => ['nullable', 'string', 'max:5000'],
            'extras' => ['nullable', 'string', 'max:5000'],
        ]);

        $request->user()->resume()->updateOrCreate([], $data);

        return redirect()->route('resume.edit')->with('status', 'Currículo salvo!');
    }
}
