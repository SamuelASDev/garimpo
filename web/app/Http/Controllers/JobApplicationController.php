<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $applications = $request->user()->jobApplications()
            ->when(array_key_exists((string) $status, JobApplication::STATUSES), fn ($q) => $q->where('status', $status))
            ->latest('applied_at')->latest('id')->get();

        return view('applications.index', [
            'applications' => $applications,
            'status' => $status,
            'counts' => $request->user()->jobApplications()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->jobApplications()->create($this->validated($request) + ['applied_at' => today()]);

        return back()->with('status', 'Candidatura registrada!');
    }

    /** Registra a candidatura a partir de uma vaga do Garimpo, sem duplicar. */
    public function storeFromJob(Request $request, JobPosting $job): RedirectResponse
    {
        $request->user()->jobApplications()->firstOrCreate(
            ['job_posting_id' => $job->id],
            ['title' => $job->title, 'company' => $job->company, 'url' => $job->url, 'applied_at' => today()],
        );

        return back()->with('status', 'Candidatura registrada! Acompanhe em "Candidaturas".');
    }

    public function update(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(JobApplication::STATUSES))],
            'next_step' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($data['status'] !== $application->status) {
            $data['last_contact_at'] = today();
        }
        $application->update($data);

        return back()->with('status', 'Candidatura atualizada.');
    }

    public function destroy(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id, 404);
        $application->delete();

        return back()->with('status', 'Candidatura removida.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
