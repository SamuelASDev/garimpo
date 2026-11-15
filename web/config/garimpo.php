<?php

/*
 * Dados do responsável pelo site exibidos na Política de Privacidade (LGPD art. 9º e 41).
 * Preencha no .env de produção; não deixe dados pessoais no código.
 */
return [
    'controller' => env('GARIMPO_CONTROLLER', 'Responsável pelo Garimpo'),
    'privacy_email' => env('GARIMPO_PRIVACY_EMAIL', 'privacidade@exemplo.com'),

    // Idade máxima das vagas aceitas na coleta (dias). A primeira coleta (banco vazio) vai mais longe.
    'max_age_days' => (int) env('GARIMPO_MAX_AGE_DAYS', 7),
    'first_max_age_days' => (int) env('GARIMPO_FIRST_MAX_AGE_DAYS', 30),
];
