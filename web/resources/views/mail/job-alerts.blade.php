<x-mail::message>
# Olá, {{ $user->name }}!

Encontramos vagas novas que combinam com os seus alertas.

@foreach ($sections as $section)
<h2 style="margin:28px 0 2px;font-size:17px">🔔 {{ $section['alert']->name }}</h2>
<p style="margin:0 0 8px;color:#6b7280;font-size:13px">{{ $section['alert']->describe() }}</p>
@foreach ($section['jobs'] as $job)
<div style="padding:10px 0;border-bottom:1px solid #e5e7eb">
<a href="{{ route('jobs.show', $job) }}" style="font-weight:600;font-size:15px;color:#047857;text-decoration:none">{{ $job->title }}</a>
<div style="color:#6b7280;font-size:13px;margin-top:2px">{{ $job->company ?? 'Empresa não informada' }} · {{ $job->remote ? 'Remoto' : ($job->location ?? 'Local não informado') }}@if ($job->techs) · {{ implode(', ', array_slice($job->techs, 0, 4)) }}@endif</div>
</div>
@endforeach
@if ($section['total'] > $section['jobs']->count())
<p style="margin:10px 0 0;font-size:13px"><a href="{{ route('jobs.index', $section['alert']->filters) }}" style="color:#047857">+ {{ $section['total'] - $section['jobs']->count() }} vagas neste alerta →</a></p>
@endif
@endforeach

<x-mail::button :url="route('alerts.index')" color="success">
Gerenciar meus alertas
</x-mail::button>

Abra a vaga no Garimpo para ver quanto seu currículo combina e gerar um currículo adaptado.

<p style="color:#9ca3af;font-size:12px">Você recebe este e-mail no máximo 1 vez por dia porque criou alertas no {{ config('app.name') }}. <a href="{{ $unsubscribeUrl }}" style="color:#9ca3af">Parar de receber</a>.</p>
</x-mail::message>
