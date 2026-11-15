@extends('errors.layout')

@section('code', '419')
@section('emoji', '⏳')
@section('title', 'Sua sessão expirou')
@section('message', 'Por segurança, a página ficou muito tempo aberta sem uso. Recarregue e tente de novo; o que você salvou antes continua lá.')
@section('actions')
    <button class="primary" type="button" onclick="location.reload()">Recarregar</button>
    <a class="secondary" href="/">Ver as vagas</a>
@endsection
